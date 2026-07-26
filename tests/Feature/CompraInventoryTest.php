<?php

use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Compra;
use App\Models\DetalleCompra;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->ownerId = $this->user->getOwnerId();
    $this->categoria = Categoria::factory()->create([
        'owner_id' => $this->ownerId,
    ]);
    $this->almacen = Almacen::factory()->create([
        'user_id' => $this->user->id,
        'owner_id' => $this->ownerId,
    ]);
    $this->producto = Producto::factory()->create([
        'owner_id' => $this->ownerId,
        'categoria_id' => $this->categoria->id,
    ]);
});

test('store crea movimiento INGRESO cuando estado es recibida', function () {
    $response = $this->actingAs($this->user)
        ->post(route('compras.store'), [
            'numero' => 'FC-INV-001',
            'proveedor_id' => Proveedor::factory()->create(['owner_id' => $this->ownerId])->id,
            'fecha' => '2026-07-23',
            'estado' => 'recibida',
            'almacen_id' => $this->almacen->id,
            'productos' => [
                [
                    'producto_id' => $this->producto->id,
                    'cantidad' => 20,
                    'precio_unitario' => 5000,
                ],
            ],
        ]);

    $response->assertStatus(302);

    $this->assertDatabaseHas('inventory_movements', [
        'product_id' => $this->producto->id,
        'type' => 'INGRESO',
        'quantity' => 20,
        'destination_warehouse_id' => $this->almacen->id,
    ]);

    $inventario = Inventario::where('producto_id', $this->producto->id)
        ->where('almacen_id', $this->almacen->id)
        ->first();
    expect($inventario->cantidad)->toBe(20.0);
});

test('store no crea movimiento cuando estado es pendiente', function () {
    $response = $this->actingAs($this->user)
        ->post(route('compras.store'), [
            'numero' => 'FC-INV-002',
            'proveedor_id' => Proveedor::factory()->create(['owner_id' => $this->ownerId])->id,
            'fecha' => '2026-07-23',
            'estado' => 'pendiente',
            'almacen_id' => $this->almacen->id,
            'productos' => [
                [
                    'producto_id' => $this->producto->id,
                    'cantidad' => 20,
                    'precio_unitario' => 5000,
                ],
            ],
        ]);

    $response->assertStatus(302);

    $this->assertDatabaseCount('inventory_movements', 0);

    $inventario = Inventario::where('producto_id', $this->producto->id)
        ->where('almacen_id', $this->almacen->id)
        ->first();
    expect($inventario)->toBeNull();
});

test('update crea movimiento INGRESO al cambiar de pendiente a recibida', function () {
    $compra = Compra::factory()->create([
        'owner_id' => $this->ownerId,
        'almacen_id' => $this->almacen->id,
        'estado' => 'pendiente',
    ]);
    DetalleCompra::factory()->create([
        'compra_id' => $compra->id,
        'producto_id' => $this->producto->id,
        'cantidad' => 15,
    ]);

    $response = $this->actingAs($this->user)
        ->put(route('compras.update', $compra), [
            'estado' => 'recibida',
        ]);

    $response->assertStatus(302);

    $this->assertDatabaseHas('inventory_movements', [
        'product_id' => $this->producto->id,
        'type' => 'INGRESO',
        'quantity' => 15,
        'destination_warehouse_id' => $this->almacen->id,
    ]);
});

test('update crea movimiento EGRESO al cancelar compra recibida', function () {
    $compra = Compra::factory()->create([
        'owner_id' => $this->ownerId,
        'almacen_id' => $this->almacen->id,
        'estado' => 'recibida',
    ]);
    DetalleCompra::factory()->create([
        'compra_id' => $compra->id,
        'producto_id' => $this->producto->id,
        'cantidad' => 10,
    ]);

    Inventario::create([
        'producto_id' => $this->producto->id,
        'almacen_id' => $this->almacen->id,
        'owner_id' => $this->ownerId,
        'cantidad' => 10,
        'cantidad_minima' => 1,
    ]);

    $response = $this->actingAs($this->user)
        ->put(route('compras.update', $compra), [
            'estado' => 'cancelada',
        ]);

    $response->assertStatus(302);

    $this->assertDatabaseHas('inventory_movements', [
        'product_id' => $this->producto->id,
        'type' => 'EGRESO',
        'quantity' => 10,
        'source_warehouse_id' => $this->almacen->id,
    ]);

    $inventario = Inventario::where('producto_id', $this->producto->id)
        ->where('almacen_id', $this->almacen->id)
        ->first();
    expect($inventario->cantidad)->toBe(0.0);
});

test('destroy restaura inventario al eliminar compra recibida', function () {
    $compra = Compra::factory()->create([
        'owner_id' => $this->ownerId,
        'almacen_id' => $this->almacen->id,
        'estado' => 'recibida',
    ]);
    DetalleCompra::factory()->create([
        'compra_id' => $compra->id,
        'producto_id' => $this->producto->id,
        'cantidad' => 25,
    ]);

    Inventario::create([
        'producto_id' => $this->producto->id,
        'almacen_id' => $this->almacen->id,
        'owner_id' => $this->ownerId,
        'cantidad' => 25,
        'cantidad_minima' => 1,
    ]);

    $response = $this->actingAs($this->user)
        ->delete(route('compras.destroy', $compra));

    $response->assertStatus(302);

    $this->assertDatabaseHas('inventory_movements', [
        'product_id' => $this->producto->id,
        'type' => 'EGRESO',
        'quantity' => 25,
        'source_warehouse_id' => $this->almacen->id,
    ]);

    $inventario = Inventario::where('producto_id', $this->producto->id)
        ->where('almacen_id', $this->almacen->id)
        ->first();
    expect($inventario->cantidad)->toBe(0.0);

    $this->assertDatabaseMissing('compras', ['id' => $compra->id]);
});

test('destroy no restaura inventario al eliminar compra pendiente', function () {
    $compra = Compra::factory()->create([
        'owner_id' => $this->ownerId,
        'almacen_id' => $this->almacen->id,
        'estado' => 'pendiente',
    ]);
    DetalleCompra::factory()->create([
        'compra_id' => $compra->id,
        'producto_id' => $this->producto->id,
        'cantidad' => 10,
    ]);

    $response = $this->actingAs($this->user)
        ->delete(route('compras.destroy', $compra));

    $response->assertStatus(302);

    $this->assertDatabaseCount('inventory_movements', 0);
    $this->assertDatabaseMissing('compras', ['id' => $compra->id]);
});
