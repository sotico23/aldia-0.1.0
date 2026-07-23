<?php

use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Cupon;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->ownerId = $this->user->getOwnerId();
    $this->categoria = Categoria::factory()->create(['owner_id' => $this->ownerId]);
    $this->cliente = Cliente::factory()->create([
        'owner_id' => $this->ownerId,
        'categoria_id' => $this->categoria->id,
    ]);
    $this->almacen = Almacen::factory()->create([
        'user_id' => $this->user->id,
        'owner_id' => $this->ownerId,
    ]);
    $this->producto = Producto::factory()->create([
        'codigo' => 'PROD-CUP-NEG-1',
        'nombre' => 'Producto Cupon Negativo',
        'precio_venta' => 10000,
        'owner_id' => $this->ownerId,
        'categoria_id' => $this->categoria->id,
    ]);
});

test('venta con cupon porcentaje y stock insuficiente se crea correctamente', function () {
    Inventario::create([
        'producto_id' => $this->producto->id,
        'almacen_id' => $this->almacen->id,
        'cantidad' => 2,
        'cantidad_minima' => 1,
    ]);

    $cupon = Cupon::factory()->porcentaje()->create([
        'owner_id' => $this->ownerId,
        'user_id' => $this->user->id,
        'valor' => 10,
        'max_usos' => 10,
        'usos_actuales' => 0,
        'usos_por_cliente' => 10,
        'fecha_inicio' => now()->subMonth(),
        'fecha_fin' => now()->addMonth(),
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('ventas.store'), [
            'numero_factura' => 'FV-CUP-NEG-001',
            'cliente_id' => $this->cliente->id,
            'fecha' => '2026-07-22',
            'estado' => 'pagada',
            'incluye_iva' => false,
            'cupon_codigo' => $cupon->codigo,
            'productos' => [
                [
                    'producto_id' => $this->producto->id,
                    'cantidad' => 10,
                    'precio_unitario' => 10000,
                ],
            ],
            'almacen_ids' => [$this->almacen->id],
        ]);

    $response->assertSessionDoesntHaveErrors();
    $response->assertStatus(302);

    $this->assertDatabaseHas('ventas', [
        'numero' => 'FV-CUP-NEG-001',
        'cupon_id' => $cupon->id,
    ]);

    $inventario = Inventario::where('producto_id', $this->producto->id)
        ->where('almacen_id', $this->almacen->id)
        ->first();

    expect((float) $inventario->cantidad)->toBe(-8.0);

    $cupon->refresh();
    expect($cupon->usos_actuales)->toBe(1);
});

test('venta con cupon precio fijo y stock negativo aplica descuento correcto', function () {
    Inventario::create([
        'producto_id' => $this->producto->id,
        'almacen_id' => $this->almacen->id,
        'cantidad' => 1,
        'cantidad_minima' => 1,
    ]);

    $cupon = Cupon::factory()->create([
        'owner_id' => $this->ownerId,
        'user_id' => $this->user->id,
        'tipo' => 'precio_fijo',
        'valor' => 5000,
        'max_usos' => 5,
        'usos_actuales' => 0,
        'usos_por_cliente' => 10,
        'fecha_inicio' => now()->subMonth(),
        'fecha_fin' => now()->addMonth(),
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('ventas.store'), [
            'numero_factura' => 'FV-CUP-NEG-002',
            'cliente_id' => $this->cliente->id,
            'fecha' => '2026-07-22',
            'estado' => 'pagada',
            'incluye_iva' => false,
            'cupon_codigo' => $cupon->codigo,
            'productos' => [
                [
                    'producto_id' => $this->producto->id,
                    'cantidad' => 5,
                    'precio_unitario' => 10000,
                ],
            ],
            'almacen_ids' => [$this->almacen->id],
        ]);

    $response->assertSessionDoesntHaveErrors();

    $this->assertDatabaseHas('ventas', [
        'numero' => 'FV-CUP-NEG-002',
        'monto_descuento_cupon' => 5000.0,
    ]);

    $inventario = Inventario::where('producto_id', $this->producto->id)
        ->where('almacen_id', $this->almacen->id)
        ->first();

    expect((float) $inventario->cantidad)->toBe(-4.0);
});

test('cupon no se canjea si la venta falla por otro motivo', function () {
    Inventario::create([
        'producto_id' => $this->producto->id,
        'almacen_id' => $this->almacen->id,
        'cantidad' => 5,
        'cantidad_minima' => 1,
    ]);

    $cupon = Cupon::factory()->porcentaje()->create([
        'owner_id' => $this->ownerId,
        'user_id' => $this->user->id,
        'valor' => 20,
        'max_usos' => 1,
        'usos_actuales' => 0,
        'usos_por_cliente' => 10,
        'fecha_inicio' => now()->subMonth(),
        'fecha_fin' => now()->addMonth(),
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('ventas.store'), [
            'numero_factura' => 'FV-CUP-NEG-003',
            'cliente_id' => $this->cliente->id,
            'fecha' => '2026-07-22',
            'estado' => 'pagada',
            'incluye_iva' => false,
            'cupon_codigo' => $cupon->codigo,
            'productos' => [],
            'almacen_ids' => [$this->almacen->id],
        ]);

    $response->assertSessionHasErrors('productos');

    $cupon->refresh();
    expect($cupon->usos_actuales)->toBe(0);
});

test('multiples productos con cupon y stock negativo calcula descuento correctamente', function () {
    $producto2 = Producto::factory()->create([
        'codigo' => 'PROD-CUP-NEG-2',
        'nombre' => 'Segundo Producto Cupon',
        'precio_venta' => 5000,
        'owner_id' => $this->ownerId,
        'categoria_id' => $this->categoria->id,
    ]);

    Inventario::create([
        'producto_id' => $this->producto->id,
        'almacen_id' => $this->almacen->id,
        'cantidad' => 1,
        'cantidad_minima' => 1,
    ]);

    Inventario::create([
        'producto_id' => $producto2->id,
        'almacen_id' => $this->almacen->id,
        'cantidad' => 0,
        'cantidad_minima' => 1,
    ]);

    $cupon = Cupon::factory()->porcentaje()->create([
        'owner_id' => $this->ownerId,
        'user_id' => $this->user->id,
        'valor' => 15,
        'max_usos' => 10,
        'usos_actuales' => 0,
        'usos_por_cliente' => 10,
        'fecha_inicio' => now()->subMonth(),
        'fecha_fin' => now()->addMonth(),
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('ventas.store'), [
            'numero_factura' => 'FV-CUP-NEG-004',
            'cliente_id' => $this->cliente->id,
            'fecha' => '2026-07-22',
            'estado' => 'pagada',
            'incluye_iva' => false,
            'cupon_codigo' => $cupon->codigo,
            'productos' => [
                [
                    'producto_id' => $this->producto->id,
                    'cantidad' => 5,
                    'precio_unitario' => 10000,
                ],
                [
                    'producto_id' => $producto2->id,
                    'cantidad' => 10,
                    'precio_unitario' => 5000,
                ],
            ],
            'almacen_ids' => [$this->almacen->id],
        ]);

    $response->assertSessionDoesntHaveErrors();

    $inv1 = Inventario::where('producto_id', $this->producto->id)
        ->where('almacen_id', $this->almacen->id)
        ->first();
    $inv2 = Inventario::where('producto_id', $producto2->id)
        ->where('almacen_id', $this->almacen->id)
        ->first();

    expect((float) $inv1->cantidad)->toBe(-4.0);
    expect((float) $inv2->cantidad)->toBe(-10.0);

    $this->assertDatabaseHas('ventas', [
        'numero' => 'FV-CUP-NEG-004',
        'cupon_id' => $cupon->id,
    ]);
});
