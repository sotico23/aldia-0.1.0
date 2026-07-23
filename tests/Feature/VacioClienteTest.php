<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\User;
use App\Models\Vacio;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->categoria = Categoria::factory()->create([
        'user_id' => $this->user->id,
    ]);
    $this->producto = Producto::factory()->create([
        'precio_venta' => 20000,
        'categoria_id' => $this->categoria->id,
    ]);
    $this->cliente = Cliente::factory()->create([
        'owner_id' => $this->user->getOwnerId(),
    ]);
});

test('vacio con estado entregado almacena cliente_id', function () {
    $vacio = Vacio::create([
        'owner_id' => $this->user->getOwnerId(),
        'producto_id' => $this->producto->id,
        'cliente_id' => $this->cliente->id,
        'cantidad' => 5,
        'cantidad_minima' => 0,
        'estado' => 'entregado',
        'observaciones' => 'Entregado en Venta #TEST-001',
    ]);

    $this->assertDatabaseHas('vacios', [
        'producto_id' => $this->producto->id,
        'cliente_id' => $this->cliente->id,
        'estado' => 'entregado',
        'cantidad' => 5,
    ]);

    expect($vacio->cliente_id)->toBe($this->cliente->id);
});

test('vacio con estado retornado almacena cliente_id', function () {
    $vacio = Vacio::create([
        'owner_id' => $this->user->getOwnerId(),
        'producto_id' => $this->producto->id,
        'cliente_id' => $this->cliente->id,
        'cantidad' => 3,
        'cantidad_minima' => 0,
        'estado' => 'retornado',
        'observaciones' => 'Retorno por cliente',
    ]);

    $this->assertDatabaseHas('vacios', [
        'producto_id' => $this->producto->id,
        'cliente_id' => $this->cliente->id,
        'estado' => 'retornado',
        'cantidad' => 3,
    ]);
});

test('vacio con estado disponible almacena cliente_id', function () {
    $vacio = Vacio::create([
        'owner_id' => $this->user->getOwnerId(),
        'producto_id' => $this->producto->id,
        'cliente_id' => $this->cliente->id,
        'cantidad' => 10,
        'cantidad_minima' => 0,
        'estado' => 'disponible',
    ]);

    $this->assertDatabaseHas('vacios', [
        'producto_id' => $this->producto->id,
        'cliente_id' => $this->cliente->id,
        'estado' => 'disponible',
    ]);
});

test('vacio permite cliente_id nulo', function () {
    $vacio = Vacio::create([
        'owner_id' => $this->user->getOwnerId(),
        'producto_id' => $this->producto->id,
        'cliente_id' => null,
        'cantidad' => 2,
        'cantidad_minima' => 0,
        'estado' => 'entregado',
    ]);

    expect($vacio->cliente_id)->toBeNull();

    $this->assertDatabaseHas('vacios', [
        'producto_id' => $this->producto->id,
        'cliente_id' => null,
        'estado' => 'entregado',
    ]);
});

test('relacion cliente() funciona con eager loading', function () {
    Vacio::create([
        'owner_id' => $this->user->getOwnerId(),
        'producto_id' => $this->producto->id,
        'cliente_id' => $this->cliente->id,
        'cantidad' => 4,
        'cantidad_minima' => 0,
        'estado' => 'entregado',
    ]);

    $vacio = Vacio::with('cliente')->where('cliente_id', $this->cliente->id)->first();

    expect($vacio->cliente)->not->toBeNull();
    expect($vacio->cliente->id)->toBe($this->cliente->id);
    expect($vacio->cliente->nombre)->toBe($this->cliente->nombre);
});

test('vacio permite actualizar cliente_id', function () {
    $vacio = Vacio::create([
        'owner_id' => $this->user->getOwnerId(),
        'producto_id' => $this->producto->id,
        'cliente_id' => $this->cliente->id,
        'cantidad' => 5,
        'cantidad_minima' => 0,
        'estado' => 'entregado',
    ]);

    $nuevoCliente = Cliente::factory()->create([
        'owner_id' => $this->user->getOwnerId(),
    ]);

    $vacio->update(['cliente_id' => $nuevoCliente->id]);

    $this->assertDatabaseHas('vacios', [
        'id' => $vacio->id,
        'cliente_id' => $nuevoCliente->id,
    ]);
});

test('vacios se agrupan por cliente_id correctamente', function () {
    $cliente2 = Cliente::factory()->create([
        'owner_id' => $this->user->getOwnerId(),
    ]);

    // 3 envases entregados al cliente 1
    Vacio::create([
        'owner_id' => $this->user->getOwnerId(),
        'producto_id' => $this->producto->id,
        'cliente_id' => $this->cliente->id,
        'cantidad' => 3,
        'cantidad_minima' => 0,
        'estado' => 'entregado',
    ]);

    // 2 envases entregados al cliente 2
    Vacio::create([
        'owner_id' => $this->user->getOwnerId(),
        'producto_id' => $this->producto->id,
        'cliente_id' => $cliente2->id,
        'cantidad' => 2,
        'cantidad_minima' => 0,
        'estado' => 'entregado',
    ]);

    $envasesCliente1 = Vacio::where('cliente_id', $this->cliente->id)
        ->where('estado', 'entregado')
        ->sum('cantidad');

    $envasesCliente2 = Vacio::where('cliente_id', $cliente2->id)
        ->where('estado', 'entregado')
        ->sum('cantidad');

    expect($envasesCliente1)->toBe(3);
    expect($envasesCliente2)->toBe(2);
});
