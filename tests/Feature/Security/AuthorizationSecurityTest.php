<?php

use App\Models\Almacen;
use App\Models\Cliente;
use App\Models\User;

test('users can only see their own resources', function () {
    // First user gets Super Admin — create a dummy to avoid that
    User::factory()->create(['email' => 'dummy@setup.test']);
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $cliente1 = Cliente::factory()->for($user1, 'owner')->create();
    Cliente::factory()->for($user2, 'owner')->create();

    $this->actingAs($user1);

    expect(Cliente::count())->toBe(1);
    expect(Cliente::first()->id)->toBe($cliente1->id);
});

test('users can only create resources with their owner_id', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $cliente = Cliente::create([
        'nombre' => 'Test Cliente',
        'nit' => '12345678-9',
        'telefono' => '12345678',
        'email' => 'test@test.com',
        'owner_id' => $user->id,
    ]);

    expect($cliente->owner_id)->toBe($user->id);
});

test('global scope filters results by owner', function () {
    // First user gets Super Admin — create a dummy to avoid that
    User::factory()->create(['email' => 'dummy@setup.test']);
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    Almacen::factory()->for($user1, 'owner')->create(['nombre' => 'Almacen1']);
    Almacen::factory()->for($user2, 'owner')->create(['nombre' => 'Almacen2']);

    $this->actingAs($user1);

    expect(Almacen::count())->toBe(1);
    expect(Almacen::first()->nombre)->toBe('Almacen1');
});

test('owner_id is automatically set on creation', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $cliente = Cliente::create([
        'nombre' => 'Test',
        'nit' => '123',
        'owner_id' => null,
    ]);

    expect($cliente->owner_id)->toBe($user->id);
});
