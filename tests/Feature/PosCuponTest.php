<?php

use App\Models\Almacen;
use App\Models\Cliente;
use App\Models\Cupon;
use App\Models\CuponUso;
use App\Models\Inventario;
use App\Models\InventoryMovement;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use Spatie\Permission\Models\Permission;

function givePosPermissions(User $user): void
{
    foreach (['ventas.pos.viewAny', 'ventas.pos.create'] as $perm) {
        $user->givePermissionTo(Permission::firstOrCreate(['name' => $perm]));
    }
}

function giveVentaPermissions(User $user): void
{
    foreach (['ventas.ventas.viewAny', 'ventas.ventas.create'] as $perm) {
        $user->givePermissionTo(Permission::firstOrCreate(['name' => $perm]));
    }
}

beforeEach(function () {
    $this->ivaRate = config('taxes.iva_rate', 0.19);
});

function createPosUser(): User
{
    $user = User::factory()->create();
    $user->syncRoles([]);
    givePosPermissions($user);

    return $user;
}

function createVentaUser(): User
{
    $user = User::factory()->create();
    $user->syncRoles([]);
    giveVentaPermissions($user);

    return $user;
}

// ============================================================================
// POS without coupon
// ============================================================================

test('pos store without coupon creates venta and detalle', function () {
    $user = createPosUser();
    $ownerId = $user->id;

    $cliente = Cliente::factory()->create(['owner_id' => $ownerId]);
    $almacen = Almacen::factory()->create(['owner_id' => $ownerId]);
    $producto = Producto::factory()->create([
        'owner_id' => $ownerId,
        'precio_venta' => 10000,
        'categoria_id' => null,
    ]);
    Inventario::factory()->create([
        'producto_id' => $producto->id,
        'almacen_id' => $almacen->id,
        'cantidad' => 100,
        'owner_id' => $ownerId,
    ]);

    $subtotal = 10000;
    $iva = round($subtotal * $this->ivaRate);
    $total = $subtotal + $iva;

    $this->actingAs($user)
        ->post(route('pos.store'), [
            'cliente_id' => $cliente->id,
            'metodo_pago' => 'efectivo',
            'tipo_documento' => 'boleta',
            'items' => [
                ['producto_id' => $producto->id, 'cantidad' => 1, 'precio' => 10000],
            ],
            'subtotal' => $subtotal,
            'iva' => $iva,
            'total' => $total,
            'almacen_id' => $almacen->id,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(Venta::where('es_pos', true)->count())->toBe(1);
    $venta = Venta::where('es_pos', true)->first();
    expect($venta->cupon_id)->toBeNull();
    expect($venta->monto_descuento_cupon)->toBeNull();
    expect($venta->detalleVentas()->count())->toBe(1);
});

test('pos store creates inventory movement record', function () {
    $user = createPosUser();
    $ownerId = $user->id;

    $cliente = Cliente::factory()->create(['owner_id' => $ownerId]);
    $almacen = Almacen::factory()->create(['owner_id' => $ownerId]);
    $producto = Producto::factory()->create([
        'owner_id' => $ownerId,
        'precio_venta' => 10000,
        'categoria_id' => null,
    ]);
    Inventario::factory()->create([
        'producto_id' => $producto->id,
        'almacen_id' => $almacen->id,
        'cantidad' => 50,
        'owner_id' => $ownerId,
    ]);

    $subtotal = 10000;
    $iva = round($subtotal * $this->ivaRate);
    $total = $subtotal + $iva;

    $this->actingAs($user)
        ->post(route('pos.store'), [
            'cliente_id' => $cliente->id,
            'metodo_pago' => 'efectivo',
            'tipo_documento' => 'boleta',
            'items' => [
                ['producto_id' => $producto->id, 'cantidad' => 1, 'precio' => 10000],
            ],
            'subtotal' => $subtotal,
            'iva' => $iva,
            'total' => $total,
            'almacen_id' => $almacen->id,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $venta = Venta::where('es_pos', true)->latest()->first();

    $movement = InventoryMovement::withoutGlobalScopes()
        ->where('product_id', $producto->id)
        ->where('type', 'EGRESO')
        ->first();

    expect($movement)->not->toBeNull();
    expect((int) $movement->quantity)->toBe(1);
    expect((int) $movement->source_warehouse_id)->toBe((int) $almacen->id);
    expect($movement->description)->toContain('Venta POS #'.$venta->id);
    expect((int) $movement->user_id)->toBe((int) $user->id);
});

// ============================================================================
// POS with coupon
// ============================================================================

test('pos store with valid coupon applies discount and tracks usage', function () {
    $user = createPosUser();
    $ownerId = $user->id;

    $cliente = Cliente::factory()->create(['owner_id' => $ownerId]);
    $almacen = Almacen::factory()->create(['owner_id' => $ownerId]);
    $producto = Producto::factory()->create([
        'owner_id' => $ownerId,
        'precio_venta' => 50000,
        'categoria_id' => null,
    ]);
    Inventario::factory()->create([
        'producto_id' => $producto->id,
        'almacen_id' => $almacen->id,
        'cantidad' => 100,
        'owner_id' => $ownerId,
    ]);

    $cupon = Cupon::factory()->create([
        'owner_id' => $ownerId,
        'user_id' => $ownerId,
        'codigo' => 'POS10',
        'tipo' => 'porcentaje',
        'valor' => 10,
        'compra_minima' => 10000,
        'max_usos' => 10,
        'fecha_inicio' => now()->subDay(),
        'fecha_fin' => now()->addMonth(),
        'activa' => true,
    ]);

    $subtotal = 50000;
    $montoCupon = $cupon->calcularDescuento($subtotal);
    $montoDescuentoTotal = (int) round($montoCupon);
    $baseImponible = max(0, $subtotal - $montoDescuentoTotal);
    $iva = round($baseImponible * $this->ivaRate);
    $total = $baseImponible + $iva;

    $this->actingAs($user)
        ->post(route('pos.store'), [
            'cliente_id' => $cliente->id,
            'metodo_pago' => 'tarjeta',
            'tipo_documento' => 'boleta',
            'items' => [
                ['producto_id' => $producto->id, 'cantidad' => 1, 'precio' => 50000],
            ],
            'subtotal' => $subtotal,
            'iva' => $iva,
            'total' => $total,
            'almacen_id' => $almacen->id,
            'cupon_codigo' => 'POS10',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $venta = Venta::where('es_pos', true)->latest()->first();
    expect($venta)->not->toBeNull();
    expect($venta->cupon_id)->toBe($cupon->id);
    expect((float) $venta->monto_descuento_cupon)->toBe($montoCupon);
    expect((int) $venta->monto_descuento)->toBe($montoDescuentoTotal);

    $uso = CuponUso::where('venta_id', $venta->id)->first();
    expect($uso)->not->toBeNull();
    expect($uso->cupon_id)->toBe($cupon->id);
    expect((float) $uso->monto_descuento)->toBe($montoCupon);

    expect($cupon->fresh()->usos_actuales)->toBe(1);
});

test('pos store with invalid coupon code returns error and no venta', function () {
    $user = createPosUser();
    $ownerId = $user->id;

    $cliente = Cliente::factory()->create(['owner_id' => $ownerId]);
    $almacen = Almacen::factory()->create(['owner_id' => $ownerId]);
    $producto = Producto::factory()->create([
        'owner_id' => $ownerId,
        'precio_venta' => 10000,
        'categoria_id' => null,
    ]);
    Inventario::factory()->create([
        'producto_id' => $producto->id,
        'almacen_id' => $almacen->id,
        'cantidad' => 100,
        'owner_id' => $ownerId,
    ]);

    $subtotal = 10000;
    $iva = round($subtotal * $this->ivaRate);
    $total = $subtotal + $iva;

    $this->actingAs($user)
        ->post(route('pos.store'), [
            'cliente_id' => $cliente->id,
            'metodo_pago' => 'efectivo',
            'tipo_documento' => 'boleta',
            'items' => [
                ['producto_id' => $producto->id, 'cantidad' => 1, 'precio' => 10000],
            ],
            'subtotal' => $subtotal,
            'iva' => $iva,
            'total' => $total,
            'almacen_id' => $almacen->id,
            'cupon_codigo' => 'NOEXISTE',
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(Venta::where('es_pos', true)->count())->toBe(0);
});

test('pos store with expired coupon returns error and no venta', function () {
    $user = createPosUser();
    $ownerId = $user->id;

    $cliente = Cliente::factory()->create(['owner_id' => $ownerId]);
    $almacen = Almacen::factory()->create(['owner_id' => $ownerId]);
    $producto = Producto::factory()->create([
        'owner_id' => $ownerId,
        'precio_venta' => 10000,
        'categoria_id' => null,
    ]);
    Inventario::factory()->create([
        'producto_id' => $producto->id,
        'almacen_id' => $almacen->id,
        'cantidad' => 100,
        'owner_id' => $ownerId,
    ]);

    Cupon::factory()->create([
        'owner_id' => $ownerId,
        'user_id' => $ownerId,
        'codigo' => 'VENCIDO',
        'tipo' => 'porcentaje',
        'valor' => 10,
        'fecha_inicio' => now()->subMonths(2),
        'fecha_fin' => now()->subDay(),
        'activa' => true,
    ]);

    $subtotal = 10000;
    $iva = round($subtotal * $this->ivaRate);
    $total = $subtotal + $iva;

    $this->actingAs($user)
        ->post(route('pos.store'), [
            'cliente_id' => $cliente->id,
            'metodo_pago' => 'efectivo',
            'tipo_documento' => 'boleta',
            'items' => [
                ['producto_id' => $producto->id, 'cantidad' => 1, 'precio' => 10000],
            ],
            'subtotal' => $subtotal,
            'iva' => $iva,
            'total' => $total,
            'almacen_id' => $almacen->id,
            'cupon_codigo' => 'VENCIDO',
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(Venta::where('es_pos', true)->count())->toBe(0);
});

// ============================================================================
// POS with coupon + manual discount (stacking)
// ============================================================================

test('pos store with coupon and manual discount stacks both', function () {
    $user = createPosUser();
    $ownerId = $user->id;

    $cliente = Cliente::factory()->create(['owner_id' => $ownerId]);
    $almacen = Almacen::factory()->create(['owner_id' => $ownerId]);
    $producto = Producto::factory()->create([
        'owner_id' => $ownerId,
        'precio_venta' => 50000,
        'categoria_id' => null,
    ]);
    Inventario::factory()->create([
        'producto_id' => $producto->id,
        'almacen_id' => $almacen->id,
        'cantidad' => 100,
        'owner_id' => $ownerId,
    ]);

    $cupon = Cupon::factory()->create([
        'owner_id' => $ownerId,
        'user_id' => $ownerId,
        'codigo' => 'STACK10',
        'tipo' => 'porcentaje',
        'valor' => 10,
        'compra_minima' => null,
        'max_usos' => 10,
        'fecha_inicio' => now()->subDay(),
        'fecha_fin' => now()->addMonth(),
        'activa' => true,
    ]);

    $subtotal = 50000;
    $descuentoManual = 5000;
    $montoCupon = $cupon->calcularDescuento($subtotal);
    $montoDescuentoTotal = (int) round($descuentoManual + $montoCupon);
    $baseImponible = max(0, $subtotal - $montoDescuentoTotal);
    $iva = round($baseImponible * $this->ivaRate);
    $total = $baseImponible + $iva;

    $this->actingAs($user)
        ->post(route('pos.store'), [
            'cliente_id' => $cliente->id,
            'metodo_pago' => 'efectivo',
            'tipo_documento' => 'boleta',
            'items' => [
                ['producto_id' => $producto->id, 'cantidad' => 1, 'precio' => 50000],
            ],
            'subtotal' => $subtotal,
            'iva' => $iva,
            'total' => $total,
            'descuento' => $descuentoManual,
            'almacen_id' => $almacen->id,
            'cupon_codigo' => 'STACK10',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $venta = Venta::where('es_pos', true)->latest()->first();
    expect($venta)->not->toBeNull();
    expect($venta->cupon_id)->toBe($cupon->id);
    expect((float) $venta->monto_descuento_cupon)->toBe($montoCupon);
    expect((int) $venta->monto_descuento)->toBe($montoDescuentoTotal);
});

// ============================================================================
// Venta (backend) with coupon
// ============================================================================

test('venta store with valid coupon applies discount and tracks usage', function () {
    $user = createVentaUser();
    $ownerId = $user->id;

    $cliente = Cliente::factory()->create(['owner_id' => $ownerId]);
    $almacen = Almacen::factory()->create(['owner_id' => $ownerId]);
    $producto = Producto::factory()->create([
        'owner_id' => $ownerId,
        'precio_venta' => 30000,
        'categoria_id' => null,
    ]);
    Inventario::factory()->create([
        'producto_id' => $producto->id,
        'almacen_id' => $almacen->id,
        'cantidad' => 100,
        'owner_id' => $ownerId,
    ]);

    $cupon = Cupon::factory()->create([
        'owner_id' => $ownerId,
        'user_id' => $ownerId,
        'codigo' => 'VENTA20',
        'tipo' => 'porcentaje',
        'valor' => 20,
        'compra_minima' => 5000,
        'max_usos' => 5,
        'fecha_inicio' => now()->subDay(),
        'fecha_fin' => now()->addMonth(),
        'activa' => true,
    ]);

    $montoCupon = $cupon->calcularDescuento(30000);
    $montoDescuentoTotal = (int) round($montoCupon);
    $baseImponible = max(0, 30000 - $montoDescuentoTotal);
    $iva = round($baseImponible * config('taxes.iva_rate'));
    $total = $baseImponible + $iva;

    $this->actingAs($user)
        ->post(route('ventas.store'), [
            'numero_factura' => 'FV-TEST-001',
            'cliente_id' => $cliente->id,
            'fecha' => now()->format('Y-m-d'),
            'estado' => 'pagada',
            'productos' => [
                [
                    'producto_id' => $producto->id,
                    'cantidad' => 1,
                    'precio_unitario' => 30000,
                ],
            ],
            'almacen_ids' => [$almacen->id],
            'tipo_documento' => 'factura',
            'cupon_codigo' => 'VENTA20',
        ])
        ->assertRedirect(route('ventas.index'));

    $venta = Venta::where('numero', 'FV-TEST-001')->first();
    expect($venta)->not->toBeNull();
    expect($venta->cupon_id)->toBe($cupon->id);
    expect((float) $venta->monto_descuento_cupon)->toBe($montoCupon);

    $uso = CuponUso::where('venta_id', $venta->id)->first();
    expect($uso)->not->toBeNull();
    expect($uso->cupon_id)->toBe($cupon->id);
});

test('venta store with invalid coupon returns validation error', function () {
    $user = createVentaUser();
    $ownerId = $user->id;

    $cliente = Cliente::factory()->create(['owner_id' => $ownerId]);
    $almacen = Almacen::factory()->create(['owner_id' => $ownerId]);
    $producto = Producto::factory()->create([
        'owner_id' => $ownerId,
        'precio_venta' => 10000,
        'categoria_id' => null,
    ]);

    $this->actingAs($user)
        ->post(route('ventas.store'), [
            'numero_factura' => 'FV-TEST-002',
            'cliente_id' => $cliente->id,
            'fecha' => now()->format('Y-m-d'),
            'estado' => 'pagada',
            'productos' => [
                [
                    'producto_id' => $producto->id,
                    'cantidad' => 1,
                    'precio_unitario' => 10000,
                ],
            ],
            'almacen_ids' => [$almacen->id],
            'tipo_documento' => 'factura',
            'cupon_codigo' => 'INVALIDO',
        ])
        ->assertSessionHasErrors('cupon_codigo');
});

// ============================================================================
// POS with vale_producto coupon (percentage per product)
// ============================================================================

test('pos store with vale_producto coupon percentage applies per-product discount', function () {
    $user = createPosUser();
    $ownerId = $user->id;

    $cliente = Cliente::factory()->create(['owner_id' => $ownerId]);
    $almacen = Almacen::factory()->create(['owner_id' => $ownerId]);
    $producto = Producto::factory()->create([
        'owner_id' => $ownerId,
        'precio_venta' => 100000,
        'categoria_id' => null,
    ]);
    Inventario::factory()->create([
        'producto_id' => $producto->id,
        'almacen_id' => $almacen->id,
        'cantidad' => 100,
        'owner_id' => $ownerId,
    ]);

    $cupon = Cupon::factory()->create([
        'owner_id' => $ownerId,
        'user_id' => $ownerId,
        'codigo' => 'VALE10PCT',
        'tipo' => 'vale_producto',
        'valor' => 10,
        'max_usos' => 10,
        'fecha_inicio' => now()->subDay(),
        'fecha_fin' => now()->addMonth(),
        'activa' => true,
    ]);
    $cupon->productos()->attach($producto->id, [
        'descuento_tipo' => 'porcentaje',
        'descuento_valor' => 10,
    ]);

    $subtotal = 200000;
    $descuentoCupon = 20000;
    $baseImponible = $subtotal - $descuentoCupon;
    $iva = round($baseImponible * $this->ivaRate);
    $total = $baseImponible + $iva;

    $this->actingAs($user)
        ->post(route('pos.store'), [
            'cliente_id' => $cliente->id,
            'metodo_pago' => 'efectivo',
            'tipo_documento' => 'boleta',
            'items' => [
                ['producto_id' => $producto->id, 'cantidad' => 2, 'precio' => 100000],
            ],
            'subtotal' => $subtotal,
            'iva' => $iva,
            'total' => $total,
            'almacen_id' => $almacen->id,
            'cupon_codigo' => 'VALE10PCT',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $venta = Venta::where('es_pos', true)->latest()->first();
    expect($venta)->not->toBeNull();
    expect($venta->cupon_id)->toBe($cupon->id);
    expect((float) $venta->monto_descuento_cupon)->toBe((float) $descuentoCupon);

    $uso = CuponUso::where('venta_id', $venta->id)->first();
    expect($uso)->not->toBeNull();
    expect((float) $uso->monto_descuento)->toBe((float) $descuentoCupon);

    expect($cupon->fresh()->usos_actuales)->toBe(1);
});

// ============================================================================
// POS with vale_producto coupon (fixed price per product)
// ============================================================================

test('pos store with vale_producto coupon fixed price applies correct discount', function () {
    $user = createPosUser();
    $ownerId = $user->id;

    $cliente = Cliente::factory()->create(['owner_id' => $ownerId]);
    $almacen = Almacen::factory()->create(['owner_id' => $ownerId]);
    $producto = Producto::factory()->create([
        'owner_id' => $ownerId,
        'precio_venta' => 50000,
        'categoria_id' => null,
    ]);
    Inventario::factory()->create([
        'producto_id' => $producto->id,
        'almacen_id' => $almacen->id,
        'cantidad' => 100,
        'owner_id' => $ownerId,
    ]);

    $cupon = Cupon::factory()->create([
        'owner_id' => $ownerId,
        'user_id' => $ownerId,
        'codigo' => 'VALEFIX',
        'tipo' => 'vale_producto',
        'valor' => 0,
        'max_usos' => 10,
        'fecha_inicio' => now()->subDay(),
        'fecha_fin' => now()->addMonth(),
        'activa' => true,
    ]);
    $cupon->productos()->attach($producto->id, [
        'descuento_tipo' => 'precio_fijo',
        'descuento_valor' => 30000,
    ]);

    $subtotal = 100000;
    $descuentoCupon = (50000 - 30000) * 2;
    $baseImponible = $subtotal - $descuentoCupon;
    $iva = round($baseImponible * $this->ivaRate);
    $total = $baseImponible + $iva;

    $this->actingAs($user)
        ->post(route('pos.store'), [
            'cliente_id' => $cliente->id,
            'metodo_pago' => 'tarjeta',
            'tipo_documento' => 'boleta',
            'items' => [
                ['producto_id' => $producto->id, 'cantidad' => 2, 'precio' => 50000],
            ],
            'subtotal' => $subtotal,
            'iva' => $iva,
            'total' => $total,
            'almacen_id' => $almacen->id,
            'cupon_codigo' => 'VALEFIX',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $venta = Venta::where('es_pos', true)->latest()->first();
    expect((float) $venta->monto_descuento_cupon)->toBe((float) $descuentoCupon);
});

// ============================================================================
// POS with exhausted coupon (max_usos reached)
// ============================================================================

test('pos store with exhausted coupon returns error', function () {
    $user = createPosUser();
    $ownerId = $user->id;

    $cliente = Cliente::factory()->create(['owner_id' => $ownerId]);
    $almacen = Almacen::factory()->create(['owner_id' => $ownerId]);
    $producto = Producto::factory()->create([
        'owner_id' => $ownerId,
        'precio_venta' => 10000,
        'categoria_id' => null,
    ]);
    Inventario::factory()->create([
        'producto_id' => $producto->id,
        'almacen_id' => $almacen->id,
        'cantidad' => 100,
        'owner_id' => $ownerId,
    ]);

    Cupon::factory()->create([
        'owner_id' => $ownerId,
        'user_id' => $ownerId,
        'codigo' => 'AGOTADO',
        'tipo' => 'porcentaje',
        'valor' => 10,
        'max_usos' => 2,
        'usos_actuales' => 2,
        'fecha_inicio' => now()->subDay(),
        'fecha_fin' => now()->addMonth(),
        'activa' => true,
    ]);

    $subtotal = 10000;
    $iva = round($subtotal * $this->ivaRate);
    $total = $subtotal + $iva;

    $this->actingAs($user)
        ->post(route('pos.store'), [
            'cliente_id' => $cliente->id,
            'metodo_pago' => 'efectivo',
            'tipo_documento' => 'boleta',
            'items' => [
                ['producto_id' => $producto->id, 'cantidad' => 1, 'precio' => 10000],
            ],
            'subtotal' => $subtotal,
            'iva' => $iva,
            'total' => $total,
            'almacen_id' => $almacen->id,
            'cupon_codigo' => 'AGOTADO',
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(Venta::where('es_pos', true)->count())->toBe(0);
});

// ============================================================================
// POS with coupon below compra_minima
// ============================================================================

test('pos store with coupon below compra_minima returns error', function () {
    $user = createPosUser();
    $ownerId = $user->id;

    $cliente = Cliente::factory()->create(['owner_id' => $ownerId]);
    $almacen = Almacen::factory()->create(['owner_id' => $ownerId]);
    $producto = Producto::factory()->create([
        'owner_id' => $ownerId,
        'precio_venta' => 5000,
        'categoria_id' => null,
    ]);
    Inventario::factory()->create([
        'producto_id' => $producto->id,
        'almacen_id' => $almacen->id,
        'cantidad' => 100,
        'owner_id' => $ownerId,
    ]);

    Cupon::factory()->create([
        'owner_id' => $ownerId,
        'user_id' => $ownerId,
        'codigo' => 'MINIMO20K',
        'tipo' => 'porcentaje',
        'valor' => 15,
        'compra_minima' => 20000,
        'max_usos' => 10,
        'fecha_inicio' => now()->subDay(),
        'fecha_fin' => now()->addMonth(),
        'activa' => true,
    ]);

    $subtotal = 5000;
    $iva = round($subtotal * $this->ivaRate);
    $total = $subtotal + $iva;

    $this->actingAs($user)
        ->post(route('pos.store'), [
            'cliente_id' => $cliente->id,
            'metodo_pago' => 'efectivo',
            'tipo_documento' => 'boleta',
            'items' => [
                ['producto_id' => $producto->id, 'cantidad' => 1, 'precio' => 5000],
            ],
            'subtotal' => $subtotal,
            'iva' => $iva,
            'total' => $total,
            'almacen_id' => $almacen->id,
            'cupon_codigo' => 'MINIMO20K',
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(Venta::where('es_pos', true)->count())->toBe(0);
});

// ============================================================================
// POS server-side total recalculation
// ============================================================================

test('pos store recalculates total server-side ignoring client values', function () {
    $user = createPosUser();
    $ownerId = $user->id;

    $cliente = Cliente::factory()->create(['owner_id' => $ownerId]);
    $almacen = Almacen::factory()->create(['owner_id' => $ownerId]);
    $producto = Producto::factory()->create([
        'owner_id' => $ownerId,
        'precio_venta' => 50000,
        'categoria_id' => null,
    ]);
    Inventario::factory()->create([
        'producto_id' => $producto->id,
        'almacen_id' => $almacen->id,
        'cantidad' => 100,
        'owner_id' => $ownerId,
    ]);

    $cupon = Cupon::factory()->create([
        'owner_id' => $ownerId,
        'user_id' => $ownerId,
        'codigo' => 'SERVERCALC',
        'tipo' => 'porcentaje',
        'valor' => 10,
        'max_usos' => 10,
        'fecha_inicio' => now()->subDay(),
        'fecha_fin' => now()->addMonth(),
        'activa' => true,
    ]);

    $realSubtotal = 50000;
    $descuentoCupon = 5000;
    $baseImponible = $realSubtotal - $descuentoCupon;
    $iva = round($baseImponible * $this->ivaRate);
    $realTotal = $baseImponible + $iva;

    $this->actingAs($user)
        ->post(route('pos.store'), [
            'cliente_id' => $cliente->id,
            'metodo_pago' => 'efectivo',
            'tipo_documento' => 'boleta',
            'items' => [
                ['producto_id' => $producto->id, 'cantidad' => 1, 'precio' => 50000],
            ],
            'subtotal' => 1,
            'iva' => 0,
            'total' => 1,
            'almacen_id' => $almacen->id,
            'cupon_codigo' => 'SERVERCALC',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $venta = Venta::where('es_pos', true)->latest()->first();
    expect((int) $venta->subtotal)->toBe($realSubtotal);
    expect((float) $venta->monto_descuento_cupon)->toBe((float) $descuentoCupon);
});

// ============================================================================
// Stock verification after POS sale with coupon
// ============================================================================

test('pos store with coupon deducts correct stock', function () {
    $user = createPosUser();
    $ownerId = $user->id;

    $cliente = Cliente::factory()->create(['owner_id' => $ownerId]);
    $almacen = Almacen::factory()->create(['owner_id' => $ownerId]);
    $producto = Producto::factory()->create([
        'owner_id' => $ownerId,
        'precio_venta' => 25000,
        'categoria_id' => null,
    ]);
    Inventario::factory()->create([
        'producto_id' => $producto->id,
        'almacen_id' => $almacen->id,
        'cantidad' => 50,
        'owner_id' => $ownerId,
    ]);

    Cupon::factory()->create([
        'owner_id' => $ownerId,
        'user_id' => $ownerId,
        'codigo' => 'STOCK10',
        'tipo' => 'porcentaje',
        'valor' => 10,
        'max_usos' => 10,
        'fecha_inicio' => now()->subDay(),
        'fecha_fin' => now()->addMonth(),
        'activa' => true,
    ]);

    $subtotal = 75000;
    $descuentoCupon = 7500;
    $baseImponible = $subtotal - $descuentoCupon;
    $iva = round($baseImponible * $this->ivaRate);
    $total = $baseImponible + $iva;

    $this->actingAs($user)
        ->post(route('pos.store'), [
            'cliente_id' => $cliente->id,
            'metodo_pago' => 'efectivo',
            'tipo_documento' => 'boleta',
            'items' => [
                ['producto_id' => $producto->id, 'cantidad' => 3, 'precio' => 25000],
            ],
            'subtotal' => $subtotal,
            'iva' => $iva,
            'total' => $total,
            'almacen_id' => $almacen->id,
            'cupon_codigo' => 'STOCK10',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $inventario = Inventario::where('producto_id', $producto->id)
        ->where('almacen_id', $almacen->id)
        ->first();

    expect((float) $inventario->cantidad)->toBe(47.0);
});

// ============================================================================
// Coupon atomic canjear() - concurrent over-use prevention
// ============================================================================

test('canjear atomic update prevents over-use when max_usos is reached', function () {
    $user = createPosUser();
    $ownerId = $user->id;

    $cupon = Cupon::factory()->create([
        'owner_id' => $ownerId,
        'user_id' => $ownerId,
        'codigo' => 'ATOMIC1',
        'tipo' => 'porcentaje',
        'valor' => 10,
        'max_usos' => 3,
        'usos_actuales' => 2,
        'fecha_inicio' => now()->subDay(),
        'fecha_fin' => now()->addMonth(),
        'activa' => true,
    ]);

    expect($cupon->canjear())->toBeTrue();
    expect($cupon->fresh()->usos_actuales)->toBe(3);

    expect($cupon->canjear())->toBeFalse();
    expect($cupon->fresh()->usos_actuales)->toBe(3);
});

test('canjear respects usos_por_cliente limit via validar', function () {
    $user = createPosUser();
    $ownerId = $user->id;

    $cliente = Cliente::factory()->create(['owner_id' => $ownerId]);

    $cupon = Cupon::factory()->create([
        'owner_id' => $ownerId,
        'user_id' => $ownerId,
        'codigo' => 'CLIENTLIM',
        'tipo' => 'porcentaje',
        'valor' => 10,
        'max_usos' => 100,
        'usos_por_cliente' => 2,
        'usos_actuales' => 0,
        'fecha_inicio' => now()->subDay(),
        'fecha_fin' => now()->addMonth(),
        'activa' => true,
    ]);

    expect($cupon->validar(10000, $user->id))->toBeTrue();

    $venta1 = Venta::create([
        'owner_id' => $ownerId,
        'user_id' => $user->id,
        'cliente_id' => $cliente->id,
        'numero' => 'TEST-CLI-1',
        'fecha' => now(),
        'subtotal' => 10000,
        'iva' => 0,
        'total' => 10000,
        'metodo_pago' => 'efectivo',
        'tipo_documento' => 'boleta',
        'estado' => 'pagada',
        'es_pos' => true,
    ]);
    $venta2 = Venta::create([
        'owner_id' => $ownerId,
        'user_id' => $user->id,
        'cliente_id' => $cliente->id,
        'numero' => 'TEST-CLI-2',
        'fecha' => now(),
        'subtotal' => 10000,
        'iva' => 0,
        'total' => 10000,
        'metodo_pago' => 'efectivo',
        'tipo_documento' => 'boleta',
        'estado' => 'pagada',
        'es_pos' => true,
    ]);

    CuponUso::create([
        'cupon_id' => $cupon->id,
        'venta_id' => $venta1->id,
        'user_id' => $user->id,
        'monto_total' => 10000,
        'monto_descuento' => 1000,
    ]);
    CuponUso::create([
        'cupon_id' => $cupon->id,
        'venta_id' => $venta2->id,
        'user_id' => $user->id,
        'monto_total' => 10000,
        'monto_descuento' => 1000,
    ]);

    expect($cupon->validar(10000, $user->id))->toBeFalse();

    $otherUser = createPosUser();
    expect($cupon->validar(10000, $otherUser->id))->toBeTrue();
});

test('validar rejects below compra_minima', function () {
    $cupon = Cupon::factory()->create([
        'tipo' => 'porcentaje',
        'valor' => 10,
        'compra_minima' => 50000,
        'max_usos' => 10,
        'fecha_inicio' => now()->subDay(),
        'fecha_fin' => now()->addMonth(),
        'activa' => true,
    ]);

    expect($cupon->validar(30000))->toBeFalse();
    expect($cupon->validar(50000))->toBeTrue();
    expect($cupon->validar(100000))->toBeTrue();
});
