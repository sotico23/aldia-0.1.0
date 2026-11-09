<?php

use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['comercial.productos.viewAny', 'comercial.productos.create'] as $permission) {
        Permission::firstOrCreate([
            'name' => $permission,
            'guard_name' => 'web',
        ]);
    }

    $this->user = User::factory()->create();
    $this->user->syncRoles([]);
    $this->user->givePermissionTo([
        'comercial.productos.viewAny',
        'comercial.productos.create',
    ]);
});

test('barcode lookup maps Open Food Facts data and sets a descriptive user agent', function () {
    Http::preventStrayRequests();
    Http::fake([
        'world.openfoodfacts.org/api/v2/product/7801234567890.json' => Http::response([
            'status' => 1,
            'product' => [
                'product_name' => 'Generic product',
                'product_name_es' => 'Producto de prueba',
                'brands' => 'Marca de prueba',
                'categories' => 'Bebidas, Jugos',
                'categories_tags' => ['es:bebidas'],
                'image_front_url' => 'https://images.openfoodfacts.org/images/products/front.jpg',
                'image_url' => 'https://images.openfoodfacts.org/images/products/main.jpg',
                'quantity' => '500 ml',
            ],
        ]),
    ]);

    $this->actingAs($this->user)
        ->getJson(route('api.v1.productos.barcode', ['ean' => '7801234567890']))
        ->assertSuccessful()
        ->assertExactJson([
            'encontrado' => true,
            'origen' => 'api_externa',
            'datos' => [
                'nombre' => 'Producto de prueba',
                'marca' => 'Marca de prueba',
                'categoria' => 'Bebidas, Jugos',
                'imagen' => 'https://images.openfoodfacts.org/images/products/front.jpg',
                'formato' => '500 ml',
            ],
        ]);

    Http::assertSent(fn ($request) => $request->hasHeader('User-Agent')
        && str_contains($request->header('User-Agent')[0], 'Web/1.0')
        && $request->url() === 'https://world.openfoodfacts.org/api/v2/product/7801234567890.json');
});

test('barcode lookup returns a matching local product without contacting Open Food Facts', function () {
    Http::preventStrayRequests();
    Http::fake();

    Producto::factory()->create([
        'user_id' => $this->user->id,
        'owner_id' => $this->user->getOwnerId(),
        'codigo' => '7801234567890',
        'nombre' => 'Producto local',
        'contenido_por_unidad' => 2,
        'unidad_medida' => 'kg',
    ]);

    $this->actingAs($this->user)
        ->getJson(route('api.v1.productos.barcode', ['ean' => '7801234567890']))
        ->assertSuccessful()
        ->assertJsonPath('encontrado', true)
        ->assertJsonPath('origen', 'base_datos')
        ->assertJsonPath('datos.nombre', 'Producto local')
        ->assertJsonPath('datos.formato', '2 kg');

    Http::assertNothingSent();
});

test('barcode lookup returns not found when Open Food Facts has no product', function () {
    Http::preventStrayRequests();
    Http::fake([
        'world.openfoodfacts.org/*' => Http::response([
            'status' => 0,
            'status_verbose' => 'product not found',
        ]),
    ]);

    $this->actingAs($this->user)
        ->getJson(route('api.v1.productos.barcode', ['ean' => '7801234567890']))
        ->assertNotFound()
        ->assertExactJson([
            'encontrado' => false,
            'origen' => 'api_externa',
            'datos' => null,
        ]);
});

test('barcode lookup reports upstream failures and rejects invalid barcode lengths', function () {
    Http::preventStrayRequests();
    Http::fake([
        'world.openfoodfacts.org/*' => Http::response([], 503),
    ]);

    $this->actingAs($this->user)
        ->getJson(route('api.v1.productos.barcode', ['ean' => '7801234567890']))
        ->assertStatus(502)
        ->assertJsonPath('encontrado', false)
        ->assertJsonPath('origen', 'api_externa');

    $this->actingAs($this->user)
        ->getJson(route('api.v1.productos.barcode', ['ean' => '123456789']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('ean');
});

test('barcode lookup reports connection failures without exposing upstream details', function () {
    Http::preventStrayRequests();
    Http::fake(fn () => throw new ConnectionException('Connection timed out'));

    $this->actingAs($this->user)
        ->getJson(route('api.v1.productos.barcode', ['ean' => '7801234567890']))
        ->assertStatus(502)
        ->assertJsonPath('mensaje', 'No fue posible consultar Open Food Facts. Inténtalo nuevamente.');
});

test('barcode lookup requires product creation permission', function () {
    $userWithoutCreatePermission = User::factory()->create();
    $userWithoutCreatePermission->syncRoles([]);
    $userWithoutCreatePermission->givePermissionTo('comercial.productos.viewAny');

    $this->actingAs($userWithoutCreatePermission)
        ->getJson(route('api.v1.productos.barcode', ['ean' => '7801234567890']))
        ->assertForbidden();
});
