<?php

namespace App\Services\Inventory;

use App\Exceptions\StockInsufficientException;
use App\Models\DetalleVenta;
use App\Models\Inventario;
use App\Models\InventoryMovement;
use App\Models\Producto;
use App\Models\Vacio;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SalesInventoryService
{
    /**
     * Procesa el inventario para una venta (ambos flujos: POS y ventas tradicionales).
     * Operación atómica con bloqueos pesimistas y manejo de envases retornables.
     *
     * @param  array  $items  Formato: [{producto_id, cantidad, cantidad_retornada?, almacen_id}]
     * @param  array  $almacenIds  Almacenes asociados a la venta
     */
    public function processSaleInventory(
        Venta $venta,
        array $items,
        int $ownerId,
        int $userId,
        array $almacenIds
    ): void {
        Log::info('SalesInventoryService::processSaleInventory START', [
            'venta_id' => $venta->id,
            'items_count' => count($items),
            'items' => $items,
            'almacenIds' => $almacenIds,
        ]);

        DB::transaction(function () use ($venta, $items, $ownerId, $userId, $almacenIds) {
            foreach ($items as $item) {
                $this->processItem($venta, $item, $ownerId, $userId, $almacenIds);
            }
        });
    }

    /**
     * Procesa un item individual de la venta.
     */
    private function processItem(
        Venta $venta,
        array $item,
        int $ownerId,
        int $userId,
        array $almacenIds
    ): void {
        $producto = Producto::findOrFail($item['producto_id']);
        $cantidadVendida = (float) $item['cantidad'];
        $cantidadRetornada = (int) ($item['cantidad_retornada'] ?? 0);

        // Usar el almacen_id del item si está especificado y es válido, sino el primero de la venta
        $almacenId = (int) ($item['almacen_id'] ?? 0);
        if (! $almacenId || ! in_array($almacenId, $almacenIds)) {
            $almacenId = $almacenIds[0] ?? 0;
        }

        // 1. DESCUENTO DE RECARGAS (Cilindros llenos) - EGRESO con lockForUpdate
        $this->decrementProductStock(
            productoId: $producto->id,
            cantidad: $cantidadVendida,
            almacenId: $almacenId,
            userId: $userId,
            tipoMovimiento: 'EGRESO',
            descripcion: "Venta POS #{$venta->id}",
            ownerId: $ownerId,
        );

        // 2. MANEJO DE ENVASES RETORNABLES
        if ($producto->envase_retornable && $producto->envase_producto_id && $producto->envase_producto_id != $producto->id) {
            $envaseProducto = Producto::findOrFail($producto->envase_producto_id);
            $cantidadVendidaFloat = (float) $item['cantidad'];
            $envasesEntregados = $cantidadVendidaFloat; // 1:1 con recargas vendidas

            // A. EGRESO: Entregar envases llenos al cliente (1:1 con recargas)
            if ($envasesEntregados > 0) {
                $this->createEnvaseDelivery(
                    venta: $venta,
                    envaseProducto: $envaseProducto,
                    cantidad: $envasesEntregados,
                    almacenId: $almacenId,
                    userId: $userId,
                    ownerId: $ownerId,
                );
            }

            // B. INGRESO: Cliente devuelve envases vacíos
            if ($cantidadRetornada > 0) {
                $this->processEnvaseReturn(
                    venta: $venta,
                    envaseProductoId: $producto->envase_producto_id,
                    cantidad: $cantidadRetornada,
                    almacenId: $almacenId,
                    userId: $userId,
                    ownerId: $ownerId,
                    clienteId: $venta->cliente_id,
                );
            }

            // C. TRACKING: Envases pendientes de retorno (entregados - devueltos)
            $envasesPendientes = $envasesEntregados - $cantidadRetornada;
            if ($envasesPendientes > 0) {
                $this->trackVacioEntregado(
                    envaseProductoId: $producto->envase_producto_id,
                    cantidad: $envasesPendientes,
                    ownerId: $ownerId,
                    clienteId: $venta->cliente_id,
                    ventaId: $venta->id,
                );
            }
        }
    }

    /**
     * Descuenta stock del producto principal (recarga) con lockForUpdate.
     */
    private function decrementProductStock(
        int $productoId,
        float $cantidad,
        int $almacenId,
        int $userId,
        string $tipoMovimiento,
        string $descripcion,
        int $ownerId
    ): void {
        // Usar InventoryMovement::registrarMovimiento que ya maneja lockForUpdate
        InventoryMovement::registrarMovimiento(
            productId: $productoId,
            userId: $userId,
            type: $tipoMovimiento,
            quantity: $cantidad,
            sourceWarehouseId: $tipoMovimiento === 'EGRESO' ? ($almacenId ?: null) : null,
            destinationWarehouseId: $tipoMovimiento === 'INGRESO' ? ($almacenId ?: null) : null,
            description: $descripcion,
            ownerId: $ownerId,
        );
    }

    /**
     * Crea la entrega de envases físicos al cliente (EGRESO de envases).
     * Crea DetalleVenta para el envase (visible en ticket/factura).
     */
    private function createEnvaseDelivery(
        Venta $venta,
        Producto $envaseProducto,
        float $cantidad,
        int $almacenId,
        int $userId,
        int $ownerId
    ): void {
        // Crear DetalleVenta para el envase (visible en ticket/factura)
        DetalleVenta::create([
            'venta_id' => $venta->id,
            'producto_id' => $envaseProducto->id,
            'cantidad' => $cantidad,
            'precio_unitario' => $envaseProducto->precio_venta,
            'subtotal' => (int) round($cantidad * $envaseProducto->precio_venta),
        ]);

        // EGRESO: Envase entregado al cliente
        InventoryMovement::registrarMovimiento(
            productId: $envaseProducto->id,
            userId: $userId,
            type: 'EGRESO',
            quantity: (int) $cantidad,
            sourceWarehouseId: $almacenId ?: null,
            description: "Entrega envases Venta #{$venta->id}",
            ownerId: $envaseProducto->owner_id,
        );

        // Track Vacio como 'entregado' (en poder del cliente)
        $this->trackVacioEntregado(
            envaseProductoId: $envaseProducto->id,
            cantidad: $cantidad,
            ownerId: $ownerId,
            clienteId: $venta->cliente_id,
            ventaId: $venta->id,
        );
    }

    /**
     * Procesa retorno de envases vacíos por el cliente (INGRESO).
     * Solo incrementa inventario, SIN impacto financiero (no genera nota de crédito).
     */
    private function processEnvaseReturn(
        Venta $venta,
        int $envaseProductoId,
        int $cantidad,
        int $almacenId,
        int $userId,
        int $ownerId,
        ?int $clienteId
    ): void {
        // INGRESO: Envases vacíos devueltos al almacén
        InventoryMovement::registrarMovimiento(
            productId: $envaseProductoId,
            userId: $userId,
            type: 'INGRESO',
            quantity: $cantidad,
            destinationWarehouseId: $almacenId,
            description: "Retorno envases Venta #{$venta->id}",
            ownerId: $ownerId,
        );

        // Track Vacio como 'disponible' (retornado a bodega)
        $this->trackVacioRetornado(
            envaseProductoId: $envaseProductoId,
            cantidad: $cantidad,
            ownerId: $ownerId,
            clienteId: $clienteId,
            ventaId: $venta->id,
        );
    }

    /**
     * Track: Envases entregados al cliente (estado 'entregado').
     */
    private function trackVacioEntregado(
        int $envaseProductoId,
        float $cantidad,
        int $ownerId,
        ?int $clienteId = null,
        ?int $ventaId = null
    ): void {
        $vacioEntregado = Vacio::where('owner_id', $ownerId)
            ->where('producto_id', $envaseProductoId)
            ->where('cliente_id', $clienteId)
            ->where('estado', 'entregado')
            ->first();

        if ($vacioEntregado) {
            $vacioEntregado->increment('cantidad', $cantidad);
        } else {
            Vacio::create([
                'owner_id' => $ownerId,
                'producto_id' => $envaseProductoId,
                'cliente_id' => $clienteId,
                'cantidad' => $cantidad,
                'cantidad_minima' => 0,
                'estado' => 'entregado',
                'observaciones' => "Entregado en Venta #{$ventaId}",
            ]);
        }
    }

    /**
     * Track: Envases retornados al almacén (estado 'disponible').
     */
    private function trackVacioRetornado(
        int $envaseProductoId,
        float $cantidad,
        int $ownerId,
        ?int $clienteId = null,
        ?int $ventaId = null
    ): void {
        $vacioExistente = Vacio::where('owner_id', $ownerId)
            ->where('producto_id', $envaseProductoId)
            ->where('cliente_id', $clienteId)
            ->where('estado', 'disponible')
            ->first();

        if ($vacioExistente) {
            $vacioExistente->increment('cantidad', $cantidad);
        } else {
            Vacio::create([
                'owner_id' => $ownerId,
                'producto_id' => $envaseProductoId,
                'cliente_id' => $clienteId,
                'cantidad' => $cantidad,
                'cantidad_minima' => 0,
                'estado' => 'disponible',
                'observaciones' => "Retorno en Venta #{$ventaId}",
            ]);
        }
    }

    /**
     * Restaura inventario al cancelar una venta.
     */
    public function restoreSaleInventory(Venta $venta): void
    {
        DB::transaction(function () use ($venta) {
            $venta->load(['detalleVentas.producto', 'almacenes']);
            $almacenIds = $venta->almacenes->pluck('id')->toArray();

            foreach ($venta->detalleVentas as $item) {
                $cantidad = (float) $item->cantidad;

                // INGRESO: Restaurar stock de recargas
                foreach ($venta->almacenes as $almacen) {
                    if ($cantidad <= 0) {
                        break;
                    }

                    $inventario = Inventario::where('producto_id', $item->producto_id)
                        ->where('almacen_id', $almacen->id)
                        ->lockForUpdate()
                        ->first();

                    if ($inventario) {
                        $inventario->increment('cantidad', $cantidad);

                        InventoryMovement::create([
                            'owner_id' => $venta->owner_id,
                            'user_id' => auth()->id(),
                            'product_id' => $item->producto_id,
                            'destination_warehouse_id' => $almacen->id,
                            'quantity' => (int) $cantidad,
                            'type' => 'INGRESO',
                            'description' => "Restauración por cancelación Venta #{$venta->id}",
                        ]);

                        $cantidad = 0;
                    }
                }

                // Revertir envases
                $producto = $item->producto;
                if ($producto && $producto->envase_retornable && $producto->envase_producto_id) {
                    $cantidadRetornada = $item->cantidad_retornada ?? 0;
                    $envasesPendientes = $item->cantidad - $cantidadRetornada;

                    // Revertir retorno (decrementar 'disponible')
                    if ($cantidadRetornada > 0) {
                        $primerAlmacen = $venta->almacenes->first()?->id ?? $venta->almacen_id;
                        $invEnvase = Inventario::where('producto_id', $producto->envase_producto_id)
                            ->where('almacen_id', $primerAlmacen)
                            ->lockForUpdate()
                            ->first();

                        if ($invEnvase) {
                            $invEnvase->decrement('cantidad', $cantidadRetornada);
                        }
                    }

                    // Revertir entregados (decrementar 'entregado')
                    if ($envasesPendientes > 0) {
                        $vacioEntregado = Vacio::where('owner_id', $venta->owner_id)
                            ->where('producto_id', $producto->envase_producto_id)
                            ->where('cliente_id', $venta->cliente_id)
                            ->where('estado', 'entregado')
                            ->first();

                        if ($vacioEntregado) {
                            $vacioEntregado->decrement('cantidad', $envasesPendientes);
                        }
                    }
                }
            }
        });
    }

    /**
     * Ajusta inventario al actualizar una venta (diferencial entre viejo y nuevo).
     */
    public function adjustInventoryOnUpdate(
        Venta $venta,
        array $newItems,
        $oldDetalles
    ): void {
        DB::transaction(function () use ($venta, $newItems, $oldDetalles) {
            $venta->load('almacenes');
            $almacenIds = $venta->almacenes->pluck('id')->toArray();

            foreach ($newItems as $newItem) {
                $productoId = $newItem['producto_id'];
                $newCantidad = (float) $newItem['cantidad'];
                $oldCantidad = $oldDetalles->has($productoId)
                    ? (float) $oldDetalles->get($productoId)->cantidad
                    : 0;

                $diff = $newCantidad - $oldCantidad;

                if ($diff == 0) {
                    continue;
                }

                foreach ($almacenIds as $almacenId) {
                    if ($diff == 0) {
                        break;
                    }

                    $inventario = Inventario::where('producto_id', $productoId)
                        ->where('almacen_id', $almacenId)
                        ->lockForUpdate()
                        ->first();

                    if (! $inventario) {
                        continue;
                    }

                    if ($diff > 0) {
                        $aDescontar = min($diff, $inventario->cantidad);
                        $inventario->decrement('cantidad', $aDescontar);
                        $diff -= $aDescontar;
                    } else {
                        $inventario->increment('cantidad', abs($diff));
                        $diff = 0;
                    }
                }

                if ($diff > 0) {
                    $producto = Producto::find($productoId);
                    throw new StockInsufficientException(
                        productName: $producto?->nombre ?? "Producto #{$productoId}",
                        requested: (float) $newCantidad,
                        available: (float) ($newCantidad - $diff),
                        productId: (int) $productoId,
                    );
                }
            }
        });
    }
}
