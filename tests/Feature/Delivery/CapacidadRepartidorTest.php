<?php

use App\Jobs\AsignacionAutomaticaJob;
use App\Models\Pedido;
use App\Models\Repartidor;
use App\Models\User;
use App\Models\Zone;
use App\Services\ZoneAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'Repartidor', 'guard_name' => 'web']);

    // Primer usuario creado se convierte en Super Admin; se crea un dummy
    // para absorber ese rol y que los demas usuarios queden en niveles bajos.
    User::factory()->create(['email' => 'dummy-setup@test.local']);

    $this->owner = User::factory()->create();

    $this->zona = Zone::factory()->create([
        'owner_id' => $this->owner->id,
        'name' => 'Centro',
        'lat' => -33.4489,
        'lng' => -70.6693,
        'radio_km' => 10,
        'capacidad_max' => 5,
    ]);

    $this->usuario = User::factory()->create([
        'creator_id' => $this->owner->id,
        'has_explicit_role' => true,
    ]);

    $this->repartidorUser = User::factory()->create([
        'creator_id' => $this->owner->id,
        'has_explicit_role' => true,
    ]);
    $this->repartidorUser->assignRole('Repartidor');

    $this->repartidor = Repartidor::factory()->create([
        'owner_id' => $this->owner->id,
        'user_id' => $this->repartidorUser->id,
        'estado' => 'disponible',
        'lat' => -33.4489,
        'lng' => -70.6693,
        'radio_km' => 10,
    ]);

    $this->crearPedidoPool = function (array $overrides = []) {
        return Pedido::factory()->create(array_merge([
            'owner_id' => $this->owner->id,
            'user_id' => $this->owner->id,
            'estado' => 'preparando',
            'destino_lat' => -33.452,
            'destino_lng' => -70.665,
        ], $overrides));
    };
});

// ============================================================================
// Capacidad en la asignacion automatica (tick)
// ============================================================================

test('la asignacion automatica respeta la capacidad maxima del repartidor', function () {
    $this->repartidor->update(['capacidad_max' => 1]);

    ($this->crearPedidoPool)();
    $segundo = ($this->crearPedidoPool)(['destino_lat' => -33.451, 'destino_lng' => -70.664]);

    Artisan::call('delivery:auto-assign');

    $segundo->refresh();
    expect($segundo->repartidor_id)->toBeNull();
    expect(Pedido::whereNotNull('repartidor_id')->count())->toBe(1);
});

test('con capacidad mayor el mismo repartidor recibe varios pedidos', function () {
    $this->repartidor->update(['capacidad_max' => 2]);

    $pedidoA = ($this->crearPedidoPool)();
    $pedidoB = ($this->crearPedidoPool)(['destino_lat' => -33.451, 'destino_lng' => -70.664]);

    Artisan::call('delivery:auto-assign');

    $pedidoA->refresh();
    $pedidoB->refresh();

    expect((int) $pedidoA->repartidor_id)->toBe($this->repartidorUser->id)
        ->and((int) $pedidoB->repartidor_id)->toBe($this->repartidorUser->id);
});

test('la asignacion automatica no reparte a un repartidor sin cupo', function () {
    $this->repartidor->update(['capacidad_max' => 1]);

    ($this->crearPedidoPool)([
        'repartidor_id' => $this->repartidorUser->id,
        'hora_aceptado' => now(),
    ]);

    $segundo = ($this->crearPedidoPool)();

    Artisan::call('delivery:auto-assign');

    $segundo->refresh();
    expect($segundo->repartidor_id)->toBeNull();
});

// ============================================================================
// Capacidad en el job de cola
// ============================================================================

test('el job de cola no asigna a un repartidor sin cupo', function () {
    $this->repartidor->update(['capacidad_max' => 1]);

    ($this->crearPedidoPool)([
        'repartidor_id' => $this->repartidorUser->id,
        'hora_aceptado' => now(),
    ]);

    $segundo = ($this->crearPedidoPool)();

    (new AsignacionAutomaticaJob($segundo->id))->handle(app(ZoneAssignment::class));

    $segundo->refresh();
    expect($segundo->repartidor_id)->toBeNull();
});

// ============================================================================
// Capacidad en la asignacion manual
// ============================================================================

test('la asignacion manual rechaza un repartidor sin cupo', function () {
    ($this->crearPedidoPool)([
        'repartidor_id' => $this->repartidorUser->id,
        'hora_aceptado' => now(),
    ]);

    $pedido = ($this->crearPedidoPool)();

    Sanctum::actingAs($this->usuario);

    $this->postJson("/api/v1/zones/pool/{$pedido->id}/asignar", [
        'repartidor_id' => $this->repartidorUser->id,
    ])
        ->assertStatus(409)
        ->assertJsonPath('message', 'El repartidor ya tiene un reparto activo.');
});

// ============================================================================
// Capacidad al aceptar del pool (API repartidor)
// ============================================================================

test('aceptar del pool rechaza al exceder la capacidad del repartidor', function () {
    ($this->crearPedidoPool)([
        'repartidor_id' => $this->repartidorUser->id,
        'hora_aceptado' => now(),
    ]);

    $segundo = ($this->crearPedidoPool)();

    Sanctum::actingAs($this->repartidorUser);

    $this->postJson("/api/v1/delivery/orders/{$segundo->id}/accept")
        ->assertStatus(409)
        ->assertJsonPath('message', 'Has alcanzado tu capacidad máxima de repartos activos.');

    $segundo->refresh();
    expect($segundo->repartidor_id)->toBeNull();
});

test('aceptar del pool funciona con capacidad superior', function () {
    $this->repartidor->update(['capacidad_max' => 2]);

    ($this->crearPedidoPool)([
        'repartidor_id' => $this->repartidorUser->id,
        'hora_aceptado' => now(),
    ]);

    $segundo = ($this->crearPedidoPool)();

    Sanctum::actingAs($this->repartidorUser);

    $this->postJson("/api/v1/delivery/orders/{$segundo->id}/accept")
        ->assertStatus(200)
        ->assertJsonPath('pedido.repartidor_id', $this->repartidorUser->id);
});
