<?php

use App\Events\DeliveryOrderPoolUpdated;
use App\Events\DeliveryPositionUpdated;
use App\Models\DeliveryConfig;
use App\Models\DeliveryPosition;
use App\Models\Pedido;
use App\Models\PedidoStatusLog;
use App\Models\Repartidor;
use App\Models\User;
use App\Notifications\ActualizacionEstadoPedidoNotification;
use App\Notifications\DeliveryPedidoAceptadoNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'Repartidor', 'guard_name' => 'web']);

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
});

// ============================================================================
// Autenticacion y autorizacion
// ============================================================================

test('delivery endpoints requieren autenticacion', function () {
    $this->getJson('/api/v1/delivery/me')->assertStatus(401);
});

test('delivery endpoints rechazan usuarios sin rol Repartidor', function () {
    $usuario = User::factory()->create();
    Sanctum::actingAs($usuario);

    $this->getJson('/api/v1/delivery/me')->assertStatus(403);
});

test('delivery endpoints rechazan repartidor sin perfil activo', function () {
    $sinPerfil = User::factory()->create([
        'creator_id' => $this->owner->id,
        'has_explicit_role' => true,
    ]);
    $sinPerfil->assignRole('Repartidor');
    Sanctum::actingAs($sinPerfil);

    $this->getJson('/api/v1/delivery/me')->assertStatus(403);
});

// ============================================================================
// POST /location
// ============================================================================

test('location actualiza posicion, guarda historico y emite broadcast', function () {
    ($this->actingAsRepartidor)();

    Event::fake();

    $response = $this->postJson('/api/v1/delivery/location', [
        'lat' => -33.4489,
        'lng' => -70.6693,
    ]);

    $response->assertStatus(200)
        ->assertJson(['success' => true, 'estado' => 'disponible']);

    $this->repartidor->refresh();
    expect((float) $this->repartidor->lat)->toBe(-33.4489);
    expect((float) $this->repartidor->lng)->toBe(-70.6693);
    expect($this->repartidor->last_position_at)->not->toBeNull();

    expect(DeliveryPosition::count())->toBe(1);

    Event::assertDispatched(DeliveryPositionUpdated::class, function ($event) {
        return $event->position->repartidor_id === $this->repartidor->id;
    });
});

test('location valida el rango de coordenadas', function () {
    ($this->actingAsRepartidor)();

    $this->postJson('/api/v1/delivery/location', [
        'lat' => 999,
        'lng' => -70.66,
    ])->assertStatus(422);
});

// ============================================================================
// GET /me
// ============================================================================

test('me devuelve perfil, config del pool y pedido activo', function () {
    DeliveryConfig::factory()->create([
        'owner_id' => $this->owner->id,
        'modo' => 'pool',
    ]);

    $pedido = ($this->crearPedidoPool)([
        'repartidor_id' => $this->repartidorUser->id,
        'hora_aceptado' => now(),
    ]);

    ($this->actingAsRepartidor)();

    $this->getJson('/api/v1/delivery/me')
        ->assertStatus(200)
        ->assertJsonPath('repartidor.estado', 'disponible')
        ->assertJsonPath('repartidor.radio_km', '10.00')
        ->assertJsonPath('config.modo', 'pool')
        ->assertJsonPath('pedido_activo.id', $pedido->id);
});

// ============================================================================
// POST /availability
// ============================================================================

test('availability libera el pedido activo al pasar a ocupado', function () {
    $pedido = ($this->crearPedidoPool)([
        'repartidor_id' => $this->repartidorUser->id,
        'hora_aceptado' => now(),
    ]);

    ($this->actingAsRepartidor)();

    Event::fake();

    $this->postJson('/api/v1/delivery/availability', ['estado' => 'ocupado'])
        ->assertStatus(200)
        ->assertJsonPath('estado', 'ocupado')
        ->assertJsonPath('pedido_liberado', $pedido->id);

    $pedido->refresh();
    expect($pedido->repartidor_id)->toBeNull();
    expect($pedido->hora_aceptado)->toBeNull();
    expect($pedido->pool_entrada_at)->not->toBeNull();

    Event::assertDispatched(DeliveryOrderPoolUpdated::class, fn ($event) => $event->motivo === 'liberado');
});

test('availability no libera pedidos ya en envio', function () {
    $pedido = ($this->crearPedidoPool)([
        'estado' => 'enviado',
        'repartidor_id' => $this->repartidorUser->id,
        'hora_aceptado' => now(),
        'hora_recogido' => now(),
    ]);

    ($this->actingAsRepartidor)();

    $this->postJson('/api/v1/delivery/availability', ['estado' => 'ocupado'])
        ->assertStatus(200);

    $pedido->refresh();
    expect((int) $pedido->repartidor_id)->toBe($this->repartidorUser->id);
});

// ============================================================================
// GET /orders
// ============================================================================

test('orders devuelve solo pedidos del pool dentro del radio', function () {
    ($this->crearPedidoPool)(); // -33.452,-70.665 ~ 0.5km
    ($this->crearPedidoPool)([
        'destino_lat' => -33.05,
        'destino_lng' => -70.9,
    ]); // ~49km, fuera del radio de 10km
    ($this->crearPedidoPool)([
        'destino_lat' => null,
        'destino_lng' => null,
    ]); // sin coordenadas, no puede calcularse distancia

    ($this->actingAsRepartidor)();

    $response = $this->getJson('/api/v1/delivery/orders')
        ->assertStatus(200);

    $pool = $response->json('pool');
    expect($pool)->toHaveCount(1);
    expect($pool[0]['distancia_km'])->toBeLessThan(10);
});

test('orders incluye los pedidos activos del repartidor', function () {
    $pedidoActivo = ($this->crearPedidoPool)([
        'estado' => 'enviado',
        'repartidor_id' => $this->repartidorUser->id,
        'hora_aceptado' => now(),
        'hora_recogido' => now(),
    ]);

    ($this->actingAsRepartidor)();

    $this->getJson('/api/v1/delivery/orders')
        ->assertStatus(200)
        ->assertJsonPath('pedidos_activos.0.id', $pedidoActivo->id);
});

test('orders oculta el pool cuando el modo de asignacion es manual', function () {
    DeliveryConfig::factory()->create([
        'owner_id' => $this->owner->id,
        'modo' => 'manual',
    ]);

    ($this->crearPedidoPool)();

    ($this->actingAsRepartidor)();

    $this->getJson('/api/v1/delivery/orders')
        ->assertStatus(200)
        ->assertJsonCount(0, 'pool');
});

// ============================================================================
// POST /orders/{pedido}/accept
// ============================================================================

test('accept asigna el pedido al repartidor sin cambiar el estado', function () {
    $pedido = ($this->crearPedidoPool)();

    ($this->actingAsRepartidor)();

    Event::fake();
    Notification::fake();

    $this->postJson("/api/v1/delivery/orders/{$pedido->id}/accept")
        ->assertStatus(200)
        ->assertJsonPath('pedido.repartidor_id', $this->repartidorUser->id);

    $pedido->refresh();
    expect((int) $pedido->repartidor_id)->toBe($this->repartidorUser->id);
    expect($pedido->estado)->toBe('preparando');
    expect($pedido->hora_aceptado)->not->toBeNull();

    Event::assertDispatched(DeliveryOrderPoolUpdated::class, fn ($event) => $event->motivo === 'aceptado');
    Notification::assertSentTo($this->owner, DeliveryPedidoAceptadoNotification::class);
});

test('accept rechaza la doble aceptacion del mismo pedido', function () {
    $pedido = ($this->crearPedidoPool)();

    ($this->actingAsRepartidor)();

    $this->postJson("/api/v1/delivery/orders/{$pedido->id}/accept")->assertStatus(200);
    $this->postJson("/api/v1/delivery/orders/{$pedido->id}/accept")
        ->assertStatus(409)
        ->assertJsonPath('message', 'El pedido ya no está disponible en el pool.');
});

test('accept rechaza pedidos fuera del estado del pool', function () {
    $pedido = ($this->crearPedidoPool)(['estado' => 'confirmado']);

    ($this->actingAsRepartidor)();

    $this->postJson("/api/v1/delivery/orders/{$pedido->id}/accept")->assertStatus(409);
});

test('accept requiere que el repartidor este disponible', function () {
    $pedido = ($this->crearPedidoPool)();
    $this->repartidor->update(['estado' => 'ocupado']);

    ($this->actingAsRepartidor)();

    $this->postJson("/api/v1/delivery/orders/{$pedido->id}/accept")
        ->assertStatus(409)
        ->assertJsonPath('message', 'Debes estar disponible para aceptar repartos.');
});

// ============================================================================
// POST /orders/{pedido}/pickup y /delivered
// ============================================================================

test('pickup solo puede ejecutarlo el repartidor asignado', function () {
    $pedido = ($this->crearPedidoPool)();
    $otroRepartidorUser = User::factory()->create([
        'creator_id' => $this->owner->id,
        'has_explicit_role' => true,
    ]);
    $otroRepartidorUser->assignRole('Repartidor');
    Repartidor::factory()->create([
        'owner_id' => $this->owner->id,
        'user_id' => $otroRepartidorUser->id,
        'estado' => 'disponible',
        'lat' => -33.45,
        'lng' => -70.66,
    ]);
    Sanctum::actingAs($otroRepartidorUser);

    $this->postJson("/api/v1/delivery/orders/{$pedido->id}/pickup")->assertStatus(403);
});

test('pickup cambia a enviado y notifica al comprador', function () {
    $cliente = User::factory()->create();
    $pedido = ($this->crearPedidoPool)([
        'repartidor_id' => $this->repartidorUser->id,
        'hora_aceptado' => now(),
        'cliente_id' => $cliente->id,
    ]);

    ($this->actingAsRepartidor)();

    Notification::fake();

    $this->postJson("/api/v1/delivery/orders/{$pedido->id}/pickup")
        ->assertStatus(200)
        ->assertJsonPath('pedido.estado', 'enviado');

    $pedido->refresh();
    expect($pedido->estado)->toBe('enviado');
    expect($pedido->hora_recogido)->not->toBeNull();

    Notification::assertSentTo($cliente, ActualizacionEstadoPedidoNotification::class);
});

test('delivered solo puede ejecutarlo el repartidor asignado', function () {
    $pedido = ($this->crearPedidoPool)(['estado' => 'enviado']);

    ($this->actingAsRepartidor)();

    $this->postJson("/api/v1/delivery/orders/{$pedido->id}/delivered")
        ->assertStatus(403)
        ->assertJsonPath('message', 'Este reparto no está asignado a ti.');
});

test('delivered completa el pedido y libera la disponibilidad', function () {
    $cliente = User::factory()->create();
    $pedido = ($this->crearPedidoPool)([
        'estado' => 'enviado',
        'repartidor_id' => $this->repartidorUser->id,
        'hora_aceptado' => now(),
        'hora_recogido' => now(),
        'cliente_id' => $cliente->id,
    ]);
    $this->repartidor->update(['estado' => 'ocupado']);

    ($this->actingAsRepartidor)();

    Notification::fake();

    $this->postJson("/api/v1/delivery/orders/{$pedido->id}/delivered")
        ->assertStatus(200)
        ->assertJsonPath('pedido.estado', 'entregado');

    $pedido->refresh();
    expect($pedido->estado)->toBe('entregado');
    expect($pedido->fecha_entrega)->not->toBeNull();
    expect($pedido->hora_entregado)->not->toBeNull();

    $this->repartidor->refresh();
    expect($this->repartidor->estado)->toBe('disponible');

    Notification::assertSentTo($cliente, ActualizacionEstadoPedidoNotification::class);
});

// ============================================================================
// POST /orders/{pedido}/reject
// ============================================================================

test('reject devuelve el pedido al pool con log y motivo', function () {
    $pedido = ($this->crearPedidoPool)([
        'repartidor_id' => $this->repartidorUser->id,
        'hora_aceptado' => now(),
    ]);

    ($this->actingAsRepartidor)();

    Event::fake();

    $this->postJson("/api/v1/delivery/orders/{$pedido->id}/reject", [
        'motivo' => 'Dirección lejana',
    ])->assertStatus(200);

    $pedido->refresh();
    expect($pedido->repartidor_id)->toBeNull();
    expect($pedido->hora_aceptado)->toBeNull();
    expect($pedido->pool_entrada_at)->not->toBeNull();
    expect($pedido->pool_reenvios)->toBe(1);

    $log = PedidoStatusLog::where('pedido_id', $pedido->id)
        ->where('field', 'repartidor')
        ->first();
    expect($log)->not->toBeNull();
    expect($log->from)->toBe((string) $this->repartidorUser->id);
    expect($log->to)->toBe('pool');
    expect($log->motivo)->toBe('Dirección lejana');

    Event::assertDispatched(DeliveryOrderPoolUpdated::class, fn ($event) => $event->motivo === 'rechazado');
});

test('reject no puede ejecutarlo un repartidor distinto al asignado', function () {
    $otroRepartidorUser = User::factory()->create([
        'creator_id' => $this->owner->id,
        'has_explicit_role' => true,
    ]);
    $otroRepartidorUser->assignRole('Repartidor');
    Repartidor::factory()->create([
        'owner_id' => $this->owner->id,
        'user_id' => $otroRepartidorUser->id,
        'estado' => 'disponible',
    ]);

    $pedido = ($this->crearPedidoPool)([
        'repartidor_id' => $this->repartidorUser->id,
        'hora_aceptado' => now(),
    ]);

    Sanctum::actingAs($otroRepartidorUser);

    $this->postJson("/api/v1/delivery/orders/{$pedido->id}/reject")->assertStatus(403);
});

// ============================================================================
// Aislamiento multi-tenant
// ============================================================================

test('los pedidos quedan aislados por tenant', function () {
    $otroOwner = User::factory()->create();
    $otroRepartidorUser = User::factory()->create([
        'creator_id' => $otroOwner->id,
        'has_explicit_role' => true,
    ]);
    $otroRepartidorUser->assignRole('Repartidor');
    Repartidor::factory()->create([
        'owner_id' => $otroOwner->id,
        'user_id' => $otroRepartidorUser->id,
        'estado' => 'disponible',
    ]);

    $pedidoAjeno = ($this->crearPedidoPool)();

    Sanctum::actingAs($otroRepartidorUser);

    $this->getJson('/api/v1/delivery/orders')
        ->assertStatus(200)
        ->assertJsonCount(0, 'pool');

    $this->postJson("/api/v1/delivery/orders/{$pedidoAjeno->id}/accept")->assertStatus(404);
});
