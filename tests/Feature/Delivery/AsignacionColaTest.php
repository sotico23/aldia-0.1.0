<?php

use App\Events\DeliveryOrderPoolUpdated;
use App\Jobs\AsignacionAutomaticaJob;
use App\Models\DeliveryAsignacion;
use App\Models\DeliveryConfig;
use App\Models\Pedido;
use App\Models\Repartidor;
use App\Models\User;
use App\Models\Zone;
use App\Services\ZoneAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'Repartidor', 'guard_name' => 'web']);

    // Primer usuario creado se convierte en Super Admin; se crea un dummy
    // para absorber ese rol y que los demas usuarios queden en niveles bajos.
    User::factory()->create(['email' => 'dummy-setup@test.local']);

    $this->owner = User::factory()->create();
    Permission::firstOrCreate(['name' => 'comercial.oportunidades.viewAny', 'guard_name' => 'web']);
    $this->owner->givePermissionTo('comercial.oportunidades.viewAny');

    $this->zona = Zone::factory()->create([
        'owner_id' => $this->owner->id,
        'name' => 'Centro',
        'lat' => -33.4489,
        'lng' => -70.6693,
        'radio_km' => 10,
        'capacidad_max' => 10,
    ]);

    $this->crearRepartidor = function (array $overrides = []) {
        $user = User::factory()->create([
            'creator_id' => $this->owner->id,
            'has_explicit_role' => true,
        ]);
        $user->assignRole('Repartidor');

        return Repartidor::factory()->create(array_merge([
            'owner_id' => $this->owner->id,
            'user_id' => $user->id,
            'estado' => 'disponible',
            'lat' => -33.4489,
            'lng' => -70.6693,
            'radio_km' => 10,
        ], $overrides));
    };

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
// Dispatch al confirmar el pedido
// ============================================================================

test('al confirmar un pedido se despacha la asignacion automatica por cola', function () {
    $pedido = Pedido::factory()->create([
        'owner_id' => $this->owner->id,
        'user_id' => $this->owner->id,
        'estado' => 'pendiente',
        'destino_lat' => -33.452,
        'destino_lng' => -70.665,
    ]);

    Queue::fake();

    $this->actingAs($this->owner)
        ->put("/pedidos-recibidos/{$pedido->id}/estado", ['estado' => 'preparando'])
        ->assertRedirect();

    Queue::assertPushed(AsignacionAutomaticaJob::class, fn ($job) => $job->pedidoId === $pedido->id);

    $pedido->refresh();
    expect($pedido->pool_entrada_at)->not->toBeNull();
});

test('confirmar un pedido en otro estado no despacha la cola', function () {
    $pedido = Pedido::factory()->create([
        'owner_id' => $this->owner->id,
        'user_id' => $this->owner->id,
        'estado' => 'pendiente',
    ]);

    Queue::fake();

    $this->actingAs($this->owner)
        ->put("/pedidos-recibidos/{$pedido->id}/estado", ['estado' => 'confirmado'])
        ->assertRedirect();

    Queue::assertNotPushed(AsignacionAutomaticaJob::class);
});

// ============================================================================
// Comportamiento del job
// ============================================================================

test('el job asigna el pedido al repartidor disponible y registra el historial', function () {
    $repartidor = ($this->crearRepartidor)();
    $pedido = ($this->crearPedidoPool)();

    Event::fake([DeliveryOrderPoolUpdated::class]);

    (new AsignacionAutomaticaJob($pedido->id))->handle(app(ZoneAssignment::class));

    $pedido->refresh();
    expect((int) $pedido->repartidor_id)->toBe($repartidor->user_id)
        ->and($pedido->hora_aceptado)->not->toBeNull();

    $registro = DeliveryAsignacion::where('pedido_id', $pedido->id)->first();
    expect($registro)->not->toBeNull()
        ->and((int) $registro->repartidor_id)->toBe($repartidor->user_id)
        ->and((int) $registro->zona_id)->toBe($this->zona->id)
        ->and($registro->asignador_id)->toBeNull()
        ->and($registro->motivo)->toBe('auto_asignado');

    Event::assertDispatched(DeliveryOrderPoolUpdated::class, fn ($event) => $event->motivo === 'auto_asignado');
});

test('el job no asigna cuando el modo de configuracion es manual', function () {
    DeliveryConfig::factory()->create([
        'owner_id' => $this->owner->id,
        'modo' => 'manual',
    ]);

    ($this->crearRepartidor)();
    $pedido = ($this->crearPedidoPool)();

    (new AsignacionAutomaticaJob($pedido->id))->handle(app(ZoneAssignment::class));

    $pedido->refresh();
    expect($pedido->repartidor_id)->toBeNull();
});

test('el job no asigna durante el cooldown de reenvio', function () {
    ($this->crearRepartidor)();
    $pedido = ($this->crearPedidoPool)([
        'pool_visible_at' => now()->addMinutes(15),
    ]);

    (new AsignacionAutomaticaJob($pedido->id))->handle(app(ZoneAssignment::class));

    $pedido->refresh();
    expect($pedido->repartidor_id)->toBeNull();
});

test('el job no toca pedidos que ya salieron del pool', function () {
    $repartidor = ($this->crearRepartidor)();
    $pedido = ($this->crearPedidoPool)([
        'repartidor_id' => $repartidor->user_id,
        'hora_aceptado' => now(),
    ]);

    (new AsignacionAutomaticaJob($pedido->id))->handle(app(ZoneAssignment::class));

    $pedido->refresh();
    expect((int) $pedido->repartidor_id)->toBe($repartidor->user_id);
});

test('el job no asigna pedidos sin coordenadas de destino', function () {
    ($this->crearRepartidor)();
    $pedido = ($this->crearPedidoPool)([
        'destino_lat' => null,
        'destino_lng' => null,
    ]);

    (new AsignacionAutomaticaJob($pedido->id))->handle(app(ZoneAssignment::class));

    $pedido->refresh();
    expect($pedido->repartidor_id)->toBeNull();
});

test('el job ignora pedidos que ya no existen', function () {
    $pedido = ($this->crearPedidoPool)();
    $pedido->delete();

    (new AsignacionAutomaticaJob($pedido->id))->handle(app(ZoneAssignment::class));

    expect(true)->toBeTrue();
});
