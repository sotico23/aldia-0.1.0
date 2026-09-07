<?php

use App\Events\DeliveryOrderPoolUpdated;
use App\Models\DeliveryConfig;
use App\Models\Pedido;
use App\Models\PedidoStatusLog;
use App\Models\Repartidor;
use App\Models\User;
use App\Models\Zone;
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

    $this->usuario = User::factory()->create([
        'creator_id' => $this->owner->id,
        'has_explicit_role' => true,
    ]);

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

test('la asignacion manual requiere autenticacion', function () {
    $this->postJson('/api/v1/zones/pool/1/asignar', ['repartidor_id' => 1])->assertStatus(401);
});

test('requiere repartidor_id o zona_id', function () {
    $pedido = ($this->crearPedidoPool)();

    Sanctum::actingAs($this->usuario);

    $this->postJson("/api/v1/zones/pool/{$pedido->id}/asignar", [])->assertStatus(422);
});

test('asigna manualmente el pedido del pool a un repartidor', function () {
    $repartidor = ($this->crearRepartidor)();
    $pedido = ($this->crearPedidoPool)();

    Sanctum::actingAs($this->usuario);

    Event::fake([DeliveryOrderPoolUpdated::class]);
    Notification::fake();

    $this->postJson("/api/v1/zones/pool/{$pedido->id}/asignar", [
        'repartidor_id' => $repartidor->user_id,
    ])
        ->assertStatus(200)
        ->assertJsonPath('message', 'Pedido asignado manualmente.')
        ->assertJsonPath('repartidor_id', $repartidor->id)
        ->assertJsonPath('pedido.repartidor_id', $repartidor->user_id);

    $pedido->refresh();

    expect((int) $pedido->repartidor_id)->toBe($repartidor->user_id)
        ->and($pedido->hora_aceptado)->not->toBeNull()
        ->and($pedido->estado)->toBe('preparando');

    expect(Pedido::disponibleEnPool()->whereKey($pedido->id)->exists())->toBeFalse();

    Event::assertDispatched(DeliveryOrderPoolUpdated::class, function ($event) use ($pedido, $repartidor) {
        return $event->motivo === 'asignado_manual'
            && $event->pedido->id === $pedido->id
            && $event->pedido->repartidor_id === $repartidor->user_id;
    });

    Notification::assertSentTo($this->owner, DeliveryPedidoAceptadoNotification::class);

    $log = PedidoStatusLog::where('pedido_id', $pedido->id)
        ->where('field', 'repartidor')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->from)->toBe('pool')
        ->and($log->to)->toBe((string) $repartidor->user_id)
        ->and($log->changed_by)->toBe($this->usuario->id)
        ->and($log->motivo)->toBe('asignado_manual');
});

test('no asigna a un repartidor de otra empresa', function () {
    $otroOwner = User::factory()->create();
    $otroUser = User::factory()->create([
        'creator_id' => $otroOwner->id,
        'has_explicit_role' => true,
    ]);
    $otroUser->assignRole('Repartidor');
    $repartidorAjeno = Repartidor::factory()->create([
        'owner_id' => $otroOwner->id,
        'user_id' => $otroUser->id,
        'estado' => 'disponible',
    ]);

    $pedido = ($this->crearPedidoPool)();

    Sanctum::actingAs($this->usuario);

    $this->postJson("/api/v1/zones/pool/{$pedido->id}/asignar", [
        'repartidor_id' => $repartidorAjeno->user_id,
    ])->assertStatus(404);
});

test('no asigna pedidos de otra empresa', function () {
    $otroOwner = User::factory()->create();
    $pedidoAjeno = Pedido::factory()->create([
        'owner_id' => $otroOwner->id,
        'user_id' => $otroOwner->id,
        'estado' => 'preparando',
        'destino_lat' => -33.452,
        'destino_lng' => -70.665,
    ]);

    $repartidor = ($this->crearRepartidor)();

    Sanctum::actingAs($this->usuario);

    $this->postJson("/api/v1/zones/pool/{$pedidoAjeno->id}/asignar", [
        'repartidor_id' => $repartidor->user_id,
    ])->assertStatus(404);
});

test('no asigna un pedido que ya salio del pool', function () {
    $repartidor = ($this->crearRepartidor)();
    $pedido = ($this->crearPedidoPool)([
        'repartidor_id' => $repartidor->user_id,
        'hora_aceptado' => now(),
    ]);

    Sanctum::actingAs($this->usuario);

    $this->postJson("/api/v1/zones/pool/{$pedido->id}/asignar", [
        'repartidor_id' => $repartidor->user_id,
    ])
        ->assertStatus(409)
        ->assertJsonPath('message', 'El pedido no está disponible en el pool.');
});

test('no asigna a un repartidor con un reparto activo', function () {
    $repartidor = ($this->crearRepartidor)();
    ($this->crearPedidoPool)([
        'repartidor_id' => $repartidor->user_id,
        'hora_aceptado' => now(),
    ]);

    $pedido = ($this->crearPedidoPool)();

    Sanctum::actingAs($this->usuario);

    $this->postJson("/api/v1/zones/pool/{$pedido->id}/asignar", [
        'repartidor_id' => $repartidor->user_id,
    ])
        ->assertStatus(409)
        ->assertJsonPath('message', 'El repartidor ya tiene un reparto activo.');
});

// ============================================================================
// Asignacion por zona
// ============================================================================

test('asigna por zona al repartidor disponible mas cercano', function () {
    $lejano = ($this->crearRepartidor)([
        'lat' => -33.3,
        'lng' => -70.8,
    ]);
    $cercano = ($this->crearRepartidor)(); // -33.4489,-70.6693

    $pedido = ($this->crearPedidoPool)();

    Sanctum::actingAs($this->usuario);

    $this->postJson("/api/v1/zones/pool/{$pedido->id}/asignar", [
        'zona_id' => $this->zona->id,
    ])
        ->assertStatus(200)
        ->assertJsonPath('repartidor_id', $cercano->id);

    $pedido->refresh();

    expect((int) $pedido->repartidor_id)->toBe($cercano->user_id);
    expect((int) $pedido->repartidor_id)->not->toBe($lejano->user_id);
});

test('no asigna por zona cuando el destino esta fuera de ella', function () {
    ($this->crearRepartidor)();

    $pedido = ($this->crearPedidoPool)([
        'destino_lat' => -33.05,
        'destino_lng' => -70.9,
    ]);

    Sanctum::actingAs($this->usuario);

    $this->postJson("/api/v1/zones/pool/{$pedido->id}/asignar", [
        'zona_id' => $this->zona->id,
    ])
        ->assertStatus(409)
        ->assertJsonPath('message', 'El destino del pedido está fuera de la zona seleccionada.');
});

test('no asigna por zona cuando la zona esta llena', function () {
    $repartidor = ($this->crearRepartidor)();
    $zonaLlena = Zone::factory()->create([
        'owner_id' => $this->owner->id,
        'name' => 'Llena',
        'lat' => -33.4489,
        'lng' => -70.6693,
        'radio_km' => 10,
        'capacidad_max' => 1,
    ]);

    // Pedido activo dentro de la zona (ya ocupa la capacidad)
    ($this->crearPedidoPool)([
        'repartidor_id' => $repartidor->user_id,
        'hora_aceptado' => now(),
    ]);

    $pedido = ($this->crearPedidoPool)();

    Sanctum::actingAs($this->usuario);

    $this->postJson("/api/v1/zones/pool/{$pedido->id}/asignar", [
        'zona_id' => $zonaLlena->id,
    ])
        ->assertStatus(409)
        ->assertJsonPath('message', 'La zona ha alcanzado su capacidad máxima.');
});

test('no asigna por zona sin repartidores disponibles', function () {
    $pedido = ($this->crearPedidoPool)();

    Sanctum::actingAs($this->usuario);

    $this->postJson("/api/v1/zones/pool/{$pedido->id}/asignar", [
        'zona_id' => $this->zona->id,
    ])
        ->assertStatus(409)
        ->assertJsonPath('message', 'No hay repartidores disponibles para asignar.');
});

test('una zona ajena no existe para asignar', function () {
    $otroOwner = User::factory()->create();
    $zonaAjena = Zone::factory()->create([
        'owner_id' => $otroOwner->id,
    ]);

    $pedido = ($this->crearPedidoPool)();

    Sanctum::actingAs($this->usuario);

    $this->postJson("/api/v1/zones/pool/{$pedido->id}/asignar", [
        'zona_id' => $zonaAjena->id,
    ])->assertStatus(404);
});

test('la asignacion manual funciona en modo manual del pool', function () {
    DeliveryConfig::factory()->create([
        'owner_id' => $this->owner->id,
        'modo' => 'manual',
    ]);

    $repartidor = ($this->crearRepartidor)();
    $pedido = ($this->crearPedidoPool)();

    Sanctum::actingAs($this->usuario);

    $this->postJson("/api/v1/zones/pool/{$pedido->id}/asignar", [
        'repartidor_id' => $repartidor->user_id,
    ])->assertStatus(200);

    $pedido->refresh();

    expect((int) $pedido->repartidor_id)->toBe($repartidor->user_id);
});
