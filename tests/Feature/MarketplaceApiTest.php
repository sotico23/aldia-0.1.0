<?php

use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Inventario;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\PublicProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function eatsStore(array $overrides = []): PublicProfile
{
    $owner = User::factory()->create();

    return PublicProfile::factory()->create(array_merge([
        'user_id' => $owner->id,
        'owner_id' => $owner->getOwnerId(),
        'slug' => 'tienda-eats',
        'title' => 'Tienda Eats',
        'is_active' => true,
    ], $overrides));
}

function eatsProduct(PublicProfile $store, array $overrides = []): Producto
{
    return Producto::factory()->create(array_merge([
        'user_id' => $store->user_id,
        'owner_id' => $store->owner_id,
        'public_profile_id' => $store->id,
        'nombre' => 'Pizza Eats',
        'precio_venta' => 10000,
        'activo' => true,
        'mostrar_en_perfil' => true,
    ], $overrides));
}

test('the stores endpoint only returns active shops even for an authenticated buyer', function () {
    $store = eatsStore();
    eatsStore(['slug' => 'tienda-cerrada', 'is_active' => false]);

    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/marketplace/stores')
        ->assertOk()
        ->assertJsonCount(1, 'stores')
        ->assertJsonPath('stores.0.slug', $store->slug)
        ->assertJsonPath('stores.0.title', 'Tienda Eats');
});

test('the products endpoint exposes the live storefront set and filters it', function () {
    $store = eatsStore();
    eatsProduct($store, ['nombre' => 'Pizza Margarita']);
    eatsProduct($store, ['nombre' => 'Empanada de pino', 'public_profile_id' => null]);
    eatsProduct(eatsStore(['slug' => 'otra-tienda']), ['nombre' => 'Producto ajeno']);

    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/marketplace/stores/tienda-eats/products')
        ->assertOk()
        ->assertJsonCount(2, 'productos')
        ->assertJsonPath('meta.total', 2);

    $this->getJson('/api/v1/marketplace/stores/tienda-eats/products?search=pizza')
        ->assertOk()
        ->assertJsonCount(1, 'productos')
        ->assertJsonPath('productos.0.nombre', 'Pizza Margarita');
});

test('the store detail returns categories, warehouses and stock', function () {
    $store = eatsStore();
    $category = Categoria::factory()->create([
        'user_id' => $store->user_id,
        'owner_id' => $store->owner_id,
        'public_profile_id' => $store->id,
        'nombre' => 'Pizzas',
        'activo' => true,
    ]);
    $warehouse = Almacen::factory()->create([
        'user_id' => $store->user_id,
        'owner_id' => $store->owner_id,
        'activo' => true,
    ]);
    $product = eatsProduct($store, ['categoria_id' => $category->id]);
    Inventario::factory()->create([
        'producto_id' => $product->id,
        'owner_id' => $store->owner_id,
        'almacen_id' => $warehouse->id,
        'cantidad' => 25,
    ]);

    $this->getJson('/api/v1/marketplace/stores/tienda-eats')
        ->assertOk()
        ->assertJsonPath('categorias.0.nombre', 'Pizzas')
        ->assertJsonPath('almacenes.0.id', $warehouse->id);

    $this->getJson('/api/v1/marketplace/stores/tienda-eats/products')
        ->assertOk()
        ->assertJsonPath('productos.0.categoria_nombre', 'Pizzas')
        ->assertJsonPath('productos.0.stock', 25);
});

test('an authenticated buyer can place an order and see it in their list', function () {
    $store = eatsStore();
    $product = eatsProduct($store);
    $buyer = User::factory()->create();
    Sanctum::actingAs($buyer);

    $response = $this->postJson('/api/v1/marketplace/orders', [
        'public_profile_id' => $store->id,
        'nombre_cliente' => 'Camila González',
        'telefono_cliente' => '+56 9 1234 5678',
        'direccion_entrega' => 'Av. Providencia 2321',
        'metodo_pago' => 'Efectivo',
        'items' => [
            ['producto_id' => $product->id, 'cantidad' => 2],
        ],
    ])->assertCreated();

    $response->assertJsonPath('order.total', 20000)
        ->assertJsonPath('order.items.0.cantidad', 2)
        ->assertJsonPath('order.estado', 'pendiente');

    $this->assertDatabaseCount('pedidos', 1);
    $this->assertDatabaseCount('pedido_items', 1);

    $this->getJson('/api/v1/marketplace/orders')
        ->assertOk()
        ->assertJsonCount(1, 'orders')
        ->assertJsonPath('orders.0.store_name', 'Tienda Eats');
});

test('a buyer cannot cancel another buyer order and can cancel their own', function () {
    $store = eatsStore();
    $buyer = User::factory()->create();
    $other = User::factory()->create();
    $order = Pedido::factory()->create([
        'user_id' => $store->user_id,
        'owner_id' => $store->owner_id,
        'public_profile_id' => $store->id,
        'cliente_id' => $buyer->id,
        'estado' => 'pendiente',
    ]);

    Sanctum::actingAs($other);
    $this->postJson("/api/v1/marketplace/orders/{$order->id}/cancel")->assertNotFound();

    Sanctum::actingAs($buyer);
    $this->postJson("/api/v1/marketplace/orders/{$order->id}/cancel")
        ->assertOk()
        ->assertJsonPath('order.estado', 'cancelado');
});
