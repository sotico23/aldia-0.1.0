<?php

use App\Models\Empleado;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('new client with access has default password clientenuevo', function () {
    $admin = User::factory()->create();

    $payload = [
        'nombre' => 'Cliente Test',
        'email' => 'clientetest@example.com',
        'crear_usuario' => true,
        'password' => 'clientenuevo',
        'activo' => true,
        'rut' => '12.345.678-9',
    ];

    $this->actingAs($admin)
        ->from('/clientes')
        ->post('/clientes', $payload)
        ->assertRedirect('/clientes');

    $user = User::where('email', 'clientetest@example.com')->first();
    expect($user)->not->toBeNull();
    expect(Hash::check('clientenuevo', $user->password))->toBeTrue();
});

test('new employee with access has default password empleadonuevo', function () {
    $admin = User::factory()->create();

    $payload = [
        'nombre' => 'Empleado',
        'apellido' => 'Test',
        'email' => 'empleadotest@example.com',
        'estado' => 'activo',
        'crear_usuario' => true,
        'cargo' => 'Tester',
    ];

    $this->actingAs($admin)
        ->from('/empleados')
        ->post('/empleados', $payload)
        ->assertRedirect('/empleados');

    $user = User::where('email', 'empleadotest@example.com')->first();
    expect($user)->not->toBeNull();
    expect(Hash::check('empleadonuevo', $user->password))->toBeTrue();
});

test('employee creation without access does not create user', function () {
    $admin = User::factory()->create();

    $payload = [
        'nombre' => 'No Access',
        'apellido' => 'User',
        'email' => 'noaccess@example.com',
        'estado' => 'activo',
        'crear_usuario' => false,
    ];

    $this->actingAs($admin)
        ->from('/empleados')
        ->post('/empleados', $payload)
        ->assertRedirect('/empleados');

    $user = User::where('email', 'noaccess@example.com')->first();
    expect($user)->toBeNull();

    $empleado = Empleado::where('email', 'noaccess@example.com')->first();
    expect($empleado)->not->toBeNull();
    expect($empleado->user_id)->toBeNull();
});

test('updating employee to add access works and sets password', function () {
    $admin = User::first() ?: User::factory()->create();

    // Create manually to avoid the factory issues for now
    $empleado = Empleado::create([
        'nombre' => 'Update',
        'apellido' => 'Access',
        'email' => 'updateaccess@example.com',
        'estado' => 'activo',
        'owner_id' => $admin->id,
        'creator_id' => $admin->id,
    ]);

    $payload = [
        'nombre' => 'Update',
        'apellido' => 'Access',
        'email' => 'updateaccess@example.com',
        'estado' => 'activo',
        'crear_usuario' => true,
        'cargo' => 'Tester',
        'telefono' => '123456',
        'direccion' => 'Calle Falsa 123',
    ];

    $this->actingAs($admin)
        ->from('/empleados')
        ->put("/empleados/{$empleado->id}", $payload)
        ->assertRedirect('/empleados');

    $user = User::where('email', 'updateaccess@example.com')->first();
    expect($user)->not->toBeNull();
    expect(Hash::check('empleadonuevo', $user->password))->toBeTrue();

    $empleado->refresh();
    expect($empleado->user_id)->toBe($user->id);
});
