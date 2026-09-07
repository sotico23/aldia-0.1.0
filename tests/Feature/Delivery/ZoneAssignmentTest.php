<?php

use App\Events\DeliveryOrderPoolUpdated;
use App\Models\DeliveryConfig;
use App\Models\Pedido;
use App\Models\Repartidor;
use App\Models\User;
use App\Models\Zone;
use App\Notifications\DeliveryPedidoAceptadoNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
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
            'destino_lat' => -33.45,
            'destino_lng' => -70.665,
        ], $overrides));
    };

    $this->ejecutarAsignacion = function () {
        return Artisan::call('delivery:auto-assign');
    };
});

// ============================================================================
// Asignacion basica
// ============================================================================

test('asigna el pedido del pool al repartidor dentro del radio', function () {
    ($this->crearRepartidor)();
    $pedido = ($this->crearPedidoPool)();

    Event::fake([DeliveryOrderPoolUpdated::class]);

    ($this->ejecutarAsignacion)();

    $pedido->refresh();
    expect($pedido->repartidor_id)->not->toBeNull();
    expect($pedido->hora_aceptado)->not->toBeNull();
    expect($pedido->estado)->toBe('preparando');

    Event::assertDispatched(DeliveryOrderPoolUpdated::class, fn ($event) => $event->motivo === 'auto_asignado');
});

test('elige al repartidor mas cercano al destino', function () {
    $lejano = ($this->crearRepartidor)([
        'lat' => -33.44,
        'lng' => -70.66,
        'radio_km' => 10,
    ]);
    $cercano = ($this->crearRepartidor)([
        'lat' => -33.4495,
        'lng' => -70.6645,
        'radio_km' => 10,
    ]);

    $pedido = ($this->crearPedidoPool)([
        'destino_lat' => -33.4496,
        'destino_lng' => -70.6646,
    ]);

    ($this->ejecutarAsignacion)();

    $pedido->refresh();
    expect((int) $pedido->repartidor_id)->toBe($cercano->user_id);
    expect((int) $pedido->repartidor_id)->not->toBe($lejano->user_id);
});

test('no asigna a un repartidor ocupado ni fuera de su radio', function () {
    ($this->crearRepartidor)(['estado' => 'ocupado']);
    ($this->crearRepartidor)([
        'lat' => -33.05,
        'lng' => -70.9,
        'radio_km' => 2,
    ]);

    $pedido = ($this->crearPedidoPool)();

    ($this->ejecutarAsignacion)();

    $pedido->refresh();
    expect($pedido->repartidor_id)->toBeNull();
});

test('notifica al vendedor cuando asigna de forma automatica', function () {
    ($this->crearRepartidor)();
    ($this->crearPedidoPool)();

    Notification::fake();

    ($this->ejecutarAsignacion)();

    Notification::assertSentTo($this->owner, DeliveryPedidoAceptadoNotification::class);
});

// ============================================================================
// Capacidad de zona
// ============================================================================

test('respeta la capacidad maxima de la zona', function () {
    $this->zona->update(['capacidad_max' => 2]);

    ($this->crearRepartidor)(['capacidad_max' => 5]);
    ($this->crearPedidoPool)();
    ($this->crearPedidoPool)(['destino_lat' => -33.451, 'destino_lng' => -70.664]);
    $sinCapacidad = ($this->crearPedidoPool)(['destino_lat' => -33.452, 'destino_lng' => -70.663]);

    ($this->ejecutarAsignacion)();

    $sinCapacidad->refresh();
    expect($sinCapacidad->repartidor_id)->toBeNull();
    expect(Pedido::whereNotNull('repartidor_id')->count())->toBe(2);
});

test('cuenta los pedidos ya en curso para la capacidad', function () {
    $this->zona->update(['capacidad_max' => 1]);

    ($this->crearRepartidor)();

    // Pedido ya asignado a otro repartidor dentro de la zona
    ($this->crearRepartidor)();
    ($this->crearPedidoPool)([
        'repartidor_id' => $this->owner->id,
        'hora_aceptado' => now(),
    ]);

    $pendiente = ($this->crearPedidoPool)(['destino_lat' => -33.451, 'destino_lng' => -70.664]);

    ($this->ejecutarAsignacion)();

    $pendiente->refresh();
    expect($pendiente->repartidor_id)->toBeNull();
});

// ============================================================================
// Fuera de zonas y configuracion
// ============================================================================

test('pedido fuera de toda zona permanece en el pool', function () {
    ($this->crearRepartidor)();

    $lejos = ($this->crearPedidoPool)([
        'destino_lat' => -33.05,
        'destino_lng' => -70.9,
    ]);

    ($this->ejecutarAsignacion)();

    $lejos->refresh();
    expect($lejos->repartidor_id)->toBeNull();
});

test('no asigna cuando no hay zonas activas', function () {
    $this->zona->update(['activa' => false]);

    ($this->crearRepartidor)();
    $pedido = ($this->crearPedidoPool)();

    ($this->ejecutarAsignacion)();

    $pedido->refresh();
    expect($pedido->repartidor_id)->toBeNull();
});

test('no asigna cuando el modo de configuracion es manual', function () {
    DeliveryConfig::factory()->create([
        'owner_id' => $this->owner->id,
        'modo' => 'manual',
    ]);

    ($this->crearRepartidor)();
    $pedido = ($this->crearPedidoPool)();

    ($this->ejecutarAsignacion)();

    $pedido->refresh();
    expect($pedido->repartidor_id)->toBeNull();
});

test('respeta el limite de capacidad entre zonas distintas', function () {
    $this->zona->update(['capacidad_max' => 1]);

    // Segunda zona cercana al mismo destino, para que no quede sin zona
    Zone::factory()->create([
        'owner_id' => $this->owner->id,
        'name' => 'Centro Ampliado',
        'lat' => -33.4490,
        'lng' => -70.6693,
        'radio_km' => 15,
        'capacidad_max' => 5,
    ]);

    ($this->crearRepartidor)(['capacidad_max' => 5]);
    ($this->crearPedidoPool)();
    $segundo = ($this->crearPedidoPool)(['destino_lat' => -33.451, 'destino_lng' => -70.664]);

    ($this->ejecutarAsignacion)();

    $segundo->refresh();
    expect($segundo->repartidor_id)->not->toBeNull();
});

// ============================================================================
// Aislamiento multi-tenant
// ============================================================================

test('solo procesa pedidos de tenants con zonas y repartidores', function () {
    ($this->crearRepartidor)();
    $pedidoPropio = ($this->crearPedidoPool)();

    $otroOwner = User::factory()->create();
    $otroUsuario = User::factory()->create([
        'creator_id' => $otroOwner->id,
        'has_explicit_role' => true,
    ]);
    $otroUsuario->assignRole('Repartidor');
    Repartidor::factory()->create([
        'owner_id' => $otroOwner->id,
        'user_id' => $otroUsuario->id,
        'estado' => 'disponible',
    ]);
    Pedido::factory()->create([
        'owner_id' => $otroOwner->id,
        'user_id' => $otroOwner->id,
        'estado' => 'preparando',
        'destino_lat' => -33.45,
        'destino_lng' => -70.665,
    ]);

    // El tenant ajeno no tiene zona: su pedido no se toca
    ($this->ejecutarAsignacion)();

    $pedidoPropio->refresh();
    expect($pedidoPropio->repartidor_id)->not->toBeNull();

    $pedidoAjeno = Pedido::withoutGlobalScopes()->where('owner_id', $otroOwner->id)->first();
    expect($pedidoAjeno->repartidor_id)->toBeNull();
});
