<?php

use App\Models\Repartidor;
use App\Models\User;
use App\Services\Auth\ApiAuthService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

uses(TestCase::class);

beforeEach(function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.database', ':memory:');
    DB::purge('sqlite');
    config()->set('cache.default', 'array');

    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->string('country')->nullable();
        $table->timestamp('email_verified_at')->nullable();
        $table->rememberToken();
        $table->text('two_factor_secret')->nullable();
        $table->text('two_factor_recovery_codes')->nullable();
        $table->timestamp('two_factor_confirmed_at')->nullable();
        $table->string('telefono')->nullable();
        $table->string('rut')->nullable();
        $table->string('referral_code')->nullable();
        $table->unsignedBigInteger('creator_id')->nullable();
        $table->unsignedBigInteger('owner_id')->nullable();
        $table->boolean('is_active')->default(false);
        $table->boolean('has_explicit_role')->default(false);
        $table->timestamp('banned_at')->nullable();
        $table->timestamps();
    });
    Schema::create('vehiculos', fn (Blueprint $table) => $table->id());
    foreach ([
        '2026_03_08_214259_create_permission_tables.php',
        '2026_05_31_210237_add_owner_id_to_roles_table.php',
        '2026_08_11_184328_create_repartidores_table.php',
        '2026_08_16_004058_add_capacidad_max_to_repartidores_table.php',
        '2026_11_06_200834_add_driver_type_to_repartidores_table.php',
    ] as $migration) {
        (require database_path('migrations/'.$migration))->up();
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    User::withoutEvents(fn () => User::factory()->create([
        'id' => 10, 'name' => 'Comercio', 'email' => 'comercio@example.test',
        'country' => null, 'email_verified_at' => null, 'remember_token' => null,
        'two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null,
    ]));
});

function freelancerRegistrationData(): array
{
    return ['name' => 'Nuevo Repartidor', 'email' => 'repartidor@example.test',
        'password' => 'password123', 'password_confirmation' => 'password123',
        'telefono' => '+56912345678', 'vehiculo_tipo' => 'moto'];
}

test('un freelancer se registra sin empresa y queda activo para ingresar pero pendiente para repartir', function ($company) {
    $data = freelancerRegistrationData();
    if ($company !== 'omitted') {
        $data['owner_id'] = $company;
    }
    $response = $this->postJson('/api/v1/driver/register', $data)->assertCreated()
        ->assertJsonPath('success', true)->assertJsonPath('data.approval_status', Repartidor::APPROVAL_PENDING);
    $user = User::where('email', $data['email'])->firstOrFail();
    $driver = Repartidor::where('user_id', $user->id)->firstOrFail();
    expect($user->is_active)->toBeTrue()->and($user->creator_id)->toBeNull()
        ->and((int) $user->owner_id)->toBe($user->id)->and((int) $driver->owner_id)->toBe($user->id)
        ->and($user->hasRole(Repartidor::defaultRole()))->toBeTrue()
        ->and($user->hasRole('Super Admin'))->toBeFalse()
        ->and(Hash::check($data['password'], $user->password))->toBeTrue()
        ->and($driver->is_online)->toBeFalse();
    $response->assertJsonPath('data.owner_id', $user->id);
    expect(app(ApiAuthService::class)->accountStatus($user))->toBe([true, null]);
})->with(['omitted', null, '']);

test('un codigo valido vincula la solicitud a la empresa y sigue pendiente', function () {
    $this->postJson('/api/v1/driver/register', [...freelancerRegistrationData(), 'owner_id' => 10])
        ->assertCreated()->assertJsonPath('data.owner_id', 10);
    $user = User::where('email', 'repartidor@example.test')->firstOrFail();
    expect((int) $user->creator_id)->toBe(10)
        ->and(Repartidor::where('user_id', $user->id)->firstOrFail()->approval_status)->toBe(Repartidor::APPROVAL_PENDING);
});

test('un codigo invalido devuelve validacion y no crea cuentas', function ($company) {
    $this->postJson('/api/v1/driver/register', [...freelancerRegistrationData(), 'owner_id' => $company])
        ->assertUnprocessable()->assertJsonValidationErrors('owner_id');
    expect(User::count())->toBe(1)->and(Repartidor::count())->toBe(0);
})->with([99999, 0, 'invalido']);

test('registrar con atributos estrictos no descarta is_active ni provoca un error 500', function () {
    Model::preventSilentlyDiscardingAttributes();
    try {
        $this->postJson('/api/v1/driver/register', freelancerRegistrationData())->assertCreated();
    } finally {
        Model::preventSilentlyDiscardingAttributes(false);
    }
});

test('un correo repetido no duplica la solicitud', function () {
    $this->postJson('/api/v1/driver/register', freelancerRegistrationData())->assertCreated();
    $this->postJson('/api/v1/driver/register', freelancerRegistrationData())->assertUnprocessable()->assertJsonValidationErrors('email');
    expect(User::count())->toBe(2)->and(Repartidor::count())->toBe(1);
});

test('un fallo al crear el perfil revierte tambien el usuario', function () {
    Event::listen('eloquent.creating: '.Repartidor::class, fn () => throw new RuntimeException('Fallo de prueba'));
    $this->postJson('/api/v1/driver/register', freelancerRegistrationData())->assertStatus(500);
    expect(User::count())->toBe(1)->and(Repartidor::count())->toBe(0);
});
