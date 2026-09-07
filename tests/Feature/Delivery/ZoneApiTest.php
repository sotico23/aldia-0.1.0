<?php

use App\Models\Pedido;
use App\Models\Repartidor;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    $this->crearPedido = function (array $overrides = []) {
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
// Autenticacion y aislamiento
// ============================================================================

test('zonas requieren autenticacion', function () {
    $this->getJson('/api/v1/zones')->assertStatus(401);
    $this->postJson('/api/v1/zones', [])->assertStatus(401);
});

test('zonas quedan aisladas por tenant', function () {
    $otroOwner = User::factory()->create();
    Zone::factory()->create([
        'owner_id' => $otroOwner->id,
        'name' => 'Zona ajena',
    ]);

    Sanctum::actingAs($this->usuario);

    $this->getJson('/api/v1/zones')
        ->assertStatus(200)
        ->assertJsonCount(1, 'zonas')
        ->assertJsonPath('zonas.0.name', 'Centro');
});

test('una zona ajena no puede actualizarse ni eliminarse', function () {
    $otroOwner = User::factory()->create();
    $zonaAjena = Zone::factory()->create([
        'owner_id' => $otroOwner->id,
    ]);

    Sanctum::actingAs($this->usuario);

    $this->putJson("/api/v1/zones/{$zonaAjena->id}", ['name' => 'Hack'])->assertStatus(404);
    $this->deleteJson("/api/v1/zones/{$zonaAjena->id}")->assertStatus(404);
});

// ============================================================================
// CRUD
// ============================================================================

test('store crea una zona', function () {
    Sanctum::actingAs($this->usuario);

    $this->postJson('/api/v1/zones', [
        'name' => 'Norte',
        'lat' => -33.35,
        'lng' => -70.74,
        'radio_km' => 8,
        'capacidad_max' => 15,
    ])
        ->assertStatus(201)
        ->assertJsonPath('zona.name', 'Norte')
        ->assertJsonPath('zona.owner_id', $this->owner->id);
});

test('store valida las coordenadas y capacidades', function () {
    Sanctum::actingAs($this->usuario);

    $this->postJson('/api/v1/zones', [
        'name' => 'Mala',
        'lat' => 999,
        'lng' => -70.74,
        'radio_km' => 8,
        'capacidad_max' => 15,
    ])->assertStatus(422);

    $this->postJson('/api/v1/zones', [
        'name' => 'Mala',
        'lat' => -33.35,
        'lng' => -70.74,
        'radio_km' => 0.1,
        'capacidad_max' => 0,
    ])->assertStatus(422);
});

test('update modifica una zona propia', function () {
    Sanctum::actingAs($this->usuario);

    $this->putJson("/api/v1/zones/{$this->zona->id}", [
        'name' => 'Centro Ampliado',
        'radio_km' => 15,
        'activa' => false,
    ])
        ->assertStatus(200)
        ->assertJsonPath('zona.name', 'Centro Ampliado');

    $this->zona->refresh();
    expect($this->zona->name)->toBe('Centro Ampliado');
    expect((float) $this->zona->radio_km)->toBe(15.0);
    expect($this->zona->activa)->toBeFalse();
});

test('destroy elimina una zona propia', function () {
    Sanctum::actingAs($this->usuario);

    $this->deleteJson("/api/v1/zones/{$this->zona->id}")->assertStatus(200);

    expect(Zone::find($this->zona->id))->toBeNull();
});

// ============================================================================
// Analitica
// ============================================================================

test('resumen calcula pedidos dentro del radio y porcentaje de uso', function () {
    // 2 pedidos dentro del radio de la zona (Centro)
    ($this->crearPedido)();
    ($this->crearPedido)(['destino_lat' => -33.454, 'destino_lng' => -70.661]);

    // 1 pedido fuera del radio (~50km al norte) y 1 sin coordenadas
    ($this->crearPedido)(['destino_lat' => -33.05, 'destino_lng' => -70.9]);
    ($this->crearPedido)(['destino_lat' => null, 'destino_lng' => null]);

    Repartidor::factory()->create([
        'owner_id' => $this->owner->id,
        'user_id' => $this->usuario->id,
        'estado' => 'disponible',
        'lat' => -33.4489,
        'lng' => -70.6693,
    ]);

    Sanctum::actingAs($this->usuario);

    $this->getJson('/api/v1/zones/resumen')
        ->assertStatus(200)
        ->assertJsonCount(1, 'zonas')
        ->assertJsonPath('zonas.0.pedidos_en_zona', 2)
        ->assertJsonPath('zonas.0.pedidos_activos', 2)
        ->assertJsonPath('zonas.0.uso_percent', 20)
        ->assertJsonPath('total_pedidos_activos', 3)
        ->assertJsonPath('total_repartidores', 1)
        ->assertJsonPath('repartidores_disponibles', 1);
});

test('resumen solo considera pedidos del mismo tenant', function () {
    ($this->crearPedido)();

    $otroOwner = User::factory()->create();
    Pedido::factory()->create([
        'owner_id' => $otroOwner->id,
        'user_id' => $otroOwner->id,
        'estado' => 'preparando',
        'destino_lat' => -33.452,
        'destino_lng' => -70.665,
    ]);

    Sanctum::actingAs($this->usuario);

    $this->getJson('/api/v1/zones/resumen')
        ->assertStatus(200)
        ->assertJsonPath('total_pedidos_activos', 1);
});

test('resumen respeta el modo del cache al crear una zona', function () {
    $ownerNuevo = User::factory()->create();
    $usuarioNuevo = User::factory()->create([
        'creator_id' => $ownerNuevo->id,
        'has_explicit_role' => true,
    ]);

    Sanctum::actingAs($usuarioNuevo);

    $this->postJson('/api/v1/zones', [
        'name' => 'Cache Test',
        'lat' => -33.4,
        'lng' => -70.6,
        'radio_km' => 5,
        'capacidad_max' => 8,
    ])->assertStatus(201);

    $this->getJson('/api/v1/zones/resumen')
        ->assertStatus(200)
        ->assertJsonPath('zonas.0.name', 'Cache Test')
        ->assertJsonCount(1, 'zonas');
});

test('index incluye el estado de uso de cada zona', function () {
    ($this->crearPedido)();

    Sanctum::actingAs($this->usuario);

    $this->getJson('/api/v1/zones')
        ->assertStatus(200)
        ->assertJsonPath('zonas.0.uso.pedidos_en_zona', 1)
        ->assertJsonPath('zonas.0.uso.uso_percent', 10);
});
