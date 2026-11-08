<?php

use App\Models\Empleado;
use App\Models\Repartidor;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function companyDriverAdmin(array $permissions): User
{
    $admin = User::factory()->create();
    $admin->syncRoles([]);

    foreach ($permissions as $permission) {
        $admin->givePermissionTo(
            Permission::firstOrCreate(['name' => $permission], ['guard_name' => 'web'])
        );
    }

    return $admin;
}

beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);

    Role::firstOrCreate(['name' => Repartidor::defaultRole(), 'guard_name' => 'web']);

    // El primer usuario recibe Super Admin; se consume con un dummy.
    User::factory()->create(['email' => 'bootstrap-company-driver@test.local']);

    $this->admin = companyDriverAdmin([
        'delivery.repartidores.viewAny',
        'delivery.repartidores.create',
        'rrhh.empleados.viewAny',
        'rrhh.empleados.create',
    ]);
});

test('crea un repartidor nuevo con rol y tenant automaticos y devuelve el usuario con su rol', function () {
    $response = $this->actingAs($this->admin)->postJson('/repartidores', [
        'name' => 'Driver Nuevo',
        'email' => 'driver.nuevo@test.local',
        'password' => 'password123',
        'telefono' => '+56911111111',
        'estado' => 'disponible',
        'radio_km' => 12.5,
        'capacidad_max' => 3,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.user.email', 'driver.nuevo@test.local')
        ->assertJsonPath('data.user.roles.0', Repartidor::defaultRole())
        ->assertJsonPath('data.repartidor.owner_id', (int) $this->admin->getOwnerId());

    $user = User::where('email', 'driver.nuevo@test.local')->firstOrFail();
    $repartidor = Repartidor::where('user_id', $user->id)->firstOrFail();

    expect($user->hasRole(Repartidor::defaultRole()))->toBeTrue()
        ->and($user->hasRole('Usuario'))->toBeFalse()
        ->and((int) $user->creator_id)->toBe($this->admin->id)
        ->and((int) $user->owner_id)->toBe((int) $this->admin->getOwnerId())
        ->and((int) $repartidor->owner_id)->toBe((int) $this->admin->getOwnerId())
        ->and($repartidor->driver_type)->toBe(Repartidor::TYPE_EMPLOYEE)
        ->and($repartidor->approval_status)->toBe(Repartidor::APPROVAL_APPROVED);
});

test('vincula un usuario existente del tenant y le asigna el rol por defecto', function () {
    $candidato = User::factory()->create([
        'creator_id' => $this->admin->id,
        'has_explicit_role' => true,
    ]);

    $this->actingAs($this->admin)->postJson('/repartidores', [
        'user_id' => $candidato->id,
        'estado' => 'disponible',
        'radio_km' => 8,
        'capacidad_max' => 2,
    ])->assertCreated();

    $candidato->refresh();

    expect($candidato->hasRole(Repartidor::defaultRole()))->toBeTrue()
        ->and(Repartidor::where('user_id', $candidato->id)->exists())->toBeTrue();
});

test('crea un empleado repartidor sin exigir rol manual y le asigna el rol por defecto', function () {
    $rolRepartidor = Role::where('name', Repartidor::defaultRole())->firstOrFail();

    $this->actingAs($this->admin)->post('/empleados', [
        'nombre' => 'Empleado',
        'apellido' => 'Repartidor',
        'email' => 'empleado.repartidor@test.local',
        'estado' => 'activo',
        'crear_usuario' => true,
        'es_repartidor' => true,
        // Aunque el formulario envíe un rol global, se ignora.
        'rol_id' => $rolRepartidor->id,
    ])->assertRedirect(route('empleados.index'));

    $empleado = Empleado::where('email', 'empleado.repartidor@test.local')->firstOrFail();
    $user = $empleado->user;

    expect($user)->not->toBeNull()
        ->and($user->hasRole(Repartidor::defaultRole()))->toBeTrue()
        ->and((int) $user->creator_id)->toBe($this->admin->id)
        ->and((int) $user->owner_id)->toBe((int) $this->admin->getOwnerId())
        ->and(Repartidor::where('user_id', $user->id)->exists())->toBeTrue();
});
