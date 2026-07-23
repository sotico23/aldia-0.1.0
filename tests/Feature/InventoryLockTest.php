<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Inventario;
use App\Models\InventoryMovement;
use App\Models\Producto;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->categoria = Categoria::factory()->create([
        'user_id' => $this->user->id,
    ]);
    $this->almacen = Almacen::factory()->create([
        'user_id' => $this->user->id,
        'owner_id' => $this->user->getOwnerId(),
    ]);
    $this->producto = Producto::factory()->create([
        'precio_venta' => 15000,
        'categoria_id' => $this->categoria->id,
    ]);
});

test('registrarMovimiento EGRESO decrementa stock correctamente', function () {
    Inventario::create([
        'producto_id' => $this->producto->id,
        'almacen_id' => $this->almacen->id,
        'cantidad' => 100,
        'cantidad_minima' => 5,
        'owner_id' => $this->user->getOwnerId(),
    ]);

    InventoryMovement::registrarMovimiento(
        productId: $this->producto->id,
        userId: $this->user->id,
        type: 'EGRESO',
        quantity: 30,
        sourceWarehouseId: $this->almacen->id,
        ownerId: $this->user->getOwnerId(),
    );

    $inventario = Inventario::where('producto_id', $this->producto->id)
        ->where('almacen_id', $this->almacen->id)
        ->first();

    expect((float) $inventario->cantidad)->toBe(70.0);

    $this->assertDatabaseHas('inventory_movements', [
        'product_id' => $this->producto->id,
        'type' => 'EGRESO',
        'quantity' => 30,
    ]);
});

test('registrarMovimiento EGRESO permite stock negativo (nuevo comportamiento)', function () {
    Inventario::create([
        'producto_id' => $this->producto->id,
        'almacen_id' => $this->almacen->id,
        'cantidad' => 10,
        'cantidad_minima' => 5,
        'owner_id' => $this->user->getOwnerId(),
    ]);

    InventoryMovement::registrarMovimiento(
        productId: $this->producto->id,
        userId: $this->user->id,
        type: 'EGRESO',
        quantity: 50,
        sourceWarehouseId: $this->almacen->id,
        ownerId: $this->user->getOwnerId(),
    );

    // Stock puede ir a negativo
    $inventario = Inventario::where('producto_id', $this->producto->id)
        ->where('almacen_id', $this->almacen->id)
        ->first();

    expect((float) $inventario->cantidad)->toBe(-40.0);
    $this->assertDatabaseHas('inventory_movements', [
        'product_id' => $this->producto->id,
        'type' => 'EGRESO',
        'quantity' => 50,
    ]);
});

test('registrarMovimiento EGRESO crea inventario si no existe y permite negativo', function () {
    // Sin crear Inventario previo
    InventoryMovement::registrarMovimiento(
        productId: $this->producto->id,
        userId: $this->user->id,
        type: 'EGRESO',
        quantity: 5,
        sourceWarehouseId: $this->almacen->id,
        ownerId: $this->user->getOwnerId(),
    );

    // Se crea el inventario con stock negativo
    $inventario = Inventario::where('producto_id', $this->producto->id)
        ->where('almacen_id', $this->almacen->id)
        ->first();

    expect($inventario)->not->toBeNull();
    expect((float) $inventario->cantidad)->toBe(-5.0);
    $this->assertDatabaseHas('inventory_movements', [
        'product_id' => $this->producto->id,
        'type' => 'EGRESO',
        'quantity' => 5,
    ]);
});

test('registrarMovimiento EGRESO exacto consume todo el stock', function () {
    Inventario::create([
        'producto_id' => $this->producto->id,
        'almacen_id' => $this->almacen->id,
        'cantidad' => 25,
        'cantidad_minima' => 5,
        'owner_id' => $this->user->getOwnerId(),
    ]);

    InventoryMovement::registrarMovimiento(
        productId: $this->producto->id,
        userId: $this->user->id,
        type: 'EGRESO',
        quantity: 25,
        sourceWarehouseId: $this->almacen->id,
        ownerId: $this->user->getOwnerId(),
    );

    $inventario = Inventario::where('producto_id', $this->producto->id)
        ->where('almacen_id', $this->almacen->id)
        ->first();

    expect((float) $inventario->cantidad)->toBe(0.0);
});

test('registrarMovimiento EGRESO secuenciales permiten stock negativo', function () {
    Inventario::create([
        'producto_id' => $this->producto->id,
        'almacen_id' => $this->almacen->id,
        'cantidad' => 50,
        'cantidad_minima' => 5,
        'owner_id' => $this->user->getOwnerId(),
    ]);

    // Primer descuento: 20
    InventoryMovement::registrarMovimiento(
        productId: $this->producto->id,
        userId: $this->user->id,
        type: 'EGRESO',
        quantity: 20,
        sourceWarehouseId: $this->almacen->id,
        ownerId: $this->user->getOwnerId(),
    );

    $inventario = Inventario::where('producto_id', $this->producto->id)->first();
    expect((float) $inventario->cantidad)->toBe(30.0);

    // Segundo descuento: 25
    InventoryMovement::registrarMovimiento(
        productId: $this->producto->id,
        userId: $this->user->id,
        type: 'EGRESO',
        quantity: 25,
        sourceWarehouseId: $this->almacen->id,
        ownerId: $this->user->getOwnerId(),
    );

    $inventario->refresh();
    expect((float) $inventario->cantidad)->toBe(5.0);

    // Tercer descuento: 10 — ahora va a negativo (-5)
    InventoryMovement::registrarMovimiento(
        productId: $this->producto->id,
        userId: $this->user->id,
        type: 'EGRESO',
        quantity: 10,
        sourceWarehouseId: $this->almacen->id,
        ownerId: $this->user->getOwnerId(),
    );

    $inventario->refresh();
    expect((float) $inventario->cantidad)->toBe(-5.0);
    $this->assertDatabaseHas('inventory_movements', [
        'product_id' => $this->producto->id,
        'type' => 'EGRESO',
        'quantity' => 10,
    ]);
});

test('registrarMovimiento INGRESO incrementa stock sin lock', function () {
    Inventario::create([
        'producto_id' => $this->producto->id,
        'almacen_id' => $this->almacen->id,
        'cantidad' => 20,
        'cantidad_minima' => 5,
        'owner_id' => $this->user->getOwnerId(),
    ]);

    InventoryMovement::registrarMovimiento(
        productId: $this->producto->id,
        userId: $this->user->id,
        type: 'INGRESO',
        quantity: 15,
        destinationWarehouseId: $this->almacen->id,
        ownerId: $this->user->getOwnerId(),
    );

    $inventario = Inventario::where('producto_id', $this->producto->id)
        ->where('almacen_id', $this->almacen->id)
        ->first();

    expect((float) $inventario->cantidad)->toBe(35.0);
});

test('registrarMovimiento EGRESO permite stock negativo en transacciones múltiples', function () {
    $producto2 = Producto::factory()->create([
        'precio_venta' => 25000,
        'categoria_id' => $this->categoria->id,
    ]);

    Inventario::create([
        'producto_id' => $this->producto->id,
        'almacen_id' => $this->almacen->id,
        'cantidad' => 50,
        'cantidad_minima' => 5,
        'owner_id' => $this->user->getOwnerId(),
    ]);

    Inventario::create([
        'producto_id' => $producto2->id,
        'almacen_id' => $this->almacen->id,
        'cantidad' => 2,
        'cantidad_minima' => 1,
        'owner_id' => $this->user->getOwnerId(),
    ]);

    // Simular dos movimientos en secuencia: primero OK, luego va a negativo
    InventoryMovement::registrarMovimiento(
        productId: $this->producto->id,
        userId: $this->user->id,
        type: 'EGRESO',
        quantity: 10,
        sourceWarehouseId: $this->almacen->id,
        ownerId: $this->user->getOwnerId(),
    );

    $inv1 = Inventario::where('producto_id', $this->producto->id)->first();
    expect((float) $inv1->cantidad)->toBe(40.0);

    // Este va a negativo (-8)
    InventoryMovement::registrarMovimiento(
        productId: $producto2->id,
        userId: $this->user->id,
        type: 'EGRESO',
        quantity: 10,
        sourceWarehouseId: $this->almacen->id,
        ownerId: $this->user->getOwnerId(),
    );

    $inv2 = Inventario::where('producto_id', $producto2->id)->first();
    expect((float) $inv2->cantidad)->toBe(-8.0);
    $this->assertDatabaseHas('inventory_movements', [
        'product_id' => $producto2->id,
        'type' => 'EGRESO',
        'quantity' => 10,
    ]);
});
