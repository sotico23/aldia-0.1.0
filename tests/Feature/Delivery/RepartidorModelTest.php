<?php

use App\Models\DeliveryConfig;
use App\Models\DeliveryPosition;
use App\Models\Pedido;
use App\Models\PedidoStatusLog;
use App\Models\PushSubscription;
use App\Models\Repartidor;
use App\Models\User;
use App\Scopes\OwnerScope;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// El primer usuario creado en cada test se convierte en Super Admin
// (User::boot); se crea uno dummy para absorber ese rol y que los demas
// usuarios queden como 'Usuario' y respeten el OwnerScope.
beforeEach(function () {
    User::factory()->create(['email' => 'dummy-setup@test.local']);
});

// ============================================================================
// Repartidor
// ============================================================================

test('repartidor factory creates a valid repartidor', function () {
    $repartidor = Repartidor::factory()->create();

    expect($repartidor->id)->toBeInt();
    expect($repartidor->user_id)->not->toBeNull();
    expect($repartidor->estado)->toBe('disponible');
    expect($repartidor->radio_km)->toBe('10.00');
    expect($repartidor->lat)->not->toBeNull();
    expect($repartidor->lng)->not->toBeNull();
});

test('repartidor belongs to its user and positions', function () {
    $user = User::factory()->create();
    $repartidor = Repartidor::factory()->create(['user_id' => $user->id]);

    DeliveryPosition::factory()->create(['repartidor_id' => $repartidor->id]);

    expect($repartidor->user->id)->toBe($user->id);
    expect($repartidor->positions)->toHaveCount(1);
});

test('isDisponible returns true only for disponible state', function () {
    expect(Repartidor::factory()->create(['estado' => 'disponible'])->isDisponible())->toBeTrue();
    expect(Repartidor::factory()->create(['estado' => 'ocupado'])->isDisponible())->toBeFalse();
    expect(Repartidor::factory()->create(['estado' => 'offline'])->isDisponible())->toBeFalse();
});

test('repartidores are isolated by tenant', function () {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    Repartidor::factory()->create(['owner_id' => $ownerA->id]);

    $this->actingAs($ownerA);

    expect(Repartidor::count())->toBe(1);

    $this->actingAs($ownerB);

    expect(Repartidor::count())->toBe(0);
});

// ============================================================================
// Pool scope de Pedido
// ============================================================================

test('disponibleEnPool returns only preparando orders without repartidor', function () {
    $owner = User::factory()->create();

    $pool = Pedido::factory()->create([
        'owner_id' => $owner->id,
        'user_id' => $owner->id,
        'estado' => 'preparando',
    ]);

    Pedido::factory()->create([
        'owner_id' => $owner->id,
        'user_id' => $owner->id,
        'estado' => 'confirmado',
    ]);

    Pedido::factory()->create([
        'owner_id' => $owner->id,
        'user_id' => $owner->id,
        'estado' => 'preparando',
        'repartidor_id' => $owner->id,
        'hora_aceptado' => now(),
    ]);

    $results = Pedido::disponibleEnPool()->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($pool->id);
});

test('disponibleEnPool is isolated by tenant', function () {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    Pedido::factory()->create([
        'owner_id' => $ownerA->id,
        'user_id' => $ownerA->id,
        'estado' => 'preparando',
    ]);

    Pedido::factory()->create([
        'owner_id' => $ownerB->id,
        'user_id' => $ownerB->id,
        'estado' => 'preparando',
    ]);

    $this->actingAs($ownerA);

    expect(Pedido::disponibleEnPool()->count())->toBe(1);
});

test('pedido exposes repartidor relation and delivery fields', function () {
    $owner = User::factory()->create();
    $repartidor = User::factory()->create(['creator_id' => $owner->id]);
    $pedido = Pedido::factory()->create([
        'owner_id' => $owner->id,
        'user_id' => $owner->id,
        'estado' => 'enviado',
        'repartidor_id' => $repartidor->id,
        'destino_lat' => -33.4489,
        'destino_lng' => -70.6693,
        'distancia_km' => 5.5,
        'hora_aceptado' => now(),
    ]);

    expect($pedido->repartidor->id)->toBe($repartidor->id);
    expect($pedido->destino_lat)->toBe(-33.4489);
    expect($pedido->destino_lng)->toBe(-70.6693);
    expect((string) $pedido->distancia_km)->toBe('5.50');
});

// ============================================================================
// PedidoStatusLog - field estado
// ============================================================================

test('estado change logs an audit entry with field estado', function () {
    $owner = User::factory()->create();
    $pedido = Pedido::factory()->create([
        'owner_id' => $owner->id,
        'user_id' => $owner->id,
        'estado' => 'confirmado',
    ]);

    PedidoStatusLog::where('field', 'estado')->delete();

    $pedido->update(['estado' => 'preparando']);

    $log = PedidoStatusLog::where('pedido_id', $pedido->id)
        ->where('field', 'estado')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->from)->toBe('confirmado');
    expect($log->to)->toBe('preparando');
    expect($log->changed_by)->toBeNull();
});

test('estado log is not written when estado does not change', function () {
    $owner = User::factory()->create();
    $pedido = Pedido::factory()->create([
        'owner_id' => $owner->id,
        'user_id' => $owner->id,
        'estado' => 'confirmado',
    ]);

    PedidoStatusLog::where('field', 'estado')->delete();

    $pedido->update(['estado' => 'confirmado']);

    expect(PedidoStatusLog::where('field', 'estado')->count())->toBe(0);
});

// ============================================================================
// DeliveryConfig / PushSubscription / DeliveryPosition
// ============================================================================

test('delivery config factory creates config with default mode', function () {
    $config = DeliveryConfig::factory()->create();

    expect($config->modo)->toBe('ambos');
    expect($config->pool_timeout_min)->toBe(10);
    expect($config->pool_reenvio_min)->toBe(30);
});

test('delivery config is unique per owner', function () {
    $owner = User::factory()->create();

    DeliveryConfig::factory()->create(['owner_id' => $owner->id]);

    expect(fn () => DeliveryConfig::factory()->create(['owner_id' => $owner->id]))
        ->toThrow(QueryException::class);
});

test('push subscription stores endpoint and keys', function () {
    $subscription = PushSubscription::factory()->create();

    expect($subscription->endpoint)->toStartWith('https://fcm.googleapis.com/');
    expect($subscription->keys_json)->toBeArray();
    expect(array_key_exists('p256dh', $subscription->keys_json))->toBeTrue();
});

test('delivery position belongs to repartidor', function () {
    $repartidor = Repartidor::factory()->create();
    $position = DeliveryPosition::factory()->create(['repartidor_id' => $repartidor->id]);

    expect($position->repartidor->id)->toBe($repartidor->id);
});

test('delivery positions are isolated by tenant', function () {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();
    $repartidorA = Repartidor::factory()->create(['owner_id' => $ownerA->id]);

    DeliveryPosition::factory()->create(['owner_id' => $ownerA->id, 'repartidor_id' => $repartidorA->id]);

    $this->actingAs($ownerB);

    expect(DeliveryPosition::count())->toBe(0);
});
