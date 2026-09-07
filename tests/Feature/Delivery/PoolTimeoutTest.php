<?php

use App\Events\DeliveryOrderPoolUpdated;
use App\Models\DeliveryConfig;
use App\Models\Pedido;
use App\Models\PedidoStatusLog;
use App\Models\Repartidor;
use App\Models\User;
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
        ], $overrides));
    };

    $this->crearPedidoAceptado = function (array $overrides = []) {
        return Pedido::factory()->create(array_merge([
            'owner_id' => $this->owner->id,
            'user_id' => $this->owner->id,
            'estado' => 'preparando',
            'repartidor_id' => ($this->crearRepartidor)(),
            'hora_aceptado' => now()->subMinutes(15),
            'pool_entrada_at' => now()->subMinutes(20),
        ], $overrides));
    };

    $this->ejecutarTimeout = function () {
        return Artisan::call('delivery:pool-timeout');
    };
});

// ============================================================================
// Liberacion por timeout
// ============================================================================

test('libera el pedido aceptado vencido y lo devuelve al pool', function () {
    $pedido = ($this->crearPedidoAceptado)();

    Event::fake([DeliveryOrderPoolUpdated::class]);

    ($this->ejecutarTimeout)();

    $pedido->refresh();

    expect($pedido->repartidor_id)->toBeNull()
        ->and($pedido->hora_aceptado)->toBeNull()
        ->and($pedido->pool_reenvios)->toBe(1)
        ->and($pedido->pool_entrada_at)->not->toBeNull();

    $log = PedidoStatusLog::where('pedido_id', $pedido->id)
        ->where('field', 'repartidor')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->field)->toBe('repartidor')
        ->and($log->to)->toBe('pool')
        ->and($log->motivo)->toBe('timeout');

    Event::assertDispatched(DeliveryOrderPoolUpdated::class, function ($event) use ($pedido) {
        return $event->motivo === 'timeout'
            && $event->pedido->id === $pedido->id
            && $event->pedido->repartidor_id === null;
    });
});

test('no libera el pedido dentro del timeout por defecto', function () {
    $pedido = ($this->crearPedidoAceptado)(['hora_aceptado' => now()->subMinutes(5)]);

    Event::fake([DeliveryOrderPoolUpdated::class]);

    ($this->ejecutarTimeout)();

    $pedido->refresh();

    expect($pedido->repartidor_id)->not->toBeNull()
        ->and($pedido->pool_reenvios)->toBe(0);

    Event::assertNotDispatched(DeliveryOrderPoolUpdated::class);
});

test('no libera el pedido ya recogido aunque tenga aceptacion vencida', function () {
    $repartidor = ($this->crearRepartidor)();
    $pedido = ($this->crearPedidoAceptado)([
        'repartidor_id' => $repartidor->id,
        'hora_aceptado' => now()->subHours(2),
        'hora_recogido' => now()->subHour(),
    ]);

    Event::fake([DeliveryOrderPoolUpdated::class]);

    ($this->ejecutarTimeout)();

    $pedido->refresh();

    expect($pedido->repartidor_id)->toBe($repartidor->id)
        ->and($pedido->pool_reenvios)->toBe(0);

    Event::assertNotDispatched(DeliveryOrderPoolUpdated::class);
});

test('respeta el pool_timeout_min configurado por tenant', function () {
    DeliveryConfig::factory()->create([
        'owner_id' => $this->owner->id,
        'pool_timeout_min' => 60,
    ]);

    $pedido = ($this->crearPedidoAceptado)();

    ($this->ejecutarTimeout)();

    $pedido->refresh();

    expect($pedido->repartidor_id)->not->toBeNull()
        ->and($pedido->pool_reenvios)->toBe(0);
});

test('no libera pedidos de otros tenants', function () {
    $otroOwner = User::factory()->create();
    DeliveryConfig::factory()->create([
        'owner_id' => $otroOwner->id,
        'pool_timeout_min' => 60,
    ]);

    $repartidorOtro = User::factory()->create([
        'creator_id' => $otroOwner->id,
        'has_explicit_role' => true,
    ]);
    $repartidorOtro->assignRole('Repartidor');

    $pedidoOtro = Pedido::factory()->create([
        'owner_id' => $otroOwner->id,
        'user_id' => $otroOwner->id,
        'estado' => 'preparando',
        'repartidor_id' => Repartidor::factory()->create([
            'owner_id' => $otroOwner->id,
            'user_id' => $repartidorOtro->id,
        ]),
        'hora_aceptado' => now()->subMinutes(15),
    ]);

    ($this->ejecutarTimeout)();

    $pedidoOtro->refresh();

    expect($pedidoOtro->repartidor_id)->not->toBeNull()
        ->and($pedidoOtro->pool_reenvios)->toBe(0);
});

test('no toca los pedidos del pool sin repartidor asignado', function () {
    $pedido = Pedido::factory()->create([
        'owner_id' => $this->owner->id,
        'user_id' => $this->owner->id,
        'estado' => 'preparando',
        'repartidor_id' => null,
    ]);

    Event::fake([DeliveryOrderPoolUpdated::class]);

    ($this->ejecutarTimeout)();

    $pedido->refresh();

    expect($pedido->repartidor_id)->toBeNull()
        ->and($pedido->pool_reenvios)->toBe(0);

    Event::assertNotDispatched(DeliveryOrderPoolUpdated::class);
});

test('el comando reporta las estadisticas de la pasada', function () {
    ($this->crearPedidoAceptado)();
    ($this->crearPedidoAceptado)(['hora_aceptado' => now()->subMinutes(5)]);

    $exitCode = ($this->ejecutarTimeout)();

    expect($exitCode)->toBe(0);

    $output = Artisan::output();

    expect($output)->toContain('Pedidos revisados: 2')
        ->and($output)->toContain('Liberados por timeout: 1');
});

test('incrementa pool_reenvios en cada liberacion sucesiva', function () {
    $pedido = ($this->crearPedidoAceptado)();

    ($this->ejecutarTimeout)();
    $pedido->refresh();

    expect($pedido->pool_reenvios)->toBe(1);

    $pedido->update(['repartidor_id' => ($this->crearRepartidor)()->id]);
    $pedido->update(['hora_aceptado' => now()->subMinutes(15)]);

    ($this->ejecutarTimeout)();
    $pedido->refresh();

    expect($pedido->pool_reenvios)->toBe(2)
        ->and($pedido->repartidor_id)->toBeNull();
});
