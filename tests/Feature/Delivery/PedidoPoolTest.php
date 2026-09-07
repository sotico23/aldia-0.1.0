<?php

use App\Events\DeliveryOrderPoolUpdated;
use App\Models\DeliveryConfig;
use App\Models\Pedido;
use App\Models\PedidoStatusLog;
use App\Models\Repartidor;
use App\Models\User;
use App\Services\PedidoPoolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'Repartidor', 'guard_name' => 'web']);
    Permission::findOrCreate('comercial.oportunidades.viewAny', 'web');

    // Primer usuario creado se convierte en Super Admin; se crea un dummy
    // para absorber ese rol y que los demas usuarios queden en niveles bajos.
    User::factory()->create(['email' => 'dummy-setup@test.local']);

    $this->owner = User::factory()->create();

    $this->repartidorUser = User::factory()->create([
        'creator_id' => $this->owner->id,
        'has_explicit_role' => true,
    ]);
    $this->repartidorUser->assignRole('Repartidor');

    $this->repartidor = Repartidor::factory()->create([
        'owner_id' => $this->owner->id,
        'user_id' => $this->repartidorUser->id,
        'estado' => 'disponible',
        'lat' => -33.45,
        'lng' => -70.66,
        'radio_km' => 10,
    ]);

    $this->actingAsRepartidor = function () {
        Sanctum::actingAs($this->repartidorUser);
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

    $this->crearPedidoAsignado = function (array $overrides = []) {
        return Pedido::factory()->create(array_merge([
            'owner_id' => $this->owner->id,
            'user_id' => $this->owner->id,
            'estado' => 'preparando',
            'destino_lat' => -33.452,
            'destino_lng' => -70.665,
            'repartidor_id' => $this->repartidorUser->id,
            'hora_aceptado' => now()->subMinutes(15),
            'pool_entrada_at' => now()->subMinutes(20),
        ], $overrides));
    };
});

// ============================================================================
// Entrada al pool al confirmar (PedidoPoolService::ingresarAlPool)
// ============================================================================

test('el pedido entra al pool cuando el vendedor lo pasa a preparando', function () {
    $this->owner->givePermissionTo('comercial.oportunidades.viewAny');

    $pedido = Pedido::factory()->create([
        'owner_id' => $this->owner->id,
        'user_id' => $this->owner->id,
        'estado' => 'confirmado',
    ]);

    Event::fake([DeliveryOrderPoolUpdated::class]);

    $this->actingAs($this->owner)
        ->put(route('pedidos-recibidos.estado', $pedido), ['estado' => 'preparando'])
        ->assertRedirect();

    $pedido->refresh();

    expect($pedido->estado)->toBe('preparando')
        ->and($pedido->pool_entrada_at)->not->toBeNull()
        ->and($pedido->pool_reenvios)->toBe(0);

    Event::assertDispatched(DeliveryOrderPoolUpdated::class, function ($event) use ($pedido) {
        return $event->motivo === 'nuevo'
            && $event->pedido->id === $pedido->id
            && $event->pedido->repartidor_id === null;
    });

    $pedido->refresh();
    expect(Pedido::disponibleEnPool()->whereKey($pedido->id)->exists())->toBeTrue();
});

test('cambiar a otros estados no mete el pedido al pool', function () {
    $this->owner->givePermissionTo('comercial.oportunidades.viewAny');

    $pedido = Pedido::factory()->create([
        'owner_id' => $this->owner->id,
        'user_id' => $this->owner->id,
        'estado' => 'pendiente',
    ]);

    Event::fake([DeliveryOrderPoolUpdated::class]);

    $this->actingAs($this->owner)
        ->put(route('pedidos-recibidos.estado', $pedido), ['estado' => 'confirmado'])
        ->assertRedirect();

    $pedido->refresh();

    expect($pedido->pool_entrada_at)->toBeNull();

    Event::assertNotDispatched(DeliveryOrderPoolUpdated::class);
});

test('ingresarAlPool rehabilita un pedido bloqueado del ciclo anterior', function () {
    $pedido = Pedido::factory()->create([
        'owner_id' => $this->owner->id,
        'user_id' => $this->owner->id,
        'estado' => 'preparando',
        'pool_entrada_at' => now()->subDay(),
        'pool_bloqueado' => true,
    ]);

    Event::fake([DeliveryOrderPoolUpdated::class]);

    app(PedidoPoolService::class)->ingresarAlPool($pedido);

    $pedido->refresh();

    expect($pedido->pool_bloqueado)->toBeFalse()
        ->and($pedido->pool_entrada_at)->not->toBeNull();

    Event::assertDispatched(DeliveryOrderPoolUpdated::class, fn ($event) => $event->motivo === 'nuevo');
});

test('ingresarAlPool no actua sobre pedidos ya asignados', function () {
    $pedido = ($this->crearPedidoAsignado)();

    Event::fake([DeliveryOrderPoolUpdated::class]);

    app(PedidoPoolService::class)->ingresarAlPool($pedido);

    $pedido->refresh();

    expect((int) $pedido->repartidor_id)->toBe($this->repartidorUser->id);

    Event::assertNotDispatched(DeliveryOrderPoolUpdated::class);
});

// ============================================================================
// Limite de reenvios (pool_reenvios_max)
// ============================================================================

test('reject respeta el limite de reenvios y bloquea el pedido', function () {
    DeliveryConfig::factory()->create([
        'owner_id' => $this->owner->id,
        'pool_reenvios_max' => 3,
        'pool_reenvio_min' => 30,
    ]);

    $pedido = ($this->crearPedidoAsignado)();

    ($this->actingAsRepartidor)();

    foreach ([1, 2] as $reenvio) {
        $this->postJson("/api/v1/delivery/orders/{$pedido->id}/reject", ['motivo' => 'Dirección lejana'])
            ->assertStatus(200)
            ->assertJsonPath('resultado', 'reenviado');

        $pedido->refresh();
        expect($pedido->pool_reenvios)->toBe($reenvio)
            ->and($pedido->pool_bloqueado)->toBeFalse();
    }

    $this->postJson("/api/v1/delivery/orders/{$pedido->id}/reject", ['motivo' => 'Dirección lejana'])
        ->assertStatus(200)
        ->assertJsonPath('resultado', 'bloqueado')
        ->assertJsonPath('message', 'Pedido retirado del pool por límite de reenvíos.');

    $pedido->refresh();

    expect($pedido->pool_reenvios)->toBe(3)
        ->and($pedido->pool_bloqueado)->toBeTrue()
        ->and($pedido->repartidor_id)->toBeNull();

    expect(Pedido::disponibleEnPool()->whereKey($pedido->id)->exists())->toBeFalse();

    $log = PedidoStatusLog::where('pedido_id', $pedido->id)
        ->where('field', 'repartidor')
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->to)->toBe('bloqueado')
        ->and($log->motivo)->toBe('bloqueado_Dirección lejana');
});

test('sin limite configurado los reenvios son ilimitados', function () {
    DeliveryConfig::factory()->create([
        'owner_id' => $this->owner->id,
        'pool_reenvios_max' => null,
    ]);

    $pedido = ($this->crearPedidoAsignado)();

    ($this->actingAsRepartidor)();

    foreach (range(1, 5) as $reenvio) {
        $this->postJson("/api/v1/delivery/orders/{$pedido->id}/reject", ['motivo' => 'Motivo '.$reenvio])
            ->assertJsonPath('resultado', 'reenviado');

        $pedido->refresh();
        expect($pedido->pool_reenvios)->toBe($reenvio);
    }

    expect($pedido->pool_bloqueado)->toBeFalse();
});

test('el timeout respeta el limite de reenvios y bloquea el pedido', function () {
    DeliveryConfig::factory()->create([
        'owner_id' => $this->owner->id,
        'pool_reenvios_max' => 1,
    ]);

    $pedido = ($this->crearPedidoAsignado)();

    Event::fake([DeliveryOrderPoolUpdated::class]);

    Artisan::call('delivery:pool-timeout');

    $pedido->refresh();

    expect($pedido->pool_bloqueado)->toBeTrue()
        ->and($pedido->repartidor_id)->toBeNull()
        ->and($pedido->pool_reenvios)->toBe(1);

    Event::assertDispatched(DeliveryOrderPoolUpdated::class, fn ($event) => $event->motivo === 'bloqueado');
});

test('un pedido bloqueado no se puede aceptar', function () {
    DeliveryConfig::factory()->create([
        'owner_id' => $this->owner->id,
        'pool_reenvios_max' => 1,
    ]);

    $pedido = ($this->crearPedidoAsignado)();

    Artisan::call('delivery:pool-timeout');

    ($this->actingAsRepartidor)();

    $this->postJson("/api/v1/delivery/orders/{$pedido->id}/accept")
        ->assertStatus(409)
        ->assertJsonPath('message', 'El pedido ya no está disponible en el pool.');
});

// ============================================================================
// Cooldown de reingreso (pool_reenvio_min)
// ============================================================================

test('el cooldown oculta el pedido del pool hasta que pasa el tiempo', function () {
    DeliveryConfig::factory()->create([
        'owner_id' => $this->owner->id,
        'pool_reenvio_min' => 60,
    ]);

    $pedido = ($this->crearPedidoAsignado)();

    ($this->actingAsRepartidor)();

    $this->postJson("/api/v1/delivery/orders/{$pedido->id}/reject", ['motivo' => 'Lejos'])
        ->assertStatus(200);

    $pedido->refresh();

    expect($pedido->pool_visible_at)->not->toBeNull();

    $this->getJson('/api/v1/delivery/orders')
        ->assertStatus(200)
        ->assertJsonCount(0, 'pool');

    $this->postJson("/api/v1/delivery/orders/{$pedido->id}/accept")->assertStatus(409);

    $this->travel(61)->minutes();

    expect(Pedido::disponibleEnPool()->whereKey($pedido->id)->exists())->toBeTrue();

    $this->getJson('/api/v1/delivery/orders')
        ->assertStatus(200)
        ->assertJsonCount(1, 'pool');
});

test('availability libera sin contar reenvios pero aplica cooldown', function () {
    DeliveryConfig::factory()->create([
        'owner_id' => $this->owner->id,
        'pool_reenvio_min' => 45,
        'pool_reenvios_max' => 1,
    ]);

    $pedido = ($this->crearPedidoAsignado)();

    ($this->actingAsRepartidor)();

    Event::fake([DeliveryOrderPoolUpdated::class]);

    $this->postJson('/api/v1/delivery/availability', ['estado' => 'ocupado'])
        ->assertStatus(200)
        ->assertJsonPath('pedido_liberado', $pedido->id);

    $pedido->refresh();

    expect($pedido->pool_reenvios)->toBe(0)
        ->and($pedido->pool_bloqueado)->toBeFalse()
        ->and($pedido->pool_visible_at)->not->toBeNull();

    Event::assertDispatched(DeliveryOrderPoolUpdated::class, fn ($event) => $event->motivo === 'liberado');
});

// ============================================================================
// Exposicion de configuracion
// ============================================================================

test('me expone el limite de reenvios configurado', function () {
    DeliveryConfig::factory()->create([
        'owner_id' => $this->owner->id,
        'pool_reenvios_max' => 5,
    ]);

    ($this->actingAsRepartidor)();

    $this->getJson('/api/v1/delivery/me')
        ->assertStatus(200)
        ->assertJsonPath('config.pool_reenvios_max', 5);
});
