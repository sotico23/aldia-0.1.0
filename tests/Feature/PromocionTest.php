<?php

use App\Models\Promocion;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;

test('guests cannot access promotions page', function () {
    $response = $this->get(route('pos.promociones'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit promotions page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('pos.promociones'));
    $response->assertOk();
});

test('promotions page shows created promotions', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $promocion = Promocion::factory()->create(['user_id' => $user->id, 'owner_id' => $user->id]);

    $response = $this->get(route('pos.promociones'));
    $response->assertOk();
    $response->assertSee($promocion->nombre);
});

test('users can create a promotion', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->withHeaders([
        'X-CSRF-TOKEN' => 'test-token',
    ])->post('/pos/promociones', [
        'nombre' => 'Test Promotion',
        'tipo' => 'porcentaje',
        'valor' => '15.00',
        'descripcion' => 'Test description',
        'skus' => [],
        'categoria_id' => '',
        'compra_minima' => '',
        'fecha_inicio' => '',
        'fecha_fin' => '',
        'activa' => true,
    ], ['X-CSRF-TOKEN' => 'test-token']);

    $this->assertNotEquals(419, $response->getStatusCode(), 'Got 419 CSRF error');

    $this->assertDatabaseHas('promocions', ['nombre' => 'Test Promotion', 'tipo' => 'porcentaje']);
});

test('users can update a promotion', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $promocion = Promocion::factory()->create(['user_id' => $user->id, 'owner_id' => $user->id]);

    $data = [
        'nombre' => 'Updated Promotion',
        'tipo' => 'precio_fijo',
        'valor' => '29990.00',
        'descripcion' => 'Updated description',
        'skus' => [],
        'categoria_id' => '',
        'compra_minima' => '',
        'fecha_inicio' => '',
        'fecha_fin' => '',
        'activa' => true,
    ];

    $this->withoutMiddleware(VerifyCsrfToken::class)->put(route('pos.promociones.update', $promocion), $data);

    $this->assertDatabaseHas('promocions', ['id' => $promocion->id, 'nombre' => 'Updated Promotion']);
});

test('users can toggle promotion active status', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $promocion = Promocion::factory()->create(['user_id' => $user->id, 'owner_id' => $user->id, 'activa' => true]);

    $this->withoutMiddleware(VerifyCsrfToken::class)->patch(route('pos.promociones.toggle', $promocion));
    $promocion->refresh();
    expect($promocion->activa)->toBeFalse();

    $this->withoutMiddleware(VerifyCsrfToken::class)->patch(route('pos.promociones.toggle', $promocion));
    $promocion->refresh();
    expect($promocion->activa)->toBeTrue();
});

test('users can delete a promotion', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $promocion = Promocion::factory()->create(['user_id' => $user->id, 'owner_id' => $user->id]);
    $id = $promocion->id;

    $this->withoutMiddleware(VerifyCsrfToken::class)->delete(route('pos.promociones.destroy', $promocion));

    $this->assertDatabaseMissing('promocions', ['id' => $id]);
});

test('promotion store validates required fields', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->withoutMiddleware(VerifyCsrfToken::class)->post(route('pos.promociones.store'), []);
    $response->assertInvalid(['nombre', 'tipo', 'valor']);
});

test('promotion store validates tipo field', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $data = [
        'nombre' => 'Test',
        'tipo' => 'invalid_type',
        'valor' => '10',
    ];

    $response = $this->withoutMiddleware(VerifyCsrfToken::class)->post(route('pos.promociones.store'), $data);
    $response->assertInvalid(['tipo']);
});

test('welcome page shows active promotions', function () {
    $user = User::factory()->create();

    $activePromo = Promocion::factory()->create([
        'user_id' => $user->id,
        'owner_id' => $user->id,
        'nombre' => 'Active Promo',
        'tipo' => 'porcentaje',
        'activa' => true,
        'fecha_inicio' => now()->subDay(),
        'fecha_fin' => now()->addDay(),
    ]);

    $inactivePromo = Promocion::factory()->create([
        'user_id' => $user->id,
        'owner_id' => $user->id,
        'nombre' => 'Inactive Promo',
        'activa' => false,
    ]);

    $response = $this->get(route('home'));
    $response->assertOk();
    $response->assertSee('Active Promo');
    $response->assertDontSee('Inactive Promo');
});

test('promotion with skus stores array correctly', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $data = [
        'nombre' => 'Pack Promo',
        'tipo' => 'precio_fijo',
        'valor' => '49990',
        'descripcion' => 'Combo pack',
        'skus' => ['SKU-001', 'SKU-002', 'SKU-003'],
        'categoria_id' => '',
        'compra_minima' => '',
        'fecha_inicio' => '',
        'fecha_fin' => '',
        'activa' => true,
    ];

    $this->withoutMiddleware(VerifyCsrfToken::class)->post(route('pos.promociones.store'), $data);

    $promocion = Promocion::where('nombre', 'Pack Promo')->first();
    expect($promocion->skus)->toBe(['SKU-001', 'SKU-002', 'SKU-003']);
});

test('promotion with empty skus array stores as null', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $data = [
        'nombre' => 'No SKUs Promo',
        'tipo' => 'porcentaje',
        'valor' => '10',
        'skus' => [],
        'categoria_id' => '',
        'compra_minima' => '',
        'fecha_inicio' => '',
        'fecha_fin' => '',
        'activa' => true,
    ];

    $this->withoutMiddleware(VerifyCsrfToken::class)->post(route('pos.promociones.store'), $data);

    $promocion = Promocion::where('nombre', 'No SKUs Promo')->first();
    expect($promocion->skus)->toBeNull();
});
