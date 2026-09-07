<?php

use App\Events\DeliveryOrderPoolUpdated;
use App\Jobs\AsignacionAutomaticaJob;
use App\Models\DeliveryAsignacion;
use App\Models\Pedido;
use App\Models\PedidoStatusLog;
use App\Models\Repartidor;
use App\Models\User;
use App\Models\Zone;
use App\Services\ZoneAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
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
// Guard atomico de asignacion (job + tick simultaneos)
// ============================================================================

test('el job y el tick no asignan dos veces el mismo pedido', function () {
    $repartidor = ($this->crearRepartidor)();
    $pedido = ($this->crearPedidoPool)();

    Event::fake([DeliveryOrderPoolUpdated::class]);

    (new AsignacionAutomaticaJob($pedido->id))->handle(app(ZoneAssignment::class));

    Artisan::call('delivery:auto-assign');

    $pedido->refresh();

    expect((int) $pedido->repartidor_id)->toBe($repartidor->user_id)
        ->and(Pedido::where('id', $pedido->id)->where('repartidor_id', $repartidor->user_id)->count())->toBe(1)
        ->and(DeliveryAsignacion::where('pedido_id', $pedido->id)->count())->toBe(1)
        ->and(PedidoStatusLog::where('pedido_id', $pedido->id)->where('field', 'repartidor')->count())->toBe(1);

    Event::assertDispatchedTimes(DeliveryOrderPoolUpdated::class, 1);
});

test('asignarPedido revalida bajo el lock y rechaza un segundo intento', function () {
    $repartidor = ($this->crearRepartidor)();
    $segundo = ($this->crearRepartidor)(['lat' => -33.42, 'lng' => -70.67]);
    $pedido = ($this->crearPedidoPool)();

    Event::fake([DeliveryOrderPoolUpdated::class]);

    $assignment = app(ZoneAssignment::class);

    expect($assignment->asignarPedido($pedido, $repartidor, 'auto_asignado', null, $this->zona))->toBeTrue();

    // Instancia obsoleta en memoria: el segundo proceso no vio el update.
    expect($assignment->asignarPedido($pedido, $segundo, 'auto_asignado', null, $this->zona))->toBeFalse();

    $pedido->refresh();

    expect((int) $pedido->repartidor_id)->toBe($repartidor->user_id)
        ->and(DeliveryAsignacion::where('pedido_id', $pedido->id)->count())->toBe(1)
        ->and(PedidoStatusLog::where('pedido_id', $pedido->id)->where('field', 'repartidor')->count())->toBe(1);

    Event::assertDispatchedTimes(DeliveryOrderPoolUpdated::class, 1);
});

test('dos corridas del job no duplican el historial de la asignacion', function () {
    ($this->crearRepartidor)();
    $pedido = ($this->crearPedidoPool)();

    Event::fake([DeliveryOrderPoolUpdated::class]);

    (new AsignacionAutomaticaJob($pedido->id))->handle(app(ZoneAssignment::class));
    (new AsignacionAutomaticaJob($pedido->id))->handle(app(ZoneAssignment::class));

    expect(DeliveryAsignacion::where('pedido_id', $pedido->id)->count())->toBe(1)
        ->and(PedidoStatusLog::where('pedido_id', $pedido->id)->where('field', 'repartidor')->count())->toBe(1);

    Event::assertDispatchedTimes(DeliveryOrderPoolUpdated::class, 1);
});
