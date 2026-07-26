<?php

use App\Enums\PaymentStatus;
use App\Models\Categoria;
use App\Models\PaymentConfig;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\PublicProfile;
use App\Models\User;
use App\Scopes\OwnerScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::create(['name' => 'Master', 'guard_name' => 'web']);

    $this->vendor = User::factory()->create();
    $this->buyer = User::factory()->create();

    $this->publicProfile = PublicProfile::factory()->create([
        'user_id' => $this->vendor->id,
        'owner_id' => $this->vendor->id,
        'is_active' => true,
    ]);

    $this->categoria = Categoria::factory()->create([
        'user_id' => $this->vendor->id,
        'owner_id' => $this->vendor->id,
        'activo' => true,
        'mostrar_en_perfil' => true,
        'public_profile_id' => $this->publicProfile->id,
    ]);

    $this->producto = Producto::factory()->create([
        'user_id' => $this->vendor->id,
        'owner_id' => $this->vendor->id,
        'categoria_id' => $this->categoria->id,
        'public_profile_id' => $this->publicProfile->id,
        'precio_venta' => 10000,
        'activo' => true,
        'mostrar_en_perfil' => true,
    ]);
});

test('marketplace show page includes paypal payment option when configured', function () {
    PaymentConfig::create([
        'owner_id' => $this->vendor->id,
        'commerce_code' => 'PRESET',
        'api_key' => 'PRESET',
        'paypal_client_id' => 'test-client-id',
        'paypal_client_secret' => 'test-secret',
        'paypal_mode' => 'sandbox',
        'paypal_active' => true,
    ]);

    $response = $this->actingAs($this->buyer)
        ->get(route('marketplace.show', $this->publicProfile->slug));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('marketplace/show')
        ->has('paymentConfig')
        ->where('paymentConfig.paypal_active', true)
        ->missing('paymentConfig.paypal_client_secret')
        ->missing('paymentConfig.paypal_client_id')
    );
});

test('marketplace show page does not include paypal when not configured', function () {
    $response = $this->actingAs($this->buyer)
        ->get(route('marketplace.show', $this->publicProfile->slug));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('marketplace/show')
        ->where('paymentConfig', null)
    );
});

test('checkout with paypal creates pedido and redirects to paypal pay route', function () {
    PaymentConfig::create([
        'owner_id' => $this->vendor->id,
        'commerce_code' => 'PRESET',
        'api_key' => 'PRESET',
        'paypal_client_id' => 'test-client-id',
        'paypal_client_secret' => 'test-secret',
        'paypal_mode' => 'sandbox',
        'paypal_active' => true,
    ]);

    $response = $this->actingAs($this->buyer)
        ->post(route('tienda.checkout', $this->publicProfile->slug), [
            'public_profile_id' => $this->publicProfile->id,
            'items' => [
                ['producto_id' => $this->producto->id, 'cantidad' => 2],
            ],
            'nombre_cliente' => 'Test Buyer',
            'telefono_cliente' => '+56912345678',
            'direccion_cliente' => 'Calle Test 123',
            'metodo_pago' => 'paypal',
        ]);

    $pedido = Pedido::withoutGlobalScope(OwnerScope::class)->latest()->first();

    expect($pedido)->not->toBeNull()
        ->and($pedido->metodo_pago)->toBe('paypal')
        ->and($pedido->estado)->toBe('pendiente')
        ->and($pedido->owner_id)->toBe($this->vendor->id)
        ->and($pedido->conversacion)->not->toBeNull();

    $response->assertRedirect(route('paypal.pay', ['pedidoId' => $pedido->id]));
});

test('paypal pay creates order and redirects to approval url', function () {
    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
            'access_token' => 'fake-access-token',
            'token_type' => 'Bearer',
        ]),
        'api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response([
            'id' => 'PAYPAL-ORDER-123',
            'status' => 'CREATED',
            'links' => [
                ['rel' => 'self', 'href' => 'https://api-m.sandbox.paypal.com/v2/checkout/orders/PAYPAL-ORDER-123'],
                ['rel' => 'approve', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=PAYPAL-ORDER-123'],
                ['rel' => 'capture', 'href' => 'https://api-m.sandbox.paypal.com/v2/checkout/orders/PAYPAL-ORDER-123/capture'],
            ],
        ]),
    ]);

    PaymentConfig::create([
        'owner_id' => $this->vendor->id,
        'commerce_code' => 'PRESET',
        'api_key' => 'PRESET',
        'paypal_client_id' => 'test-client-id',
        'paypal_client_secret' => 'test-secret',
        'paypal_mode' => 'sandbox',
        'paypal_active' => true,
    ]);

    $pedido = Pedido::create([
        'user_id' => $this->vendor->id,
        'owner_id' => $this->vendor->id,
        'public_profile_id' => $this->publicProfile->id,
        'cliente_id' => $this->buyer->id,
        'numero_pedido' => 'PED-TEST-001',
        'estado' => 'pendiente',
        'nombre_cliente' => 'Test Buyer',
        'total' => 20000,
        'subtotal' => 16807,
        'impuesto' => 3193,
        'metodo_pago' => 'paypal',
    ]);

    $response = $this->actingAs($this->buyer)
        ->get(route('paypal.pay', ['pedidoId' => $pedido->id]));

    $response->assertStatus(302);
    $response->assertHeader('Location', 'https://www.sandbox.paypal.com/checkoutnow?token=PAYPAL-ORDER-123');

    $pedido = Pedido::withoutGlobalScope(OwnerScope::class)->find($pedido->id);
    expect($pedido->payment_id)->toBe('PAYPAL-ORDER-123')
        ->and($pedido->payment_status)->toBe(PaymentStatus::Created);
});

test('paypal success captures payment and marks pedido as confirmed', function () {
    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
            'access_token' => 'fake-access-token',
            'token_type' => 'Bearer',
        ]),
        'api-m.sandbox.paypal.com/v2/checkout/orders/PAYPAL-ORDER-123/capture' => Http::response([
            'id' => 'PAYPAL-ORDER-123',
            'status' => 'COMPLETED',
            'purchase_units' => [
                [
                    'payments' => [
                        'captures' => [
                            ['id' => 'CAPTURE-001', 'status' => 'COMPLETED'],
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    PaymentConfig::create([
        'owner_id' => $this->vendor->id,
        'commerce_code' => 'PRESET',
        'api_key' => 'PRESET',
        'paypal_client_id' => 'test-client-id',
        'paypal_client_secret' => 'test-secret',
        'paypal_mode' => 'sandbox',
        'paypal_active' => true,
    ]);

    $pedido = Pedido::create([
        'user_id' => $this->vendor->id,
        'owner_id' => $this->vendor->id,
        'public_profile_id' => $this->publicProfile->id,
        'cliente_id' => $this->buyer->id,
        'numero_pedido' => 'PED-TEST-002',
        'estado' => 'pendiente',
        'nombre_cliente' => 'Test Buyer',
        'total' => 20000,
        'subtotal' => 16807,
        'impuesto' => 3193,
        'metodo_pago' => 'paypal',
        'payment_id' => 'PAYPAL-ORDER-123',
        'payment_status' => 'created',
    ]);

    $response = $this->actingAs($this->buyer)
        ->get(route('paypal.success', ['pedidoId' => $pedido->id, 'token' => 'PAYPAL-ORDER-123']));

    $response->assertRedirect(route('tienda.confirmacion', [
        'slug' => $this->publicProfile->slug,
        'pedidoId' => $pedido->id,
    ]));

    $pedido = Pedido::withoutGlobalScope(OwnerScope::class)->find($pedido->id);
    expect($pedido->payment_status)->toBe(PaymentStatus::Completed)
        ->and($pedido->estado)->toBe('confirmado')
        ->and($pedido->payment_data)->not->toBeNull();
});

test('paypal cancel marks pedido as cancelled and redirects to store', function () {
    PaymentConfig::create([
        'owner_id' => $this->vendor->id,
        'commerce_code' => 'PRESET',
        'api_key' => 'PRESET',
        'paypal_client_id' => 'test-client-id',
        'paypal_client_secret' => 'test-secret',
        'paypal_mode' => 'sandbox',
        'paypal_active' => true,
    ]);

    $pedido = Pedido::create([
        'user_id' => $this->vendor->id,
        'owner_id' => $this->vendor->id,
        'public_profile_id' => $this->publicProfile->id,
        'cliente_id' => $this->buyer->id,
        'numero_pedido' => 'PED-TEST-003',
        'estado' => 'pendiente',
        'nombre_cliente' => 'Test Buyer',
        'total' => 20000,
        'subtotal' => 16807,
        'impuesto' => 3193,
        'metodo_pago' => 'paypal',
        'payment_id' => 'PAYPAL-ORDER-123',
        'payment_status' => 'created',
    ]);

    $response = $this->actingAs($this->buyer)
        ->get(route('paypal.cancel', ['pedidoId' => $pedido->id]));

    $response->assertRedirect(route('marketplace.show', $this->publicProfile->slug));

    $pedido = Pedido::withoutGlobalScope(OwnerScope::class)->find($pedido->id);
    expect($pedido->payment_status)->toBe(PaymentStatus::Cancelled);
});

test('paypal pay denies access for non-owner of pedido', function () {
    PaymentConfig::create([
        'owner_id' => $this->vendor->id,
        'commerce_code' => 'PRESET',
        'api_key' => 'PRESET',
        'paypal_client_id' => 'test-client-id',
        'paypal_client_secret' => 'test-secret',
        'paypal_mode' => 'sandbox',
        'paypal_active' => true,
    ]);

    $pedido = Pedido::create([
        'user_id' => $this->vendor->id,
        'owner_id' => $this->vendor->id,
        'public_profile_id' => $this->publicProfile->id,
        'cliente_id' => $this->buyer->id,
        'numero_pedido' => 'PED-TEST-004',
        'estado' => 'pendiente',
        'nombre_cliente' => 'Test Buyer',
        'total' => 20000,
        'subtotal' => 16807,
        'impuesto' => 3193,
        'metodo_pago' => 'paypal',
    ]);

    $otherUser = User::factory()->create();

    $response = $this->actingAs($otherUser)
        ->get(route('paypal.pay', ['pedidoId' => $pedido->id]));

    $response->assertForbidden();
});
