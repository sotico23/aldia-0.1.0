<?php

use App\Models\DeliveryRate;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionsSeeder::class);
    User::factory()->create(['email' => 'zone-test-super-admin@example.test']);
    $this->owner = User::factory()->create([
        'email' => 'zone-test-owner@example.test',
        'has_explicit_role' => true,
    ]);
    $this->owner->givePermissionTo([
        'delivery.zonas-reparto.viewAny',
        'delivery.zonas-reparto.create',
        'delivery.zonas-reparto.edit',
    ]);
});

test('a zone can be created with commune based delivery rates without map coordinates', function () {
    $this->actingAs($this->owner)
        ->post('/zonas-reparto', [
            'name' => 'Santiago Centro - Express',
            'modo' => 'comunas',
            'capacidad_max' => 12,
            'activa' => true,
            'comunas' => [
                ['comuna' => 'Santiago', 'precio' => 2500],
                ['comuna' => 'Providencia', 'precio' => 3000],
            ],
        ])
        ->assertRedirect();

    $zona = Zone::query()->where('name', 'Santiago Centro - Express')->firstOrFail();

    expect($zona->modo)->toBe('comunas')
        ->and($zona->lat)->toBeNull()
        ->and($zona->lng)->toBeNull()
        ->and($zona->radio_km)->toBeNull()
        ->and($zona->deliveryRates)->toHaveCount(2);

    $this->assertDatabaseHas('delivery_rates', [
        'owner_id' => $this->owner->id,
        'zone_id' => $zona->id,
        'calculation_type' => 'comuna',
        'commune' => 'Santiago',
        'cost' => 2500,
        'is_active' => true,
    ]);

    $this->actingAs($this->owner)
        ->get('/zonas-reparto')
        ->assertInertia(fn ($page) => $page
            ->component('Backend/ZonaReparto/Index')
            ->where('zonas.0.modo', 'comunas')
            ->where('zonas.0.comunas.0.comuna', 'Santiago')
            ->where('comunas_disponibles.0', 'Providencia'));
});

test('updating a commune based zone updates prices and disables removed communes', function () {
    $zona = Zone::factory()->create([
        'owner_id' => $this->owner->id,
        'name' => 'Zona histórica',
        'modo' => 'comunas',
        'lat' => null,
        'lng' => null,
        'radio_km' => null,
    ]);
    $tarifaAntigua = DeliveryRate::create([
        'owner_id' => $this->owner->id,
        'zone_id' => $zona->id,
        'calculation_type' => 'comuna',
        'commune' => 'Santiago',
        'cost' => 1800,
        'is_active' => true,
    ]);

    $this->actingAs($this->owner)
        ->put("/zonas-reparto/{$zona->id}", [
            'name' => 'Zona histórica',
            'modo' => 'comunas',
            'capacidad_max' => 10,
            'activa' => true,
            'comunas' => [
                ['comuna' => 'Providencia', 'precio' => 3200],
            ],
        ])
        ->assertRedirect();

    expect($tarifaAntigua->fresh()->is_active)->toBeFalse();

    $this->assertDatabaseHas('delivery_rates', [
        'owner_id' => $this->owner->id,
        'zone_id' => $zona->id,
        'commune' => 'Providencia',
        'cost' => 3200,
        'is_active' => true,
    ]);
});

test('commune based zone validation requires a commune and a nonnegative numeric price', function () {
    $this->actingAs($this->owner)
        ->post('/zonas-reparto', [
            'name' => 'Zona inválida',
            'modo' => 'comunas',
            'capacidad_max' => 10,
            'activa' => true,
            'comunas' => [
                ['comuna' => '', 'precio' => 'no-es-un-precio'],
            ],
        ])
        ->assertSessionHasErrors([
            'comunas.0.comuna',
            'comunas.0.precio',
        ]);

    expect(Zone::query()->count())->toBe(0);
});

test('commune based zones are not treated as geographic coverage', function () {
    $zona = Zone::factory()->create([
        'owner_id' => $this->owner->id,
        'modo' => 'comunas',
        'lat' => null,
        'lng' => null,
        'radio_km' => null,
    ]);

    expect($zona->contiene(-33.4489, -70.6693))->toBeFalse()
        ->and(is_infinite($zona->distanciaA(-33.4489, -70.6693)))->toBeTrue();
});
