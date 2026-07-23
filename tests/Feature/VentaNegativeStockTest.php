<?php

use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Inventario;
use App\Models\InventoryMovement;
use App\Models\Producto;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->ownerId = $this->user->getOwnerId();
    $this->categoria = Categoria::factory()->create([
        'owner_id' => $this->ownerId,
    ]);
    $this->cliente = Cliente::factory()->create([
        'owner_id' => $this->ownerId,
        'categoria_id' => $this->categoria->id,
    ]);
    $this->almacen = Almacen::factory()->create([
        'user_id' => $this->user->id,
        'owner_id' => $this->ownerId,
    ]);
    $this->producto = Producto::factory()->create([
        'codigo' => 'PROD-NEG-STOCK',
        'nombre' => 'Producto Test Negativo',
        'precio_venta' => 5000,
        'owner_id' => $this->ownerId,
        'categoria_id' => $this->categoria->id,
    ]);
});

test('venta se crea exitosamente cuando no hay stock suficiente', function () {
    Inventario::create([
        'producto_id' => $this->producto->id,
        'almacen_id' => $this->almacen->id,
        'cantidad' => 2,
        'cantidad_minima' => 5,
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('ventas.store'), [
            'numero_factura' => 'FV-NEG-001',
            'cliente_id' => $this->cliente->id,
            'fecha' => '2026-07-22',
            'estado' => 'pendiente',
            'incluye_iva' => false,
            'productos' => [
                [
                    'producto_id' => $this->producto->id,
                    'cantidad' => 10,
                    'precio_unitario' => 5000,
                ],
            ],
            'almacen_ids' => [$this->almacen->id],
        ]);

    $response->assertSessionDoesntHaveErrors();
    $response->assertStatus(302);

    $this->assertDatabaseHas('ventas', [
        'numero' => 'FV-NEG-001',
        'estado' => 'pendiente',
    ]);

    $this->assertDatabaseHas('detalle_ventas', [
        'producto_id' => $this->producto->id,
        'cantidad' => 10,
    ]);
});

test('stock queda negativo al crear venta sin inventario suficiente', function () {
    Inventario::create([
        'producto_id' => $this->producto->id,
        'almacen_id' => $this->almacen->id,
        'cantidad' => 3,
        'cantidad_minima' => 1,
    ]);

    $this->actingAs($this->user)
        ->post(route('ventas.store'), [
            'numero_factura' => 'FV-NEG-002',
            'cliente_id' => $this->cliente->id,
            'fecha' => '2026-07-22',
            'estado' => 'pagada',
            'incluye_iva' => false,
            'productos' => [
                [
                    'producto_id' => $this->producto->id,
                    'cantidad' => 10,
                    'precio_unitario' => 5000,
                ],
            ],
            'almacen_ids' => [$this->almacen->id],
        ]);

    $inventario = Inventario::where('producto_id', $this->producto->id)
        ->where('almacen_id', $this->almacen->id)
        ->first();

    expect($inventario)->not->toBeNull();
    expect((float) $inventario->cantidad)->toBe(-7.0);
});

test('stock queda negativo cuando no existe registro de inventario previo', function () {
    $this->actingAs($this->user)
        ->post(route('ventas.store'), [
            'numero_factura' => 'FV-NEG-003',
            'cliente_id' => $this->cliente->id,
            'fecha' => '2026-07-22',
            'estado' => 'pagada',
            'incluye_iva' => false,
            'productos' => [
                [
                    'producto_id' => $this->producto->id,
                    'cantidad' => 5,
                    'precio_unitario' => 5000,
                ],
            ],
            'almacen_ids' => [$this->almacen->id],
        ]);

    $inventario = Inventario::where('producto_id', $this->producto->id)
        ->where('almacen_id', $this->almacen->id)
        ->first();

    expect($inventario)->not->toBeNull();
    expect((float) $inventario->cantidad)->toBe(-5.0);
});

test('se registra movimiento de inventario con stock negativo', function () {
    Inventario::create([
        'producto_id' => $this->producto->id,
        'almacen_id' => $this->almacen->id,
        'cantidad' => 1,
        'cantidad_minima' => 1,
    ]);

    $this->actingAs($this->user)
        ->post(route('ventas.store'), [
            'numero_factura' => 'FV-NEG-004',
            'cliente_id' => $this->cliente->id,
            'fecha' => '2026-07-22',
            'estado' => 'pagada',
            'incluye_iva' => false,
            'productos' => [
                [
                    'producto_id' => $this->producto->id,
                    'cantidad' => 8,
                    'precio_unitario' => 5000,
                ],
            ],
            'almacen_ids' => [$this->almacen->id],
        ]);

    $movimiento = InventoryMovement::withoutGlobalScopes()
        ->where('product_id', $this->producto->id)
        ->where('type', 'EGRESO')
        ->latest()
        ->first();

    expect($movimiento)->not->toBeNull();
    expect((float) $movimiento->quantity)->toBe(8.0);
});

test('venta con multiples productos y stock insuficiente se crea correctamente', function () {
    $producto2 = Producto::factory()->create([
        'codigo' => 'PROD-NEG-STOCK-2',
        'nombre' => 'Producto Test Negativo 2',
        'precio_venta' => 3000,
        'owner_id' => $this->ownerId,
        'categoria_id' => $this->categoria->id,
    ]);

    Inventario::create([
        'producto_id' => $this->producto->id,
        'almacen_id' => $this->almacen->id,
        'cantidad' => 2,
        'cantidad_minima' => 1,
    ]);

    Inventario::create([
        'producto_id' => $producto2->id,
        'almacen_id' => $this->almacen->id,
        'cantidad' => 0,
        'cantidad_minima' => 5,
    ]);

    $this->actingAs($this->user)
        ->post(route('ventas.store'), [
            'numero_factura' => 'FV-NEG-005',
            'cliente_id' => $this->cliente->id,
            'fecha' => '2026-07-22',
            'estado' => 'pagada',
            'incluye_iva' => false,
            'productos' => [
                [
                    'producto_id' => $this->producto->id,
                    'cantidad' => 10,
                    'precio_unitario' => 5000,
                ],
                [
                    'producto_id' => $producto2->id,
                    'cantidad' => 5,
                    'precio_unitario' => 3000,
                ],
            ],
            'almacen_ids' => [$this->almacen->id],
        ]);

    $inv1 = Inventario::where('producto_id', $this->producto->id)
        ->where('almacen_id', $this->almacen->id)
        ->first();
    $inv2 = Inventario::where('producto_id', $producto2->id)
        ->where('almacen_id', $this->almacen->id)
        ->first();

    expect((float) $inv1->cantidad)->toBe(-8.0);
    expect((float) $inv2->cantidad)->toBe(-5.0);

    $this->assertDatabaseHas('ventas', ['numero' => 'FV-NEG-005']);
    $this->assertDatabaseHas('detalle_ventas', [
        'producto_id' => $this->producto->id,
        'cantidad' => 10,
    ]);
    $this->assertDatabaseHas('detalle_ventas', [
        'producto_id' => $producto2->id,
        'cantidad' => 5,
    ]);
});

test('venta con stock exacto no genera negativo', function () {
    Inventario::create([
        'producto_id' => $this->producto->id,
        'almacen_id' => $this->almacen->id,
        'cantidad' => 10,
        'cantidad_minima' => 5,
    ]);

    $this->actingAs($this->user)
        ->post(route('ventas.store'), [
            'numero_factura' => 'FV-NEG-006',
            'cliente_id' => $this->cliente->id,
            'fecha' => '2026-07-22',
            'estado' => 'pagada',
            'incluye_iva' => false,
            'productos' => [
                [
                    'producto_id' => $this->producto->id,
                    'cantidad' => 10,
                    'precio_unitario' => 5000,
                ],
            ],
            'almacen_ids' => [$this->almacen->id],
        ]);

    $inventario = Inventario::where('producto_id', $this->producto->id)
        ->where('almacen_id', $this->almacen->id)
        ->first();

    expect((float) $inventario->cantidad)->toBe(0.0);
});
