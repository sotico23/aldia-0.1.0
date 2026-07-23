<?php

use App\Models\Categoria;
use App\Models\Inventario;
use App\Models\PaymentConfig;
use App\Models\PaymentSession;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\PublicProfile;
use App\Models\User;
use App\Scopes\BusinessScope;
use App\Scopes\OwnerScope;
use App\Services\WebpayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'Master', 'guard_name' => 'web']);

    $this->master = User::factory()->create();
    $this->master->assignRole('Master');

    $this->vendor = User::factory()->create();
    $this->buyer = User::factory()->create();
    $this->attacker = User::factory()->create();

    $this->publicProfile = PublicProfile::factory()->create([
        'user_id' => $this->vendor->id,
        'owner_id' => $this->vendor->id,
        'is_active' => true,
    ]);

    $this->categoria = Categoria::factory()->create([
        'user_id' => $this->vendor->id,
        'owner_id' => $this->vendor->id,
        'activo' => true,
    ]);

    $this->producto = Producto::factory()->create([
        'user_id' => $this->vendor->id,
        'owner_id' => $this->vendor->id,
        'categoria_id' => $this->categoria->id,
        'precio_venta' => 10000,
        'activo' => true,
    ]);

    Inventario::factory()->create([
        'owner_id' => $this->vendor->id,
        'producto_id' => $this->producto->id,
        'cantidad' => 100,
    ]);

    $this->pedido = Pedido::withoutGlobalScope(OwnerScope::class)->create([
        'owner_id' => $this->vendor->id,
        'user_id' => $this->vendor->id,
        'public_profile_id' => $this->publicProfile->id,
        'cliente_id' => $this->buyer->id,
        'numero_pedido' => 'PED-SEC-001',
        'estado' => 'pendiente',
        'nombre_cliente' => 'Test Buyer',
        'total' => 20000,
        'subtotal' => 16807,
        'impuesto' => 3193,
        'metodo_pago' => 'webpay',
    ]);
});

test('Webpay pay endpoint has rate limiting', function () {
    PaymentConfig::create([
        'owner_id' => $this->vendor->id,
        'commerce_code' => '597055555532',
        'api_key' => 'test-api-key',
        'environment' => 'integration',
        'is_active' => true,
    ]);

    $this->mock(WebpayService::class, function ($mock) {
        $mock->shouldReceive('createTransaction')->andReturn(new class
        {
            public function getToken(): string
            {
                return 'mock-token';
            }

            public function getUrl(): string
            {
                return 'https://mock.url';
            }
        });
    });

    for ($i = 0; $i < 11; $i++) {
        $response = $this->actingAs($this->buyer)
            ->postJson(route('webpay.pay'), [
                'invoice_id' => 'inv-'.$i,
                'amount' => 1000,
            ]);
        if ($i < 10) {
            $response->assertStatus(200);
        } else {
            $response->assertTooManyRequests();
        }
    }
});

test('PayPal pay endpoint has rate limiting', function () {
    PaymentConfig::create([
        'owner_id' => $this->vendor->id,
        'paypal_client_id' => 'test-client-id',
        'paypal_client_secret' => 'test-secret',
        'paypal_mode' => 'sandbox',
        'paypal_active' => true,
    ]);

    for ($i = 0; $i < 11; $i++) {
        $pedido = Pedido::withoutGlobalScope(OwnerScope::class)->create([
            'owner_id' => $this->vendor->id,
            'user_id' => $this->vendor->id,
            'public_profile_id' => $this->publicProfile->id,
            'cliente_id' => $this->buyer->id,
            'numero_pedido' => 'PED-RL-'.$i,
            'estado' => 'pendiente',
            'nombre_cliente' => 'Test',
            'total' => 20000,
            'subtotal' => 16807,
            'impuesto' => 3193,
            'metodo_pago' => 'paypal',
        ]);

        $response = $this->actingAs($this->buyer)
            ->get(route('paypal.pay', ['pedidoId' => $pedido->id]));
        if ($i < 10) {
            $response->assertStatus(302);
        } else {
            $response->assertTooManyRequests();
        }
    }
});

test('MercadoPago pay endpoint has rate limiting', function () {
    PaymentConfig::create([
        'owner_id' => $this->vendor->id,
        'mercadopago_public_key' => 'test-public',
        'mercadopago_access_token' => 'test-access',
        'mercadopago_mode' => 'sandbox',
        'mercadopago_active' => true,
    ]);

    for ($i = 0; $i < 11; $i++) {
        $pedido = Pedido::withoutGlobalScope(OwnerScope::class)->create([
            'owner_id' => $this->vendor->id,
            'user_id' => $this->vendor->id,
            'public_profile_id' => $this->publicProfile->id,
            'cliente_id' => $this->buyer->id,
            'numero_pedido' => 'PED-MP-'.$i,
            'estado' => 'pendiente',
            'nombre_cliente' => 'Test',
            'total' => 20000,
            'subtotal' => 16807,
            'impuesto' => 3193,
            'metodo_pago' => 'mercadopago',
        ]);

        $response = $this->actingAs($this->buyer)
            ->get(route('mercadopago.pay', ['pedidoId' => $pedido->id]));
        if ($i < 10) {
            $response->assertStatus(302);
        } else {
            $response->assertTooManyRequests();
        }
    }
});

test('Webpay callback only accepts POST (not GET)', function () {
    PaymentConfig::create([
        'owner_id' => $this->vendor->id,
        'commerce_code' => '597055555532',
        'api_key' => 'test-api-key',
        'environment' => 'integration',
        'is_active' => true,
    ]);

    $response = $this->get(route('webpay.callback'));

    $response->assertStatus(405);
});

test('Webpay callback rejects expired payment session', function () {
    PaymentConfig::create([
        'owner_id' => $this->vendor->id,
        'commerce_code' => '597055555532',
        'api_key' => 'test-api-key',
        'environment' => 'integration',
        'is_active' => true,
    ]);

    $session = PaymentSession::create([
        'token' => 'test-token-expired',
        'buy_order' => 'ORD-TEST-001',
        'business_id' => $this->vendor->id,
        'status' => 'pending',
        'gateway' => 'webpay',
        'amount' => 10000,
        'metadata' => ['invoice_id' => 'inv-001'],
        'expires_at' => now()->subHour(),
    ]);

    $this->actingAs($this->vendor);

    $response = $this->post(route('webpay.callback'), [
        'token_ws' => 'test-token-expired',
    ]);

    $session->refresh();
    expect($session->status)->toBe('expired');
    $response->assertStatus(200);
});

test('Webpay buy_order is cryptographically secure', function () {
    PaymentConfig::create([
        'owner_id' => $this->vendor->id,
        'commerce_code' => '597055555532',
        'api_key' => 'test-api-key',
        'environment' => 'integration',
        'is_active' => true,
    ]);

    $this->mock(WebpayService::class, function ($mock) {
        $mock->shouldReceive('createTransaction')->andReturn(new class
        {
            public function getToken(): string
            {
                return 'mock-token-buy-order';
            }

            public function getUrl(): string
            {
                return 'https://mock.url';
            }
        });
    });

    $response = $this->actingAs($this->buyer)
        ->postJson(route('webpay.pay'), [
            'invoice_id' => 'inv-001',
            'amount' => 10000,
        ]);

    $response->assertStatus(200);

    $session = PaymentSession::withoutGlobalScope(BusinessScope::class)->latest()->first();
    expect($session->buy_order)->toMatch('/^ORD-\d+-[a-zA-Z0-9]{16}$/');
});

test('MercadoPago success verifies payment with API before confirming', function () {
    PaymentConfig::create([
        'owner_id' => $this->vendor->id,
        'mercadopago_public_key' => 'test-public',
        'mercadopago_access_token' => 'test-access',
        'mercadopago_mode' => 'sandbox',
        'mercadopago_active' => true,
    ]);

    $pedido = Pedido::withoutGlobalScope(OwnerScope::class)->create([
        'owner_id' => $this->vendor->id,
        'user_id' => $this->vendor->id,
        'public_profile_id' => $this->publicProfile->id,
        'cliente_id' => $this->buyer->id,
        'numero_pedido' => 'PED-MP-VERIFY',
        'estado' => 'pendiente',
        'nombre_cliente' => 'Test',
        'total' => 20000,
        'subtotal' => 16807,
        'impuesto' => 3193,
        'metodo_pago' => 'mercadopago',
        'payment_id' => 'PAY-TEST-001',
        'payment_status' => 'created',
    ]);

    Http::fake([
        'api.mercadopago.com/v1/payments/*' => Http::response([
            'status' => 'rejected',
            'status_detail' => 'cc_rejected_bad_filled_card_number',
        ]),
    ]);

    $response = $this->actingAs($this->buyer)
        ->get(route('mercadopago.success', ['pedidoId' => $pedido->id, 'payment_id' => 'PAY-TEST-001', 'status' => 'approved']));

    $response->assertRedirect();
    $response->assertSessionHas('error');
});

test('PayPal cancel verifies with API before marking cancelled', function () {
    PaymentConfig::create([
        'owner_id' => $this->vendor->id,
        'paypal_client_id' => 'test-client-id',
        'paypal_client_secret' => 'test-secret',
        'paypal_mode' => 'sandbox',
        'paypal_active' => true,
    ]);

    $pedido = Pedido::withoutGlobalScope(OwnerScope::class)->create([
        'owner_id' => $this->vendor->id,
        'user_id' => $this->vendor->id,
        'public_profile_id' => $this->publicProfile->id,
        'cliente_id' => $this->buyer->id,
        'numero_pedido' => 'PED-PP-CANCEL',
        'estado' => 'pendiente',
        'nombre_cliente' => 'Test',
        'total' => 20000,
        'subtotal' => 16807,
        'impuesto' => 3193,
        'metodo_pago' => 'paypal',
        'payment_id' => 'PP-ORDER-001',
        'payment_status' => 'created',
    ]);

    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
            'access_token' => 'fake-token',
        ]),
        'api-m.sandbox.paypal.com/v2/checkout/orders/PP-ORDER-001' => Http::response([
            'status' => 'APPROVED',
        ]),
    ]);

    $response = $this->actingAs($this->buyer)
        ->get(route('paypal.cancel', ['pedidoId' => $pedido->id]));

    $response->assertRedirect(route('paypal.success', ['pedidoId' => $pedido->id, 'token' => 'PP-ORDER-001']));
});

test('MercadoPago webhook uses tenant-specific config via external_reference', function () {
    PaymentConfig::create([
        'owner_id' => $this->vendor->id,
        'mercadopago_public_key' => 'vendor-public',
        'mercadopago_access_token' => 'vendor-access',
        'mercadopago_mode' => 'sandbox',
        'mercadopago_active' => true,
        'mercadopago_webhook_secret' => 'vendor-secret',
    ]);

    $otherVendor = User::factory()->create();
    PaymentConfig::create([
        'owner_id' => $otherVendor->id,
        'mercadopago_public_key' => 'other-public',
        'mercadopago_access_token' => 'other-access',
        'mercadopago_mode' => 'sandbox',
        'mercadopago_active' => true,
        'mercadopago_webhook_secret' => 'other-secret',
    ]);

    $pedido = Pedido::withoutGlobalScope(OwnerScope::class)->create([
        'owner_id' => $this->vendor->id,
        'user_id' => $this->vendor->id,
        'public_profile_id' => $this->publicProfile->id,
        'cliente_id' => $this->buyer->id,
        'numero_pedido' => 'MP-WEBHOOK-001',
        'estado' => 'pendiente',
        'nombre_cliente' => 'Test',
        'total' => 25000,
        'subtotal' => 21008,
        'impuesto' => 3992,
        'metodo_pago' => 'mercadopago',
    ]);

    Http::fake([
        'api.mercadopago.com/v1/payments/*' => Http::response([
            'id' => 'PAY-WEBHOOK-001',
            'status' => 'approved',
            'status_detail' => 'accredited',
            'transaction_amount' => 25000.00,
            'currency_id' => 'CLP',
            'external_reference' => 'MP-WEBHOOK-001',
            'payment_method_id' => 'visa',
            'installments' => 1,
            'transaction_details' => [
                'total_paid_amount' => 25000.00,
                'net_received_amount' => 24000.00,
            ],
        ]),
    ]);

    $payload = [
        'id' => 'evt-001',
        'type' => 'payment',
        'action' => 'payment.updated',
        'data' => ['id' => 'PAY-WEBHOOK-001'],
    ];

    $signature = 'ts='.time().',v1='.hash_hmac('sha256', 'id:PAY-WEBHOOK-001;request-id:;ts:'.time().';', 'vendor-secret');

    $response = $this->postJson(route('webhooks.mercadopago'), $payload, [
        'X-MercadoPago-Signature' => $signature,
    ]);

    $response->assertOk();
    $response->assertJson(['status' => 'ok']);

    $this->assertDatabaseHas('transactions', [
        'gateway' => 'mercadopago',
        'gateway_transaction_id' => 'PAY-WEBHOOK-001',
        'business_id' => $this->vendor->id,
    ]);
});

test('MercadoPago webhook rejects amount mismatch', function () {
    PaymentConfig::create([
        'owner_id' => $this->vendor->id,
        'mercadopago_public_key' => 'vendor-public',
        'mercadopago_access_token' => 'vendor-access',
        'mercadopago_mode' => 'sandbox',
        'mercadopago_active' => true,
        'mercadopago_webhook_secret' => 'vendor-secret',
    ]);

    $pedido = Pedido::withoutGlobalScope(OwnerScope::class)->create([
        'owner_id' => $this->vendor->id,
        'user_id' => $this->vendor->id,
        'public_profile_id' => $this->publicProfile->id,
        'cliente_id' => $this->buyer->id,
        'numero_pedido' => 'MP-AMOUNT-001',
        'estado' => 'pendiente',
        'nombre_cliente' => 'Test',
        'total' => 25000,
        'subtotal' => 21008,
        'impuesto' => 3992,
        'metodo_pago' => 'mercadopago',
    ]);

    Http::fake([
        'api.mercadopago.com/v1/payments/*' => Http::response([
            'id' => 'PAY-AMOUNT-001',
            'status' => 'approved',
            'transaction_amount' => 10000,
            'currency_id' => 'CLP',
            'external_reference' => 'MP-AMOUNT-001',
            'transaction_details' => [
                'net_received_amount' => 9500.00,
            ],
        ]),
    ]);

    $payload = [
        'id' => 'evt-002',
        'type' => 'payment',
        'action' => 'payment.updated',
        'data' => ['id' => 'PAY-AMOUNT-001'],
    ];

    $signature = 'ts='.time().',v1='.hash_hmac('sha256', 'id:PAY-AMOUNT-001;request-id:;ts:'.time().';', 'vendor-secret');

    $response = $this->postJson(route('webhooks.mercadopago'), $payload, [
        'X-MercadoPago-Signature' => $signature,
    ]);

    $response->assertStatus(400);
    $response->assertJson(['error' => 'Amount mismatch']);
});

test('PayPal webhook uses tenant-specific config via custom_id', function () {
    PaymentConfig::create([
        'owner_id' => $this->vendor->id,
        'paypal_client_id' => 'vendor-client',
        'paypal_client_secret' => 'vendor-secret',
        'paypal_mode' => 'sandbox',
        'paypal_active' => true,
        'paypal_webhook_id' => 'vendor-webhook-id',
    ]);

    config(['services.paypal.webhook_id' => 'vendor-webhook-id']);

    $pedido = Pedido::withoutGlobalScope(OwnerScope::class)->create([
        'owner_id' => $this->vendor->id,
        'user_id' => $this->vendor->id,
        'public_profile_id' => $this->publicProfile->id,
        'cliente_id' => $this->buyer->id,
        'numero_pedido' => 'PP-WEBHOOK-001',
        'estado' => 'pendiente',
        'nombre_cliente' => 'Test',
        'total' => 20000,
        'subtotal' => 16807,
        'impuesto' => 3193,
        'metodo_pago' => 'paypal',
    ]);

    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
            'access_token' => 'fake-token',
        ]),
        'api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' => Http::response([
            'verification_status' => 'VERIFIED',
        ]),
    ]);

    $payload = [
        'id' => 'evt-pp-001',
        'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
        'resource' => [
            'id' => 'CAPTURE-001',
            'status' => 'COMPLETED',
            'amount' => ['value' => '20000.00', 'currency_code' => 'CLP'],
            'custom_id' => 'PP-WEBHOOK-001',
        ],
    ];

    $response = $this->postJson(route('webhooks.paypal'), $payload, [
        'PAYPAL-TRANSMISSION-ID' => 'txn-001',
        'PAYPAL-TRANSMISSION-TIME' => now()->toISOString(),
        'PAYPAL-TRANSMISSION-SIG' => 'sig',
        'PAYPAL-CERT-URL' => 'cert',
        'PAYPAL-AUTH-ALGO' => 'SHA256withRSA',
    ]);

    $response->assertOk();
    $response->assertJson(['status' => 'ok']);

    $this->assertDatabaseHas('transactions', [
        'gateway' => 'paypal',
        'gateway_transaction_id' => 'CAPTURE-001',
        'business_id' => $this->vendor->id,
    ]);
});

test('PaymentConfig resolveForOwner returns null when tenant has no active methods', function () {
    PaymentConfig::create([
        'owner_id' => $this->vendor->id,
        'commerce_code' => '597055555532',
        'api_key' => 'test-key',
        'is_active' => false,
        'paypal_active' => false,
        'mercadopago_active' => false,
    ]);

    $config = PaymentConfig::resolveForOwner($this->vendor->id);

    expect($config)->toBeNull();
});

test('PaymentConfig resolveForOwner falls back to master when use_platform_config is true', function () {
    PaymentConfig::create([
        'owner_id' => $this->master->id,
        'commerce_code' => 'MASTER-CC',
        'api_key' => 'MASTER-API',
        'is_active' => true,
    ]);

    PaymentConfig::create([
        'owner_id' => $this->vendor->id,
        'use_platform_config' => true,
    ]);

    $config = PaymentConfig::resolveForOwner($this->vendor->id);

    expect($config)->not->toBeNull();
    expect($config->owner_id)->toBe($this->master->id);
});

test('Pedido creation validates metodo_pago against active gateways', function () {
    $response = $this->actingAs($this->buyer)
        ->post(route('tienda.checkout', $this->publicProfile->slug), [
            'public_profile_id' => $this->publicProfile->id,
            'items' => [['producto_id' => $this->producto->id, 'cantidad' => 1]],
            'nombre_cliente' => 'Test',
            'metodo_pago' => 'webpay',
        ]);

    $response->assertSessionHasErrors('metodo_pago');

    PaymentConfig::create([
        'owner_id' => $this->vendor->id,
        'commerce_code' => '597055555532',
        'api_key' => 'test-key',
        'environment' => 'integration',
        'is_active' => true,
    ]);

    $this->mock(WebpayService::class, function ($mock) {
        $mock->shouldReceive('createTransaction')->andReturn(new class
        {
            public function getToken(): string
            {
                return 'mock-token';
            }

            public function getUrl(): string
            {
                return 'https://mock.url';
            }
        });
    });

    $response = $this->actingAs($this->buyer)
        ->post(route('tienda.checkout', $this->publicProfile->slug), [
            'public_profile_id' => $this->publicProfile->id,
            'items' => [['producto_id' => $this->producto->id, 'cantidad' => 1]],
            'nombre_cliente' => 'Test',
            'metodo_pago' => 'webpay',
        ]);

    $response->assertRedirect();
});
