<?php

use App\Models\Almacen;
use App\Models\CargaDiaria;
use App\Models\CargaDiariaProducto;
use App\Models\Categoria;
use App\Models\Conductor;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\User;
use App\Models\Vehiculo;

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
    $this->vehiculo = Vehiculo::factory()->create();
    $this->conductor = Conductor::factory()->create();
    $this->producto = Producto::factory()->create([
        'owner_id' => $this->ownerId,
        'categoria_id' => $this->categoria->id,
    ]);
});

test('store crea EGRESO del almacén al crear carga con productos', function () {
    Inventario::create([
        'producto_id' => $this->producto->id,
        'almacen_id' => $this->almacen->id,
        'owner_id' => $this->ownerId,
        'cantidad' => 100,
        'cantidad_minima' => 10,
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('cargas-diarias.store'), [
            'vehiculo_id' => $this->vehiculo->id,
            'conductor_id' => $this->conductor->id,
            'almacen_id' => $this->almacen->id,
            'fecha' => '2026-07-23',
            'estado' => 'pendiente',
            'productos' => [
                [
                    'producto_id' => $this->producto->id,
                    'cantidad' => 30,
                ],
            ],
        ]);

    $response->assertStatus(302);

    $this->assertDatabaseHas('inventory_movements', [
        'product_id' => $this->producto->id,
        'type' => 'EGRESO',
        'quantity' => 30,
        'source_warehouse_id' => $this->almacen->id,
    ]);

    $inventario = Inventario::where('producto_id', $this->producto->id)
        ->where('almacen_id', $this->almacen->id)
        ->first();
    expect($inventario->cantidad)->toBe(70.0);
});

test('store no crea movimiento sin almacen_id', function () {
    $response = $this->actingAs($this->user)
        ->post(route('cargas-diarias.store'), [
            'vehiculo_id' => $this->vehiculo->id,
            'conductor_id' => $this->conductor->id,
            'fecha' => '2026-07-23',
            'estado' => 'pendiente',
            'productos' => [
                [
                    'producto_id' => $this->producto->id,
                    'cantidad' => 10,
                ],
            ],
        ]);

    $response->assertStatus(302);

    $this->assertDatabaseCount('inventory_movements', 0);
});

test('recargar devuelve productos al almacén y crea nueva carga si se solicita', function () {
    Inventario::create([
        'producto_id' => $this->producto->id,
        'almacen_id' => $this->almacen->id,
        'owner_id' => $this->ownerId,
        'cantidad' => 70,
        'cantidad_minima' => 10,
    ]);

    $carga = CargaDiaria::factory()->create([
        'vehiculo_id' => $this->vehiculo->id,
        'conductor_id' => $this->conductor->id,
        'almacen_id' => $this->almacen->id,
        'owner_id' => $this->ownerId,
        'estado' => 'en_ruta',
    ]);

    CargaDiariaProducto::create([
        'owner_id' => $this->ownerId,
        'carga_diaria_id' => $carga->id,
        'producto_id' => $this->producto->id,
        'cantidad_bordo' => 30,
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('cargas-diarias.recargar', $carga), [
            'ventas_totales' => 50000,
            'devoluciones_totales' => 10000,
            'crear_nueva_carga' => true,
            'productos' => [
                [
                    'producto_id' => $this->producto->id,
                    'cantidad_bordo' => 30,
                    'cantidad_llena' => 20,
                    'cantidad_vacia' => 5,
                    'cantidad_faltante' => 3,
                    'cantidad_defectuosa' => 2,
                    'cantidad_vendida' => 15,
                    'cantidad_devuelta' => 10,
                ],
            ],
        ]);

    $response->assertStatus(302);

    // INGRESO: 20 (llena) + 5 (vacia) = 25 productos regresan al almacén
    $this->assertDatabaseHas('inventory_movements', [
        'product_id' => $this->producto->id,
        'type' => 'INGRESO',
        'quantity' => 25,
        'destination_warehouse_id' => $this->almacen->id,
    ]);

    // EGRESO: 20 (llena) salen para nueva carga
    $this->assertDatabaseHas('inventory_movements', [
        'product_id' => $this->producto->id,
        'type' => 'EGRESO',
        'quantity' => 20,
        'source_warehouse_id' => $this->almacen->id,
    ]);

    // Stock: 70 + 25 (retorno) - 20 (nueva carga) = 75
    $inventario = Inventario::where('producto_id', $this->producto->id)
        ->where('almacen_id', $this->almacen->id)
        ->first();
    expect($inventario->cantidad)->toBe(75.0);

    $this->assertDatabaseHas('carga_diarias', [
        'vehiculo_id' => $this->vehiculo->id,
        'estado' => 'pendiente',
    ]);
});

test('confirmarRenovacion devuelve productos al almacén', function () {
    Inventario::create([
        'producto_id' => $this->producto->id,
        'almacen_id' => $this->almacen->id,
        'owner_id' => $this->ownerId,
        'cantidad' => 75,
        'cantidad_minima' => 10,
    ]);

    $carga = CargaDiaria::factory()->create([
        'vehiculo_id' => $this->vehiculo->id,
        'conductor_id' => $this->conductor->id,
        'almacen_id' => $this->almacen->id,
        'owner_id' => $this->ownerId,
        'estado' => 'en_ruta',
    ]);

    CargaDiariaProducto::create([
        'owner_id' => $this->ownerId,
        'carga_diaria_id' => $carga->id,
        'producto_id' => $this->producto->id,
        'cantidad_bordo' => 25,
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('cargas-diarias.renovar', $carga), [
            'ventas_totales' => 40000,
            'devoluciones_totales' => 8000,
            'productos' => [
                [
                    'producto_id' => $this->producto->id,
                    'cantidad_bordo' => 25,
                    'cantidad_vendida' => 12,
                    'cantidad_devuelta' => 8,
                    'renovar' => true,
                ],
            ],
        ]);

    $response->assertStatus(302);

    // INGRESO: 8 productos devueltos al almacén
    $this->assertDatabaseHas('inventory_movements', [
        'product_id' => $this->producto->id,
        'type' => 'INGRESO',
        'quantity' => 8,
        'destination_warehouse_id' => $this->almacen->id,
    ]);

    // EGRESO: 25 - 12 (vendida) - 8 (devuelta) = 5 productos renovados
    $this->assertDatabaseHas('inventory_movements', [
        'product_id' => $this->producto->id,
        'type' => 'EGRESO',
        'quantity' => 5,
        'source_warehouse_id' => $this->almacen->id,
    ]);
});

test('destroy restaura inventario al eliminar carga con productos en camión', function () {
    Inventario::create([
        'producto_id' => $this->producto->id,
        'almacen_id' => $this->almacen->id,
        'owner_id' => $this->ownerId,
        'cantidad' => 70,
        'cantidad_minima' => 10,
    ]);

    $carga = CargaDiaria::factory()->create([
        'vehiculo_id' => $this->vehiculo->id,
        'conductor_id' => $this->conductor->id,
        'almacen_id' => $this->almacen->id,
        'owner_id' => $this->ownerId,
        'estado' => 'en_ruta',
    ]);

    CargaDiariaProducto::create([
        'owner_id' => $this->ownerId,
        'carga_diaria_id' => $carga->id,
        'producto_id' => $this->producto->id,
        'cantidad_bordo' => 30,
        'cantidad_vendida' => 10,
        'cantidad_devuelta' => 5,
    ]);

    $response = $this->actingAs($this->user)
        ->delete(route('cargas-diarias.destroy', $carga));

    $response->assertStatus(302);

    // Restaurar: 30 - 10 (vendida) - 5 (devuelta) = 15 productos en camión
    $this->assertDatabaseHas('inventory_movements', [
        'product_id' => $this->producto->id,
        'type' => 'INGRESO',
        'quantity' => 15,
        'destination_warehouse_id' => $this->almacen->id,
    ]);

    // Stock: 70 + 15 = 85
    $inventario = Inventario::where('producto_id', $this->producto->id)
        ->where('almacen_id', $this->almacen->id)
        ->first();
    expect($inventario->cantidad)->toBe(85.0);

    $this->assertDatabaseMissing('carga_diarias', ['id' => $carga->id]);
});

test('destroy no restaura si no hay productos en camión', function () {
    $carga = CargaDiaria::factory()->create([
        'vehiculo_id' => $this->vehiculo->id,
        'conductor_id' => $this->conductor->id,
        'almacen_id' => $this->almacen->id,
        'owner_id' => $this->ownerId,
        'estado' => 'pendiente',
    ]);

    CargaDiariaProducto::create([
        'owner_id' => $this->ownerId,
        'carga_diaria_id' => $carga->id,
        'producto_id' => $this->producto->id,
        'cantidad_bordo' => 20,
        'cantidad_vendida' => 10,
        'cantidad_devuelta' => 10,
    ]);

    $response = $this->actingAs($this->user)
        ->delete(route('cargas-diarias.destroy', $carga));

    $response->assertStatus(302);

    // 20 - 10 - 10 = 0, no movement needed
    $this->assertDatabaseCount('inventory_movements', 0);
    $this->assertDatabaseMissing('carga_diarias', ['id' => $carga->id]);
});
