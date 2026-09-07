<?php

use App\Models\Pedido;
use App\Models\Repartidor;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function giveRepartidorPermissions(User $user, array $actions = ['viewAny', 'view', 'create', 'edit']): void
{
    $permissions = array_map(
        fn ($a) => Permission::firstOrCreate(['name' => "delivery.repartidores.{$a}"], ['guard_name' => 'web']),
        $actions
    );

    $user->givePermissionTo($permissions);
}

function createAdminWithoutRole(): User
{
    $user = User::factory()->create();

    $user->syncRoles([]);

    return $user;
}

beforeEach(function () {
    Role::firstOrCreate(['name' => 'Repartidor', 'guard_name' => 'web']);

    // Primer usuario creado se convierte en Super Admin; se crea un dummy
    // para absorber ese rol y que los demas usuarios queden en niveles bajos.
    User::factory()->create(['email' => 'dummy-setup@test.local']);

    $this->admin = createAdminWithoutRole();

    $this->candidato = User::factory()->create([
        'creator_id' => $this->admin->id,
        'has_explicit_role' => true,
    ]);
});

test('index requiere el permiso de ver repartidores', function () {
    $this->actingAs($this->admin)->get('/repartidores')->assertForbidden();

    giveRepartidorPermissions($this->admin, ['viewAny']);

    $this->actingAs($this->admin)
        ->get('/repartidores')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Backend/Repartidores/Index')
            ->has('repartidores')
            ->has('candidatos')
            ->where('candidatos.0.id', $this->candidato->id));
});

test('store crea el repartidor y vincula el rol al usuario', function () {
    giveRepartidorPermissions($this->admin);

    $this->actingAs($this->admin)->post('/repartidores', [
        'user_id' => $this->candidato->id,
        'estado' => 'disponible',
        'radio_km' => 8.5,
        'capacidad_max' => 4,
    ])->assertRedirect();

    $repartidor = Repartidor::where('user_id', $this->candidato->id)->first();

    expect($repartidor)->not->toBeNull()
        ->and((int) $repartidor?->owner_id)->toBe($this->admin->id)
        ->and($repartidor?->estado)->toBe('disponible')
        ->and((float) $repartidor?->radio_km)->toBe(8.5)
        ->and((int) $repartidor?->capacidad_max)->toBe(4)
        ->and($this->candidato->hasRole('Repartidor'))->toBeTrue();
});

test('store rechaza un usuario que ya tiene perfil de repartidor', function () {
    giveRepartidorPermissions($this->admin);

    Repartidor::factory()->create([
        'owner_id' => $this->admin->id,
        'user_id' => $this->candidato->id,
    ]);

    $this->actingAs($this->admin)->post('/repartidores', [
        'user_id' => $this->candidato->id,
        'estado' => 'disponible',
        'radio_km' => 10,
        'capacidad_max' => 5,
    ])->assertSessionHasErrors('user_id');

    expect(Repartidor::count())->toBe(1);
});

test('store rechaza un usuario de otra empresa', function () {
    giveRepartidorPermissions($this->admin);

    $otroOwner = createAdminWithoutRole();
    $usuarioAjeno = User::factory()->create([
        'creator_id' => $otroOwner->id,
        'has_explicit_role' => true,
    ]);

    $this->actingAs($this->admin)->post('/repartidores', [
        'user_id' => $usuarioAjeno->id,
        'estado' => 'disponible',
        'radio_km' => 10,
        'capacidad_max' => 5,
    ])->assertForbidden();
});

test('update modifica estado, radio y capacidad del repartidor', function () {
    giveRepartidorPermissions($this->admin);

    $repartidor = Repartidor::factory()->create([
        'owner_id' => $this->admin->id,
        'user_id' => $this->candidato->id,
        'estado' => 'offline',
        'radio_km' => 10,
        'capacidad_max' => 5,
    ]);

    $this->actingAs($this->admin)->put("/repartidores/{$repartidor->id}", [
        'estado' => 'ocupado',
        'radio_km' => 15,
        'capacidad_max' => 8,
    ])->assertRedirect();

    $repartidor->refresh();

    expect($repartidor->estado)->toBe('ocupado')
        ->and((float) $repartidor->radio_km)->toBe(15.0)
        ->and((int) $repartidor->capacidad_max)->toBe(8);
});

test('index muestra los repartos activos de cada repartidor', function () {
    giveRepartidorPermissions($this->admin);

    $zona = Zone::factory()->create([
        'owner_id' => $this->admin->id,
        'name' => 'Centro',
        'lat' => -33.4489,
        'lng' => -70.6693,
        'radio_km' => 10,
        'capacidad_max' => 10,
    ]);

    $repartidor = Repartidor::factory()->create([
        'owner_id' => $this->admin->id,
        'user_id' => $this->candidato->id,
        'estado' => 'disponible',
        'lat' => -33.4489,
        'lng' => -70.6693,
        'radio_km' => 10,
        'capacidad_max' => 5,
    ]);

    Pedido::factory()->create([
        'owner_id' => $this->admin->id,
        'user_id' => $this->admin->id,
        'estado' => 'preparando',
        'destino_lat' => -33.452,
        'destino_lng' => -70.665,
    ]);

    Artisan::call('delivery:auto-assign');

    $this->actingAs($this->admin)
        ->get('/repartidores')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('repartidores', 1)
            ->where('repartidores.0.user_id', $repartidor->user_id)
            ->where('repartidores.0.pedidos_activos', 1)
            ->where('repartidores.0.capacidad_max', (int) $repartidor->capacidad_max));
});
