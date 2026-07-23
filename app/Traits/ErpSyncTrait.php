<?php

namespace App\Traits;

use App\Exceptions\StockInsufficientException;
use App\Models\Almacen;
use App\Models\Asiento;
use App\Models\Cliente;
use App\Models\DetalleVenta;
use App\Models\Inventario;
use App\Models\InventoryMovement;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Tesoreria;
use App\Models\Venta;
use App\Scopes\OwnerScope;
use Illuminate\Support\Facades\DB;

trait ErpSyncTrait
{
    /**
     * Sincroniza un pedido confirmado con el ERP (Ventas, Tesorería, Contabilidad, Inventario).
     *
     * @throws StockInsufficientException
     */
    public function syncPedidoToErp(Pedido $pedido): void
    {
        if ($pedido->erp_synced_at !== null) {
            return;
        }

        $publicProfile = $pedido->publicProfile()->withoutGlobalScope(OwnerScope::class)->first();
        if (! $publicProfile) {
            return;
        }

        $vendedorId = $publicProfile->user_id;
        $vendedorOwnerId = $publicProfile->owner_id;

        DB::transaction(function () use ($pedido, $vendedorId, $vendedorOwnerId) {
            // 1. Validar stock insuficiente ANTES de procesar
            $this->validarStockPedido($pedido);

            // 2. Asegurar registro de Cliente para el Vendedor
            $clienteEmail = $pedido->cliente->email ?? "guest_{$pedido->id}@marketplace.com";
            $clienteErp = Cliente::withoutGlobalScope(OwnerScope::class)
                ->where('email', $clienteEmail)
                ->first();

            if (! $clienteErp) {
                $clienteErp = Cliente::withoutGlobalScope(OwnerScope::class)->create([
                    'email' => $clienteEmail,
                    'user_id' => $vendedorId,
                    'nombre' => $pedido->nombre_cliente,
                    'telefono' => $pedido->telefono_cliente ?? '',
                    'direccion' => $pedido->direccion_cliente ?? '',
                    'activo' => true,
                    'owner_id' => $vendedorOwnerId,
                ]);
            }

            // 3. Mapear método de pago
            $metodoPagoErp = match (strtolower($pedido->metodo_pago ?? '')) {
                'efectivo' => 'efectivo',
                'tarjeta', 'debito', 'credito', 'webpay', 'paypal', 'mercadopago' => 'tarjeta',
                'transferencia' => 'transferencia',
                default => 'otro'
            };

            // 4. Crear Venta
            $venta = Venta::withoutGlobalScope(OwnerScope::class)->create([
                'numero_factura' => str_replace('PED-', 'VMT-', $pedido->numero_pedido),
                'cliente_id' => $clienteErp->id,
                'user_id' => $vendedorId,
                'owner_id' => $vendedorOwnerId,
                'fecha' => now(),
                'subtotal' => $pedido->subtotal,
                'iva' => $pedido->impuesto,
                'total' => $pedido->total,
                'metodo_pago' => $metodoPagoErp,
                'estado' => 'pagada',
                'notas' => "Originado desde Pedido Marketplace #{$pedido->numero_pedido}. ".($pedido->notas ?? ''),
            ]);

            // 5. Obtener almacén por defecto para el vendedor
            $almacenId = $this->obtenerAlmacenDefault($vendedorOwnerId);

            // 6. Procesar items: DetalleVenta + InventoryMovement
            $productosMap = Producto::whereIn('id', collect($pedido->items)->pluck('producto_id'))
                ->get()
                ->keyBy('id');

            foreach ($pedido->items as $item) {
                $producto = $productosMap->get($item->producto_id);
                $contenido = (float) ($producto->contenido_por_unidad ?? 1.0);
                $pesoBase = (float) ($producto->peso_base ?? 0.0);
                $subtotalMetrica = ($item->cantidad * $contenido) + ($item->cantidad * $pesoBase);

                DetalleVenta::create([
                    'venta_id' => $venta->id,
                    'producto_id' => $item->producto_id,
                    'cantidad' => $item->cantidad,
                    'precio_unitario' => $item->precio_unitario,
                    'subtotal' => $item->subtotal,
                    'subtotal_metrica' => $subtotalMetrica,
                ]);

                // Generar movimiento de inventario (EGRESO)
                InventoryMovement::registrarMovimiento(
                    productId: (int) $item->producto_id,
                    userId: $vendedorId,
                    type: 'EGRESO',
                    quantity: (int) $item->cantidad,
                    sourceWarehouseId: $almacenId,
                    description: "Venta marketplace #{$pedido->numero_pedido} - Venta #{$venta->id}",
                    ownerId: $vendedorOwnerId,
                );
            }

            // 7. Registro en Tesorería
            Tesoreria::withoutGlobalScope(OwnerScope::class)->create([
                'tipo' => 'ingreso',
                'monto' => $pedido->total,
                'cuenta' => 'Caja/Banco',
                'descripcion' => "Venta Marketplace - Pedido #{$pedido->numero_pedido}",
                'fecha' => now(),
                'user_id' => $vendedorId,
                'owner_id' => $vendedorOwnerId,
            ]);

            // 8. Asiento Contable
            $asiento = Asiento::withoutGlobalScope(OwnerScope::class)->create([
                'fecha' => now(),
                'numero' => 'AS-VMT-'.time(),
                'descripcion' => "Registro de venta marketplace #{$pedido->numero_pedido}",
                'tipo' => 'venta',
                'total_debe' => $pedido->total,
                'total_haber' => $pedido->total,
                'estado' => true,
                'owner_id' => $vendedorOwnerId,
            ]);

            $asiento->detalles()->create([
                'cuenta' => 'Caja/Banco', 'cuenta_codigo' => '1.1.01', 'debe' => $pedido->total, 'haber' => 0, 'owner_id' => $vendedorOwnerId,
            ]);

            $asiento->detalles()->create([
                'cuenta' => 'Ventas Marketplace', 'cuenta_codigo' => '4.1.01', 'debe' => 0, 'haber' => $pedido->subtotal, 'owner_id' => $vendedorOwnerId,
            ]);

            if ($pedido->impuesto > 0) {
                $asiento->detalles()->create([
                    'cuenta' => 'IVA Débito Fiscal', 'cuenta_codigo' => '2.1.03', 'debe' => 0, 'haber' => $pedido->impuesto, 'owner_id' => $vendedorOwnerId,
                ]);
            }

            // 9. Marcar sync solo si todo fue exitoso
            $pedido->update(['erp_synced_at' => now()]);
        });
    }

    /**
     * Valida que todo el pedido tenga stock suficiente antes de procesar.
     *
     * @throws StockInsufficientException
     */
    private function validarStockPedido(Pedido $pedido): void
    {
        foreach ($pedido->items as $item) {
            $producto = Producto::withoutGlobalScope(OwnerScope::class)
                ->where('id', $item->producto_id)
                ->first();

            if (! $producto) {
                throw new StockInsufficientException(
                    productName: "Producto #{$item->producto_id}",
                    requested: (float) $item->cantidad,
                    available: 0,
                    productId: (int) $item->producto_id,
                );
            }

            $stockTotal = Inventario::withoutGlobalScope(OwnerScope::class)
                ->where('producto_id', $item->producto_id)
                ->sum('cantidad');

            if ((float) $stockTotal < (float) $item->cantidad) {
                throw new StockInsufficientException(
                    productName: $producto->nombre,
                    requested: (float) $item->cantidad,
                    available: (float) $stockTotal,
                    productId: $producto->id,
                );
            }
        }
    }

    /**
     * Obtiene el almacén principal del tenant para generar movimientos de egreso.
     */
    private function obtenerAlmacenDefault(int $ownerId): ?int
    {
        $almacen = Almacen::withoutGlobalScope(OwnerScope::class)
            ->where('owner_id', $ownerId)
            ->where('activo', true)
            ->orderBy('id')
            ->first();

        return $almacen?->id;
    }
}
