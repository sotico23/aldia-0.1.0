<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->categoria = Categoria::factory()->create([
        'owner_id' => $this->user->getOwnerId(),
        'nombre' => 'Cat Venta Gramos',
    ]);
    $this->cliente = Cliente::factory()->create([
        'email' => 'venta-gramos@test.com',
        'owner_id' => $this->user->getOwnerId(),
        'categoria_id' => $this->categoria->id,
    ]);
    $this->productoKg = Producto::factory()->create([
        'codigo' => 'PROD-KG-GRAMOS',
        'nombre' => 'Producto por Kilo',
        'precio_venta' => 2000,
        'unidad_medida' => 'kg',
        'owner_id' => $this->user->getOwnerId(),
        'categoria_id' => $this->categoria->id,
    ]);
    $this->productoLt = Producto::factory()->create([
        'codigo' => 'PROD-LT-GRAMOS',
        'nombre' => 'Producto por Litro',
        'precio_venta' => 1500,
        'unidad_medida' => 'lt',
        'owner_id' => $this->user->getOwnerId(),
        'categoria_id' => $this->categoria->id,
    ]);
    $this->almacen = Almacen::factory()->create([
        'user_id' => $this->user->id,
        'owner_id' => $this->user->getOwnerId(),
    ]);
});

test('store accepts decimal quantity in kg', function () {
    $response = $this->actingAs($this->user)
        ->post(route('ventas.store'), [
            'numero_factura' => 'FV-KG-DEC-001',
            'cliente_id' => $this->cliente->id,
            'fecha' => '2026-07-04',
            'estado' => 'pendiente',
            'incluye_iva' => false,
            'productos' => [
                [
                    'producto_id' => $this->productoKg->id,
                    'cantidad' => 0.500,
                    'precio_unitario' => 2000,
                ],
            ],
            'almacen_ids' => [$this->almacen->id],
        ]);

    $response->assertSessionDoesntHaveErrors();
    $response->assertStatus(302);

    $this->assertDatabaseHas('detalle_ventas', [
        'producto_id' => $this->productoKg->id,
        'cantidad' => 0.500,
    ]);
});

test('store accepts decimal quantity in liters', function () {
    $response = $this->actingAs($this->user)
        ->post(route('ventas.store'), [
            'numero_factura' => 'FV-LT-DEC-001',
            'cliente_id' => $this->cliente->id,
            'fecha' => '2026-07-04',
            'estado' => 'pendiente',
            'incluye_iva' => false,
            'productos' => [
                [
                    'producto_id' => $this->productoLt->id,
                    'cantidad' => 0.250,
                    'precio_unitario' => 1500,
                ],
            ],
            'almacen_ids' => [$this->almacen->id],
        ]);

    $response->assertSessionDoesntHaveErrors();
    $response->assertStatus(302);

    $this->assertDatabaseHas('detalle_ventas', [
        'producto_id' => $this->productoLt->id,
        'cantidad' => 0.250,
    ]);
});

test('store accepts integer quantity for non-grameable products', function () {
    $response = $this->actingAs($this->user)
        ->post(route('ventas.store'), [
            'numero_factura' => 'FV-INT-001',
            'cliente_id' => $this->cliente->id,
            'fecha' => '2026-07-04',
            'estado' => 'pendiente',
            'incluye_iva' => false,
            'productos' => [
                [
                    'producto_id' => $this->productoKg->id,
                    'cantidad' => 3,
                    'precio_unitario' => 2000,
                ],
            ],
            'almacen_ids' => [$this->almacen->id],
        ]);

    $response->assertSessionDoesntHaveErrors();
    $response->assertStatus(302);

    $this->assertDatabaseHas('detalle_ventas', [
        'producto_id' => $this->productoKg->id,
        'cantidad' => 3,
    ]);
});

test('store rejects quantity below 0.001', function () {
    $response = $this->actingAs($this->user)
        ->post(route('ventas.store'), [
            'numero_factura' => 'FV-MIN-001',
            'cliente_id' => $this->cliente->id,
            'fecha' => '2026-07-04',
            'estado' => 'pendiente',
            'incluye_iva' => false,
            'productos' => [
                [
                    'producto_id' => $this->productoKg->id,
                    'cantidad' => 0.0001,
                    'precio_unitario' => 2000,
                ],
            ],
            'almacen_ids' => [$this->almacen->id],
        ]);

    $response->assertStatus(302);
    $response->assertSessionHasErrors(['productos.0.cantidad']);
});

test('store rejects negative quantity', function () {
    $response = $this->actingAs($this->user)
        ->post(route('ventas.store'), [
            'numero_factura' => 'FV-NEG-001',
            'cliente_id' => $this->cliente->id,
            'fecha' => '2026-07-04',
            'estado' => 'pendiente',
            'incluye_iva' => false,
            'productos' => [
                [
                    'producto_id' => $this->productoKg->id,
                    'cantidad' => -1,
                    'precio_unitario' => 2000,
                ],
            ],
            'almacen_ids' => [$this->almacen->id],
        ]);

    $response->assertStatus(302);
    $response->assertSessionHasErrors(['productos.0.cantidad']);
});

test('store calculates subtotal correctly with decimal quantity', function () {
    $response = $this->actingAs($this->user)
        ->post(route('ventas.store'), [
            'numero_factura' => 'FV-SUB-001',
            'cliente_id' => $this->cliente->id,
            'fecha' => '2026-07-04',
            'estado' => 'pendiente',
            'incluye_iva' => false,
            'productos' => [
                [
                    'producto_id' => $this->productoKg->id,
                    'cantidad' => 0.500,
                    'precio_unitario' => 2000,
                ],
            ],
            'almacen_ids' => [$this->almacen->id],
        ]);

    $response->assertSessionDoesntHaveErrors();
    $response->assertStatus(302);

    $venta = Venta::where('numero', 'FV-SUB-001')->first();
    expect($venta)->not->toBeNull();
    expect((int) $venta->subtotal)->toBe(1000);
    expect((int) $venta->total)->toBe(1000);
});

test('store calculates subtotal with 3 decimal places precision', function () {
    $response = $this->actingAs($this->user)
        ->post(route('ventas.store'), [
            'numero_factura' => 'FV-PREC-001',
            'cliente_id' => $this->cliente->id,
            'fecha' => '2026-07-04',
            'estado' => 'pendiente',
            'incluye_iva' => false,
            'productos' => [
                [
                    'producto_id' => $this->productoKg->id,
                    'cantidad' => 0.333,
                    'precio_unitario' => 3000,
                ],
            ],
            'almacen_ids' => [$this->almacen->id],
        ]);

    $response->assertSessionDoesntHaveErrors();
    $response->assertStatus(302);

    $venta = Venta::where('numero', 'FV-PREC-001')->first();
    expect($venta)->not->toBeNull();
    // 0.333 * 3000 = 999 -> rounded to integer
    expect((int) $venta->subtotal)->toBe(999);
});

test('update accepts decimal quantity', function () {
    $venta = Venta::factory()->create([
        'numero' => 'FV-UPD-DEC-001',
        'cliente_id' => $this->cliente->id,
        'owner_id' => $this->user->getOwnerId(),
        'almacen_id' => $this->almacen->id,
    ]);

    $response = $this->actingAs($this->user)
        ->put(route('ventas.update', $venta), [
            'productos' => [
                [
                    'producto_id' => $this->productoKg->id,
                    'cantidad' => 0.750,
                    'precio_unitario' => 2000,
                ],
            ],
        ]);

    $response->assertSessionDoesntHaveErrors();
    $response->assertStatus(302);

    $this->assertDatabaseHas('detalle_ventas', [
        'venta_id' => $venta->id,
        'producto_id' => $this->productoKg->id,
        'cantidad' => 0.750,
    ]);
});
