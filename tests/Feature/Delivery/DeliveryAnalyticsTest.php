<?php

use App\Jobs\AsignacionAutomaticaJob;
use App\Models\Pedido;
use App\Models\Repartidor;
use App\Models\User;
use App\Models\Zone;
use App\Services\ZoneAnalytics;
use App\Services\ZoneAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;
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
            'capacidad_max' => 5,
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

    Event::fake();
});

test('rendimiento agrega asignaciones por motivo y por repartidor', function () {
    $repartidor = ($this->crearRepartidor)();
    $pedidoA = ($this->crearPedidoPool)();
    $pedidoB = ($this->crearPedidoPool)(['destino_lat' => -33.451, 'destino_lng' => -70.664]);

    (new AsignacionAutomaticaJob($pedidoA->id))->handle(app(ZoneAssignment::class));

    $resultado = app(ZoneAssignment::class)->asignarUnPedido($pedidoB, $repartidor, $this->zona, $this->owner->id);
    expect($resultado['success'])->toBeTrue();

    $rendimiento = app(ZoneAnalytics::class)->rendimiento($this->owner->id);

    expect($rendimiento['asignaciones'])->toBe(2)
        ->and($rendimiento['por_motivo']['auto_asignado'])->toBe(1)
        ->and($rendimiento['por_motivo']['asignado_manual'])->toBe(1)
        ->and($rendimiento['por_repartidor'])->toHaveCount(1)
        ->and($rendimiento['por_repartidor'][0]['user_id'])->toBe($repartidor->user_id)
        ->and($rendimiento['por_repartidor'][0]['asignaciones'])->toBe(2);
});

test('rendimiento calcula el tiempo medio en el pool', function () {
    $repartidor = ($this->crearRepartidor)();
    $pedido = ($this->crearPedidoPool)([
        'pool_entrada_at' => now()->subMinutes(10),
    ]);

    (new AsignacionAutomaticaJob($pedido->id))->handle(app(ZoneAssignment::class));

    $rendimiento = app(ZoneAnalytics::class)->rendimiento($this->owner->id);

    expect($rendimiento['tiempo_medio_pool_min'])->toBe(10.0);
});

test('rendimiento cuenta pedidos en pool sin repartidor', function () {
    ($this->crearRepartidor);
    ($this->crearPedidoPool)();

    $rendimiento = app(ZoneAnalytics::class)->rendimiento($this->owner->id);

    expect($rendimiento['en_pool_sin_repartidor'])->toBe(1);
});

test('rendimiento lista las asignaciones por día', function () {
    $repartidor = ($this->crearRepartidor)();
    $pedido = ($this->crearPedidoPool)();

    (new AsignacionAutomaticaJob($pedido->id))->handle(app(ZoneAssignment::class));

    $rendimiento = app(ZoneAnalytics::class)->rendimiento($this->owner->id);

    expect($rendimiento['por_dia'])->toHaveCount(1)
        ->and($rendimiento['por_dia'][0]['fecha'])->toBe(now()->toDateString())
        ->and($rendimiento['por_dia'][0]['asignaciones'])->toBe(1);
});

test('rendimiento marca como entregadas las asignaciones de pedidos entregados', function () {
    $repartidor = ($this->crearRepartidor)();
    $pedido = ($this->crearPedidoPool)();

    (new AsignacionAutomaticaJob($pedido->id))->handle(app(ZoneAssignment::class));

    $pedido->update(['estado' => 'entregado']);

    $rendimiento = app(ZoneAnalytics::class)->rendimiento($this->owner->id);

    expect($rendimiento['entregadas'])->toBe(1)
        ->and($rendimiento['por_repartidor'][0]['entregadas'])->toBe(1);
});

test('el admin de zonas sirve la prop de rendimiento', function () {
    Permission::firstOrCreate(['name' => 'zonas-reparto.view'], ['guard_name' => 'web']);
    $this->owner->givePermissionTo('zonas-reparto.view');

    $this->actingAs($this->owner)
        ->get('/zonas-reparto')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Backend/ZonaReparto/Index')
            ->has('rendimiento')
            ->where('rendimiento.asignaciones', 0));
});
