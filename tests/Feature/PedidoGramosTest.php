<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Inventario;
use App\Models\PedidoItem;
use App\Models\Producto;
use App\Models\PublicProfile;
use App\Models\User;

beforeEach(function () {
    $this->customer = User::factory()->create();
    $this->storeOwner = User::factory()->create();
    $this->categoria = Categoria::factory()->create();
    $this->publicProfile = PublicProfile::factory()->create([
        'user_id' => $this->storeOwner->id,
        'owner_id' => $this->storeOwner->id,
    ]);
    $this->producto = Producto::factory()->create([
        'codigo' => 'PROD-MKT-GRAMOS',
        'nombre' => 'Producto Marketplace Grameable',
        'precio_venta' => 2000,
        'unidad_medida' => 'kg',
        'categoria_id' => $this->categoria->id,
        'owner_id' => $this->storeOwner->id,
        'user_id' => $this->storeOwner->id,
        'public_profile_id' => $this->publicProfile->id,
    ]);
    $this->almacen = Almacen::factory()->create([
        'owner_id' => $this->storeOwner->id,
        'activo' => true,
    ]);
    Inventario::factory()->create([
        'producto_id' => $this->producto->id,
        'almacen_id' => $this->almacen->id,
        'cantidad' => 1000,
        'owner_id' => $this->storeOwner->id,
    ]);
});

test('crear accepts decimal quantity in kg', function () {
    $response = $this->actingAs($this->customer)
        ->post(route('tienda.checkout', ['slug' => $this->publicProfile->slug]), [
            'public_profile_id' => $this->publicProfile->id,
            'items' => [
                [
                    'producto_id' => $this->producto->id,
                    'cantidad' => 0.500,
                ],
            ],
            'nombre_cliente' => 'Cliente Test',
            'telefono_cliente' => '+56 9 12345678',
            'direccion_cliente' => 'Dirección Test 123',
        ]);

    $response->assertSessionDoesntHaveErrors();
    $response->assertStatus(302);

    $this->assertDatabaseHas('pedido_items', [
        'producto_id' => $this->producto->id,
        'cantidad' => 0.500,
    ]);
});

test('crear accepts integer quantity', function () {
    $response = $this->actingAs($this->customer)
        ->post(route('tienda.checkout', ['slug' => $this->publicProfile->slug]), [
            'public_profile_id' => $this->publicProfile->id,
            'items' => [
                [
                    'producto_id' => $this->producto->id,
                    'cantidad' => 3,
                ],
            ],
            'nombre_cliente' => 'Cliente Test',
        ]);

    $response->assertSessionDoesntHaveErrors();
    $response->assertStatus(302);

    $this->assertDatabaseHas('pedido_items', [
        'producto_id' => $this->producto->id,
        'cantidad' => 3,
    ]);
});

test('crear rejects quantity below 0.001', function () {
    $response = $this->actingAs($this->customer)
        ->post(route('tienda.checkout', ['slug' => $this->publicProfile->slug]), [
            'public_profile_id' => $this->publicProfile->id,
            'items' => [
                [
                    'producto_id' => $this->producto->id,
                    'cantidad' => 0.0001,
                ],
            ],
            'nombre_cliente' => 'Cliente Test',
        ]);

    $response->assertStatus(302);
    $response->assertSessionHasErrors(['items.0.cantidad']);
});

test('crear rejects negative quantity', function () {
    $response = $this->actingAs($this->customer)
        ->post(route('tienda.checkout', ['slug' => $this->publicProfile->slug]), [
            'public_profile_id' => $this->publicProfile->id,
            'items' => [
                [
                    'producto_id' => $this->producto->id,
                    'cantidad' => -1,
                ],
            ],
            'nombre_cliente' => 'Cliente Test',
        ]);

    $response->assertStatus(302);
    $response->assertSessionHasErrors(['items.0.cantidad']);
});

test('crear stores decimal with 3 decimal precision in pedido_items', function () {
    $response = $this->actingAs($this->customer)
        ->post(route('tienda.checkout', ['slug' => $this->publicProfile->slug]), [
            'public_profile_id' => $this->publicProfile->id,
            'items' => [
                [
                    'producto_id' => $this->producto->id,
                    'cantidad' => 1.250,
                ],
            ],
            'nombre_cliente' => 'Cliente Precisión',
        ]);

    $response->assertSessionDoesntHaveErrors();
    $response->assertStatus(302);

    $item = PedidoItem::where('producto_id', $this->producto->id)->first();
    expect($item)->not->toBeNull();
    expect((float) $item->cantidad)->toBe(1.250);
});

test('crear calculates subtotal proportionally for decimal quantity', function () {
    $response = $this->actingAs($this->customer)
        ->post(route('tienda.checkout', ['slug' => $this->publicProfile->slug]), [
            'public_profile_id' => $this->publicProfile->id,
            'items' => [
                [
                    'producto_id' => $this->producto->id,
                    'cantidad' => 0.500,
                ],
            ],
            'nombre_cliente' => 'Cliente Subtotal',
        ]);

    $response->assertSessionDoesntHaveErrors();
    $response->assertStatus(302);

    $item = PedidoItem::where('producto_id', $this->producto->id)->first();
    expect($item)->not->toBeNull();
    // precio_venta = 2000, cantidad = 0.500 => 2000 * 0.500 = 1000
    expect((float) $item->subtotal)->toBe(1000.0);
});

test('crear accepts multiple items with decimal quantities', function () {
    $producto2 = Producto::factory()->create([
        'codigo' => 'PROD-MKT-DOS',
        'nombre' => 'Segundo Producto',
        'precio_venta' => 1000,
        'unidad_medida' => 'lt',
        'categoria_id' => $this->categoria->id,
        'owner_id' => $this->storeOwner->id,
        'user_id' => $this->storeOwner->id,
        'public_profile_id' => $this->publicProfile->id,
    ]);
    Inventario::factory()->create([
        'producto_id' => $producto2->id,
        'almacen_id' => $this->almacen->id,
        'cantidad' => 1000,
        'owner_id' => $this->storeOwner->id,
    ]);

    $response = $this->actingAs($this->customer)
        ->post(route('tienda.checkout', ['slug' => $this->publicProfile->slug]), [
            'public_profile_id' => $this->publicProfile->id,
            'items' => [
                [
                    'producto_id' => $this->producto->id,
                    'cantidad' => 0.500,
                ],
                [
                    'producto_id' => $producto2->id,
                    'cantidad' => 0.750,
                ],
            ],
            'nombre_cliente' => 'Cliente Multi Item',
        ]);

    $response->assertSessionDoesntHaveErrors();
    $response->assertStatus(302);

    $this->assertDatabaseHas('pedido_items', [
        'producto_id' => $this->producto->id,
        'cantidad' => 0.500,
    ]);
    $this->assertDatabaseHas('pedido_items', [
        'producto_id' => $producto2->id,
        'cantidad' => 0.750,
    ]);
});
