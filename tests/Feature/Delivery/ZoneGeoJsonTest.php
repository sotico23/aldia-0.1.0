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
});

test('geojson requiere autenticacion', function () {
    $this->getJson('/api/v1/zones/geojson')->assertStatus(401);
});

test('geojson devuelve las zonas como puntos con estadisticas', function () {
    Pedido::factory()->create([
        'owner_id' => $this->owner->id,
        'user_id' => $this->owner->id,
        'estado' => 'preparando',
        'destino_lat' => -33.452,
        'destino_lng' => -70.665,
    ]);

    Sanctum::actingAs($this->usuario);

    $response = $this->getJson('/api/v1/zones/geojson')
        ->assertStatus(200)
        ->assertJsonPath('type', 'FeatureCollection');

    $features = $response->json('features');
    $zonas = collect($features)->where('properties.tipo', 'zona')->values();

    expect($zonas)->toHaveCount(1);

    $zona = $zonas->first();

    expect($zona['geometry']['type'])->toBe('Point')
        ->and($zona['geometry']['coordinates'])->toBe([-70.6693, -33.4489])
        ->and($zona['properties']['name'])->toBe('Centro')
        ->and($zona['properties']['radio_km'])->toBe(10)
        ->and($zona['properties']['capacidad_max'])->toBe(10)
        ->and($zona['properties']['activa'])->toBeTrue()
        ->and($zona['properties']['pedidos_en_zona'])->toBe(1)
        ->and($zona['properties']['uso_percent'])->toBe(10);
});

test('geojson incluye los repartidores con posicion y pedidos activos', function () {
    $repartidorUser = User::factory()->create([
        'creator_id' => $this->owner->id,
        'has_explicit_role' => true,
    ]);
    $repartidorUser->assignRole('Repartidor');

    $repartidor = Repartidor::factory()->create([
        'owner_id' => $this->owner->id,
        'user_id' => $repartidorUser->id,
        'estado' => 'ocupado',
        'lat' => -33.45,
        'lng' => -70.66,
        'radio_km' => 8,
    ]);

    Pedido::factory()->create([
        'owner_id' => $this->owner->id,
        'user_id' => $this->owner->id,
        'estado' => 'preparando',
        'repartidor_id' => $repartidorUser->id,
        'hora_aceptado' => now(),
        'destino_lat' => -33.452,
        'destino_lng' => -70.665,
    ]);

    Repartidor::factory()->create([
        'owner_id' => $this->owner->id,
        'user_id' => User::factory()->create([
            'creator_id' => $this->owner->id,
            'has_explicit_role' => true,
        ])->id,
        'estado' => 'disponible',
        'lat' => null,
        'lng' => null,
    ]);

    Sanctum::actingAs($this->usuario);

    $response = $this->getJson('/api/v1/zones/geojson')
        ->assertStatus(200);

    $repartidores = collect($response->json('features'))
        ->where('properties.tipo', 'repartidor')
        ->values();

    expect($repartidores)->toHaveCount(1);

    $repartidorFeature = $repartidores->first();

    expect($repartidorFeature['geometry']['coordinates'])->toBe([-70.66, -33.45])
        ->and($repartidorFeature['properties']['id'])->toBe($repartidor->id)
        ->and($repartidorFeature['properties']['user_id'])->toBe($repartidorUser->id)
        ->and($repartidorFeature['properties']['estado'])->toBe('ocupado')
        ->and($repartidorFeature['properties']['radio_km'])->toBe(8)
        ->and($repartidorFeature['properties']['pedidos_activos'])->toBe(1);
});

test('geojson solo considera zonas y pedidos del mismo tenant', function () {
    $otroOwner = User::factory()->create();
    Zone::factory()->create([
        'owner_id' => $otroOwner->id,
        'name' => 'Zona ajena',
    ]);

    $repartidorOtro = User::factory()->create([
        'creator_id' => $otroOwner->id,
        'has_explicit_role' => true,
    ]);
    $repartidorOtro->assignRole('Repartidor');

    Repartidor::factory()->create([
        'owner_id' => $otroOwner->id,
        'user_id' => $repartidorOtro->id,
        'lat' => -30.0,
        'lng' => -70.0,
    ]);

    Sanctum::actingAs($this->usuario);

    $response = $this->getJson('/api/v1/zones/geojson')
        ->assertStatus(200);

    $features = $response->json('features');

    expect(collect($features)->where('properties.tipo', 'zona'))->toHaveCount(1)
        ->and(collect($features)->where('properties.tipo', 'repartidor'))->toHaveCount(0);
});
