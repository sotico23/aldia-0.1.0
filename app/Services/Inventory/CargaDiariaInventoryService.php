<?php

namespace App\Services\Inventory;

use App\Models\CargaDiaria;
use App\Models\InventoryMovement;

class CargaDiariaInventoryService
{
    /**
     * Registra EGRESO del almacén cuando se crea una carga diaria con productos.
     * Los productos salen del almacén hacia el camión.
     */
    public function cargarProductos(CargaDiaria $carga, int $ownerId, int $userId): void
    {
        if (! $carga->almacen_id) {
            return;
        }

        $productos = $carga->productos()->with('producto')->get();

        foreach ($productos as $cargaProducto) {
            if ($cargaProducto->cantidad_bordo <= 0) {
                continue;
            }

            InventoryMovement::registrarMovimiento(
                productId: $cargaProducto->producto_id,
                userId: $userId,
                type: 'EGRESO',
                quantity: (float) $cargaProducto->cantidad_bordo,
                sourceWarehouseId: $carga->almacen_id,
                destinationWarehouseId: null,
                description: "Carga diaria #{$carga->id} - Carga inicial al camión",
                ownerId: $ownerId,
            );
        }
    }

    /**
     * Procesa el inventario al cerrar una carga diaria (recarga).
     * Devuelve productos al almacén y opcionalmente carga una nueva tanda.
     */
    public function procesarRecarga(
        CargaDiaria $carga,
        array $productosValidados,
        bool $crearNuevaCarga,
        int $ownerId,
        int $userId,
    ): void {
        if (! $carga->almacen_id) {
            return;
        }

        foreach ($productosValidados as $prod) {
            $productoId = $prod['producto_id'];
            $cantidadLlena = (int) ($prod['cantidad_llena'] ?? 0);
            $cantidadVacia = (int) ($prod['cantidad_vacia'] ?? 0);

            // INGRESO: productos que regresan al almacén (llenas + vacías)
            $cantidadRetornada = $cantidadLlena + $cantidadVacia;
            if ($cantidadRetornada > 0) {
                InventoryMovement::registrarMovimiento(
                    productId: $productoId,
                    userId: $userId,
                    type: 'INGRESO',
                    quantity: (float) $cantidadRetornada,
                    sourceWarehouseId: null,
                    destinationWarehouseId: $carga->almacen_id,
                    description: "Carga diaria #{$carga->id} - Retorno de productos al almacén",
                    ownerId: $ownerId,
                );
            }

            // EGRESO: si se crea nueva carga, las llenas salen nuevamente del almacén
            if ($crearNuevaCarga && $cantidadLlena > 0) {
                InventoryMovement::registrarMovimiento(
                    productId: $productoId,
                    userId: $userId,
                    type: 'EGRESO',
                    quantity: (float) $cantidadLlena,
                    sourceWarehouseId: $carga->almacen_id,
                    destinationWarehouseId: null,
                    description: "Carga diaria #{$carga->id} - Nueva carga al camión",
                    ownerId: $ownerId,
                );
            }
        }
    }

    /**
     * Procesa el inventario al confirmar renovación de carga.
     * Misma lógica que recarga: devuelve productos y opcionalmente recarga.
     */
    public function procesarRenovacion(
        CargaDiaria $carga,
        array $productosValidados,
        int $ownerId,
        int $userId,
    ): void {
        if (! $carga->almacen_id) {
            return;
        }

        $productosRenovar = array_filter($productosValidados, fn ($p) => ! empty($p['renovar']));

        foreach ($productosValidados as $prod) {
            $productoId = $prod['producto_id'];
            $cantidadBordo = (int) ($prod['cantidad_bordo'] ?? 0);
            $cantidadVendida = (int) ($prod['cantidad_vendida'] ?? 0);
            $cantidadDevuelta = (int) ($prod['cantidad_devuelta'] ?? 0);
            $renovar = ! empty($prod['renovar']);

            // INGRESO: todos los productos regresan al almacén
            if ($cantidadDevuelta > 0) {
                InventoryMovement::registrarMovimiento(
                    productId: $productoId,
                    userId: $userId,
                    type: 'INGRESO',
                    quantity: (float) $cantidadDevuelta,
                    sourceWarehouseId: null,
                    destinationWarehouseId: $carga->almacen_id,
                    description: "Carga diaria #{$carga->id} - Retorno de productos al almacén",
                    ownerId: $ownerId,
                );
            }

            // EGRESO: si se renueva este producto, la cantidad restante sale del almacén
            if ($renovar) {
                $cantidadRenovar = max(0, $cantidadBordo - $cantidadVendida - $cantidadDevuelta);
                if ($cantidadRenovar > 0) {
                    InventoryMovement::registrarMovimiento(
                        productId: $productoId,
                        userId: $userId,
                        type: 'EGRESO',
                        quantity: (float) $cantidadRenovar,
                        sourceWarehouseId: $carga->almacen_id,
                        destinationWarehouseId: null,
                        description: "Carga diaria #{$carga->id} - Nueva carga al camión",
                        ownerId: $ownerId,
                    );
                }
            }
        }
    }

    /**
     * Restaura el inventario al eliminar una carga diaria.
     * INGRESO de todos los productos que estaban en el camión.
     */
    public function restaurarInventario(CargaDiaria $carga, int $ownerId, int $userId): void
    {
        if (! $carga->almacen_id) {
            return;
        }

        $productos = $carga->productos()->get();

        foreach ($productos as $cargaProducto) {
            // Solo restaurar lo que no fue vendido ni devuelto
            $cantidadEnCamion = $cargaProducto->cantidad_bordo
                - $cargaProducto->cantidad_vendida
                - $cargaProducto->cantidad_devuelta;

            if ($cantidadEnCamion > 0) {
                InventoryMovement::registrarMovimiento(
                    productId: $cargaProducto->producto_id,
                    userId: $userId,
                    type: 'INGRESO',
                    quantity: (float) $cantidadEnCamion,
                    sourceWarehouseId: null,
                    destinationWarehouseId: $carga->almacen_id,
                    description: "Carga diaria #{$carga->id} - Eliminación de carga, productos restaurados",
                    ownerId: $ownerId,
                );
            }
        }
    }
}
