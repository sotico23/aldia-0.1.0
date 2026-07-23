<?php

use App\Models\User;

test('required fields are validated on cliente creation', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('clientes.store'), []);

    $response->assertSessionHasErrors('nombre');
});

test('email validation rejects invalid format', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('clientes.store'), [
        'nombre' => 'Test Client',
        'email' => 'invalid-email',
    ]);

    $response->assertSessionHasErrors('email');
});

test('producto requires precio fields', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('productos.store'), [
        'codigo' => 'TEST-001',
        'nombre' => 'Test Product',
        'stock' => 10,
        'stock_minimo' => 5,
    ]);

    $response->assertSessionHasErrors(['precio_compra', 'precio_venta']);
});

test('producto price must be numeric', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('productos.store'), [
        'codigo' => 'TEST-002',
        'nombre' => 'Test Product',
        'precio_compra' => 'not-a-number',
        'precio_venta' => 'not-a-number',
        'stock' => 10,
        'stock_minimo' => 5,
    ]);

    $response->assertSessionHasErrors(['precio_compra', 'precio_venta']);
});
