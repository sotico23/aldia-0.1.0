<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->categoria = Categoria::factory()->create([
        'owner_id' => $this->user->getOwnerId(),
        'nombre' => 'Cat Stock Gramos',
    ]);
    $this->cliente = Cliente::factory()->create([
        'email' => 'stock-gramos@test.com',
        'owner_id' => $this->user->getOwnerId(),
        'categoria_id' => $this->categoria->id,
    ]);
    $this->producto = Producto::factory()->create([
        'codigo' => 'PROD-STOCK-GRAMOS',
        'nombre' => 'Producto Stock Grameable',
        'precio_venta' => 2000,
        'unidad_medida' => 'kg',
        'owner_id' => $this->user->getOwnerId(),
        'categoria_id' => $this->categoria->id,
    ]);
    $this->almacen = Almacen::factory()->create([
        'user_id' => $this->user->id,
        'owner_id' => $this->user->getOwnerId(),
    ]);
    $this->inventario = Inventario::factory()->create([
        'producto_id' => $this->producto->id,
        'almacen_id' => $this->almacen->id,
        'owner_id' => $this->user->getOwnerId(),
        'cantidad' => 100,
        'cantidad_minima' => 0,
    ]);
});

test('deducts decimal stock when venta is pagada with gramos quantity', function () {
    $response = $this->actingAs($this->user)
        ->post(route('ventas.store'), [
            'numero_factura' => 'FV-STOCK-DEC-001',
            'cliente_id' => $this->cliente->id,
            'fecha' => '2026-07-04',
            'estado' => 'pagada',
            'incluye_iva' => false,
            'productos' => [
                [
                    'producto_id' => $this->producto->id,
                    'cantidad' => 0.500,
                    'precio_unitario' => 2000,
                ],
            ],
            'almacen_ids' => [$this->almacen->id],
        ]);

    $response->assertSessionDoesntHaveErrors();

    $this->inventario->refresh();
    expect((float) $this->inventario->cantidad)->toBe(99.500);
});

test('deducts exact gram integer stock correctly', function () {
    $this->inventario->update(['cantidad' => 50]);

    $response = $this->actingAs($this->user)
        ->post(route('ventas.store'), [
            'numero_factura' => 'FV-STOCK-GRAM-001',
            'cliente_id' => $this->cliente->id,
            'fecha' => '2026-07-04',
            'estado' => 'pagada',
            'incluye_iva' => false,
            'productos' => [
                [
                    'producto_id' => $this->producto->id,
                    'cantidad' => 0.001,
                    'precio_unitario' => 2000,
                ],
            ],
            'almacen_ids' => [$this->almacen->id],
        ]);

    $response->assertSessionDoesntHaveErrors();

    $this->inventario->refresh();
    expect((float) $this->inventario->cantidad)->toBe(49.999);
});

test('deducts quantity with 3 decimal precision from stock', function () {
    $this->inventario->update(['cantidad' => 10]);

    $response = $this->actingAs($this->user)
        ->post(route('ventas.store'), [
            'numero_factura' => 'FV-STOCK-PREC-001',
            'cliente_id' => $this->cliente->id,
            'fecha' => '2026-07-04',
            'estado' => 'pagada',
            'incluye_iva' => false,
            'productos' => [
                [
                    'producto_id' => $this->producto->id,
                    'cantidad' => 0.333,
                    'precio_unitario' => 3000,
                ],
            ],
            'almacen_ids' => [$this->almacen->id],
        ]);

    // Debug: check response status
    $response->dump();

    $response->assertSessionDoesntHaveErrors();

    $this->inventario->refresh();
    // 10 - 0.333 = 9.667
    expect((float) $this->inventario->cantidad)->toBe(9.667);
});

test('deducts multiple items stock correctly', function () {
    $producto2 = Producto::factory()->create([
        'codigo' => 'PROD-STOCK-DOS',
        'nombre' => 'Segundo Stock',
        'precio_venta' => 1000,
        'unidad_medida' => 'lt',
        'owner_id' => $this->user->getOwnerId(),
        'categoria_id' => $this->categoria->id,
    ]);
    $inventario2 = Inventario::factory()->create([
        'producto_id' => $producto2->id,
        'almacen_id' => $this->almacen->id,
        'owner_id' => $this->user->getOwnerId(),
        'cantidad' => 50,
        'cantidad_minima' => 0,
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('ventas.store'), [
            'numero_factura' => 'FV-STOCK-MULTI-001',
            'cliente_id' => $this->cliente->id,
            'fecha' => '2026-07-04',
            'estado' => 'pagada',
            'incluye_iva' => false,
            'productos' => [
                [
                    'producto_id' => $this->producto->id,
                    'cantidad' => 0.500,
                    'precio_unitario' => 2000,
                ],
                [
                    'producto_id' => $producto2->id,
                    'cantidad' => 0.750,
                    'precio_unitario' => 1000,
                ],
            ],
            'almacen_ids' => [$this->almacen->id],
        ]);

    $response->assertSessionDoesntHaveErrors();

    $this->inventario->refresh();
    $inventario2->refresh();
    expect((float) $this->inventario->cantidad)->toBe(99.500);
    expect((float) $inventario2->cantidad)->toBe(49.250);
});
