<?php

use App\Models\DeliveryAsignacion;
use App\Models\Pedido;
use App\Models\Repartidor;
use App\Models\User;
use App\Models\Zone;
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

test('la asignacion automatica registra el historial con su zona', function () {
    ($this->crearPedidoPool)();

    Artisan::call('delivery:auto-assign');

    $registro = DeliveryAsignacion::where('pedido_id', Pedido::first()->id)->first();

    expect($registro)->not->toBeNull()
        ->and((int) $registro->repartidor_id)->toBe($this->repartidorUser->id)
        ->and((int) $registro->zona_id)->toBe($this->zona->id)
        ->and($registro->asignador_id)->toBeNull()
        ->and($registro->motivo)->toBe('auto_asignado');
});

test('la asignacion manual registra quien asigno y la zona', function () {
    $pedido = ($this->crearPedidoPool)();

    Sanctum::actingAs($this->usuario);

    $this->postJson("/api/v1/zones/pool/{$pedido->id}/asignar", [
        'zona_id' => $this->zona->id,
    ])->assertStatus(200);

    $registro = DeliveryAsignacion::where('pedido_id', $pedido->id)->first();

    expect($registro)->not->toBeNull()
        ->and((int) $registro->repartidor_id)->toBe($this->repartidorUser->id)
        ->and((int) $registro->zona_id)->toBe($this->zona->id)
        ->and((int) $registro->asignador_id)->toBe($this->usuario->id)
        ->and($registro->motivo)->toBe('asignado_manual');
});

test('aceptar del pool registra el historial con la zona detectada', function () {
    $pedido = ($this->crearPedidoPool)();

    Sanctum::actingAs($this->repartidorUser);

    $this->postJson("/api/v1/delivery/orders/{$pedido->id}/accept")->assertStatus(200);

    $registro = DeliveryAsignacion::where('pedido_id', $pedido->id)->first();

    expect($registro)->not->toBeNull()
        ->and((int) $registro->repartidor_id)->toBe($this->repartidorUser->id)
        ->and((int) $registro->zona_id)->toBe($this->zona->id)
        ->and((int) $registro->asignador_id)->toBe($this->repartidorUser->id)
        ->and($registro->motivo)->toBe('aceptado_pool');
});

test('aceptar del pool registra sin zona cuando no hay ninguna que lo cubra', function () {
    $pedido = ($this->crearPedidoPool)([
        'destino_lat' => -33.05,
        'destino_lng' => -70.9,
    ]);

    Sanctum::actingAs($this->repartidorUser);

    $this->postJson("/api/v1/delivery/orders/{$pedido->id}/accept")->assertStatus(200);

    $registro = DeliveryAsignacion::where('pedido_id', $pedido->id)->first();

    expect($registro)->not->toBeNull()
        ->and($registro->zona_id)->toBeNull()
        ->and($registro->motivo)->toBe('aceptado_pool');
});

test('el historial queda aislado por tenant', function () {
    $pedidoPropio = ($this->crearPedidoPool)();
    DeliveryAsignacion::factory()->create([
        'owner_id' => $this->owner->id,
        'pedido_id' => $pedidoPropio->id,
        'repartidor_id' => $this->repartidorUser->id,
    ]);

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
    DeliveryAsignacion::factory()->create([
        'owner_id' => $otroOwner->id,
        'pedido_id' => Pedido::factory()->create([
            'owner_id' => $otroOwner->id,
            'user_id' => $otroOwner->id,
            'estado' => 'preparando',
        ])->id,
        'repartidor_id' => $otroRepartidorUser->id,
    ]);

    expect(DeliveryAsignacion::count())->toBe(2);

    Sanctum::actingAs($this->usuario);

    expect(DeliveryAsignacion::count())->toBe(1);
});
