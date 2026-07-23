<?php

namespace App\Console\Commands;

use App\Models\Almacen;
use App\Models\Cliente;
use App\Models\Cupon;
use App\Models\CuponUso;
use App\Models\DetalleVenta;
use App\Models\Inventario;
use App\Models\InventoryMovement;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerificarStockCupon extends Command
{
    protected $signature = 'verificar:stock-cupon
                            {--producto-id= : ID del producto a usar (auto-crea si no se provee)}
                            {--cupon-id= : ID del cupón a usar (auto-crea si no se provee)}
                            {--cantidad=2 : Cantidad a vender}
                            {--precio=50000 : Precio unitario}';

    protected $description = 'Simula una venta POS con cupón y verifica stock: Inicial - Vendido = Final';

    public function handle(): int
    {
        $this->newLine();
        $this->info('=== Verificación de Stock con Cupón ===');
        $this->newLine();

        return DB::transaction(function () {
            $resultado = $this->ejecutarVerificacion();
            DB::rollBack();

            return $resultado;
        });
    }

    private function ejecutarVerificacion(): int
    {
        $userId = $this->resolveUser();
        if (! $userId) {
            $this->error('No se encontró un usuario válido.');

            return 1;
        }

        $almacen = Almacen::where('owner_id', $userId)->first();
        if (! $almacen) {
            $this->error('No se encontró un almacén. Cree uno primero.');

            return 1;
        }

        $producto = $this->resolveProducto($userId);
        if (! $producto) {
            $this->error('No se pudo crear/encontrar el producto.');

            return 1;
        }

        $cupon = $this->resolveCupon($userId, $producto);
        if (! $cupon) {
            $this->error('No se pudo crear/encontrar el cupón.');

            return 1;
        }

        $cantidad = (int) $this->option('cantidad');
        $precio = (float) $this->option('precio');

        // Estado inicial
        $inventario = Inventario::where('producto_id', $producto->id)
            ->where('almacen_id', $almacen->id)
            ->first();

        $stockInicial = $inventario ? (float) $inventario->cantidad : 0.0;
        $movimientosIniciales = InventoryMovement::withoutGlobalScopes()
            ->where('product_id', $producto->id)
            ->where('type', 'EGRESO')
            ->count();
        $usosIniciales = $cupon->fresh()->usos_actuales;

        $this->info("Producto: {$producto->nombre} (ID: {$producto->id})");
        $this->info("Cupón: {$cupon->codigo} (Tipo: {$cupon->tipo}, ID: {$cupon->id})");
        $this->info("Almacén: {$almacen->nombre} (ID: {$almacen->id})");
        $this->newLine();
        $this->info('--- Estado Inicial ---');
        $this->info("Stock producto:       {$stockInicial}");
        $this->info("Movimientos EGRESO:   {$movimientosIniciales}");
        $this->info("Usos del cupón:       {$usosIniciales}");

        // Calcular descuento esperado
        $subtotal = $cantidad * $precio;
        $items = [['producto_id' => $producto->id, 'cantidad' => $cantidad, 'precio_unitario' => $precio]];
        $descuentoEsperado = $cupon->calcularDescuentoProductos($items);
        $this->info('Descuento esperado:   $'.number_format($descuentoEsperado, 0, ',', '.'));
        $this->newLine();

        // Ejecutar venta
        $this->info('--- Ejecutando Venta POS ---');

        $cliente = Cliente::where('owner_id', $userId)->first();
        if (! $cliente) {
            $cliente = Cliente::create([
                'owner_id' => $userId,
                'nombre' => 'Cliente Verificación',
                'email' => 'verificacion@test.local',
            ]);
        }

        $venta = Venta::create([
            'owner_id' => $userId,
            'user_id' => $userId,
            'cliente_id' => $cliente->id,
            'numero' => 'VERIF-'.time(),
            'fecha' => now(),
            'subtotal' => (int) $subtotal,
            'iva' => 0,
            'total' => (int) $subtotal,
            'incluye_iva' => false,
            'metodo_pago' => 'efectivo',
            'tipo_documento' => 'boleta',
            'almacen_id' => $almacen->id,
            'es_pos' => true,
            'estado' => 'pagada',
            'tipo_descuento' => 'monto',
            'valor_descuento' => (int) $descuentoEsperado,
            'monto_descuento' => (int) $descuentoEsperado,
            'descuento' => (int) $descuentoEsperado,
            'cupon_id' => $cupon->id,
            'monto_descuento_cupon' => $descuentoEsperado,
        ]);

        $venta->almacenes()->attach($almacen->id);

        DetalleVenta::create([
            'venta_id' => $venta->id,
            'producto_id' => $producto->id,
            'cantidad' => $cantidad,
            'precio_unitario' => (int) $precio,
            'subtotal' => (int) ($cantidad * $precio),
        ]);

        InventoryMovement::registrarMovimiento(
            productId: $producto->id,
            userId: $userId,
            type: 'EGRESO',
            quantity: $cantidad,
            sourceWarehouseId: $almacen->id,
            description: "Verificación stock cupón Venta #{$venta->id}",
            ownerId: $userId,
        );

        CuponUso::create([
            'cupon_id' => $cupon->id,
            'venta_id' => $venta->id,
            'user_id' => $userId,
            'monto_total' => (float) $subtotal,
            'monto_descuento' => $descuentoEsperado,
        ]);

        $cupon->increment('usos_actuales');

        // Estado final
        $inventario->refresh();
        $stockFinal = (float) $inventario->cantidad;
        $movimientosFinales = InventoryMovement::withoutGlobalScopes()
            ->where('product_id', $producto->id)
            ->where('type', 'EGRESO')
            ->count();
        $usosFinales = $cupon->fresh()->usos_actuales;

        $this->newLine();
        $this->info('--- Estado Final ---');
        $this->info("Stock producto:       {$stockFinal}");
        $this->info("Movimientos EGRESO:   {$movimientosFinales}");
        $this->info("Usos del cupón:       {$usosFinales}");
        $this->newLine();

        // Verificaciones
        $this->info('--- Verificaciones ---');
        $pass = true;

        $stockEsperado = $stockInicial - $cantidad;
        if (abs($stockFinal - $stockEsperado) < 0.01) {
            $this->info("✓ Stock: {$stockInicial} - {$cantidad} = {$stockFinal} (ESPERADO: {$stockEsperado})");
        } else {
            $this->error("✗ Stock: {$stockInicial} - {$cantidad} = {$stockFinal} (ESPERADO: {$stockEsperado})");
            $pass = false;
        }

        if ($movimientosFinales === $movimientosIniciales + 1) {
            $this->info("✓ Movimiento EGRESO creado: {$movimientosIniciales} → {$movimientosFinales}");
        } else {
            $this->error('✗ Movimientos EGRESO: esperado '.($movimientosIniciales + 1).", obtenido {$movimientosFinales}");
            $pass = false;
        }

        if ($usosFinales === $usosIniciales + 1) {
            $this->info("✓ Usos cupón incrementados: {$usosIniciales} → {$usosFinales}");
        } else {
            $this->error('✗ Usos cupón: esperado '.($usosIniciales + 1).", obtenido {$usosFinales}");
            $pass = false;
        }

        $usoRecord = CuponUso::where('venta_id', $venta->id)->first();
        if ($usoRecord && abs((float) $usoRecord->monto_descuento - $descuentoEsperado) < 0.01) {
            $this->info('✓ CuponUso registrado: descuento = $'.number_format($usoRecord->monto_descuento, 0, ',', '.'));
        } else {
            $this->error('✗ CuponUso no encontrado o monto incorrecto');
            $pass = false;
        }

        if ($venta->cupon_id === $cupon->id) {
            $this->info("✓ Venta vinculada al cupón: cupon_id = {$venta->cupon_id}");
        } else {
            $this->error('✗ Venta no vinculada al cupón');
            $pass = false;
        }

        $this->newLine();
        if ($pass) {
            $this->info('✅ TODAS LAS VERIFICACIONES PASARON');
        } else {
            $this->error('❌ ALGUNAS VERIFICACIONES FALLARON');
        }

        return $pass ? 0 : 1;
    }

    private function resolveUser(): ?int
    {
        $userId = User::where('is_active', true)->first()?->id;

        return $userId;
    }

    private function resolveProducto(int $ownerId): ?Producto
    {
        $productoId = $this->option('producto-id');

        if ($productoId) {
            return Producto::find($productoId);
        }

        return Producto::create([
            'owner_id' => $ownerId,
            'nombre' => 'Producto Verificación Stock '.time(),
            'sku' => 'VST-'.time(),
            'codigo' => 'VST-'.time(),
            'precio_venta' => (float) $this->option('precio'),
            'precio_compra' => 0,
            'Stock' => 1000,
            'activo' => true,
        ]);
    }

    private function resolveCupon(int $ownerId, Producto $producto): ?Cupon
    {
        $cuponId = $this->option('cupon-id');

        if ($cuponId) {
            $cupon = Cupon::find($cuponId);
            if ($cupon && ! $cupon->productos->contains($producto->id)) {
                $cupon->productos()->attach($producto->id, [
                    'descuento_tipo' => 'porcentaje',
                    'descuento_valor' => 10,
                ]);
            }

            return $cupon;
        }

        $cupon = Cupon::create([
            'owner_id' => $ownerId,
            'user_id' => $ownerId,
            'codigo' => 'VERIF-'.time(),
            'tipo' => 'vale_producto',
            'valor' => 10,
            'descripcion' => 'Cupón de verificación de stock',
            'max_usos' => 100,
            'usos_actuales' => 0,
            'fecha_inicio' => now()->subDay(),
            'fecha_fin' => now()->addMonth(),
            'activa' => true,
        ]);

        $cupon->productos()->attach($producto->id, [
            'descuento_tipo' => 'porcentaje',
            'descuento_valor' => 10,
        ]);

        return $cupon;
    }
}
