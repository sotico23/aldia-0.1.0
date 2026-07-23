<?php

use App\Models\Categoria;
use App\Models\PaymentConfig;
use App\Models\PaymentSession;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\PublicProfile;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WebpayTransaction;
use App\Scopes\OwnerScope;
use App\Services\WebpayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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
});

test('PaymentSession duplicate detection prevents replay attacks', function () {
    PaymentConfig::create([
        'owner_id' => $this->vendor->id,
        'commerce_code' => '597055555532',
        'api_key' => 'test-api-key',
        'environment' => 'integration',
        'is_active' => true,
    ]);

    $session1 = PaymentSession::create([
        'token' => 'token-001',
        'buy_order' => 'ORD-001',
        'business_id' => $this->vendor->id,
        'status' => 'pending',
        'gateway' => 'webpay',
        'amount' => 10000,
        'metadata' => ['invoice_id' => 'inv-001'],
        'expires_at' => now()->addHours(2),
    ]);

    try {
        PaymentSession::create([
            'token' => 'token-001',
            'buy_order' => 'ORD-002',
            'business_id' => $this->vendor->id,
            'status' => 'pending',
            'gateway' => 'webpay',
            'amount' => 10000,
            'metadata' => ['invoice_id' => 'inv-002'],
            'expires_at' => now()->addHours(2),
        ]);
    } catch (Exception $e) {
        // Expected: UNIQUE constraint violation
    }

    expect(PaymentSession::where('token', 'token-001')->count())->toBe(1);
});

test('PayPal duplicate webhook detection', function () {
    PaymentConfig::create([
        'owner_id' => $this->vendor->id,
        'paypal_client_id' => 'test-client',
        'paypal_client_secret' => 'test-secret',
        'paypal_mode' => 'sandbox',
        'paypal_active' => true,
    ]);

    config(['services.paypal.webhook_id' => 'test-webhook-id']);

    $eventId = 'evt-duplicate-001';
    Cache::put('paypal_webhook_'.$eventId, true, 3600);

    $payload = [
        'id' => $eventId,
        'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
        'resource' => [
            'id' => 'CAPTURE-001',
            'status' => 'COMPLETED',
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
    $response->assertJson(['status' => 'duplicate_ignored']);
});

test('MercadoPago duplicate webhook detection', function () {
    PaymentConfig::create([
        'owner_id' => $this->vendor->id,
        'mercadopago_public_key' => 'test-public',
        'mercadopago_access_token' => 'test-access',
        'mercadopago_mode' => 'sandbox',
        'mercadopago_active' => true,
        'mercadopago_webhook_secret' => 'test-secret',
    ]);

    $eventId = 'evt-mp-duplicate';
    Cache::put('mercadopago_webhook_'.$eventId, true, 3600);

    $payload = [
        'id' => $eventId,
        'type' => 'payment',
        'data' => ['id' => 'PAY-001'],
    ];

    Http::fake([
        'api.mercadopago.com/v1/payments/*' => Http::response([
            'id' => 'PAY-001',
            'status' => 'approved',
            'transaction_amount' => 10000.00,
            'currency_id' => 'CLP',
            'external_reference' => 'PED-MP-DUP',
            'transaction_details' => [
                'total_paid_amount' => 10000.00,
                'net_received_amount' => 9500.00,
            ],
        ]),
    ]);

    $signature = 'ts='.time().',v1='.hash_hmac('sha256', 'id:PAY-001;request-id:;ts:'.time().';', 'test-secret');

    $response = $this->postJson(route('webhooks.mercadopago'), $payload, [
        'X-MercadoPago-Signature' => $signature,
    ]);

    $response->assertOk();
    $response->assertJson(['status' => 'duplicate_ignored']);
});

test('Webpay race condition: concurrent callbacks handled with lockForUpdate', function () {
    PaymentConfig::create([
        'owner_id' => $this->vendor->id,
        'commerce_code' => '597055555532',
        'api_key' => 'test-api-key',
        'environment' => 'integration',
        'is_active' => true,
    ]);

    $session = PaymentSession::create([
        'token' => 'token-race',
        'buy_order' => 'ORD-RACE',
        'business_id' => $this->vendor->id,
        'status' => 'pending',
        'gateway' => 'webpay',
        'amount' => 10000,
        'metadata' => ['invoice_id' => 'inv-race'],
        'expires_at' => now()->addHours(2),
    ]);

    WebpayTransaction::create([
        'owner_id' => $this->vendor->id,
        'token' => 'token-race',
        'amount' => 10000,
        'status' => 'pending',
        'buy_order' => 'ORD-RACE',
    ]);

    $this->mock(WebpayService::class, function ($mock) {
        $mock->shouldReceive('confirmTransaction')
            ->andReturn(new class
            {
                public function getAmount(): float
                {
                    return 10000;
                }

                public function getStatus(): string
                {
                    return 'AUTHORIZED';
                }

                public function getVci(): string
                {
                    return 'TSY';
                }

                public function getAuthorizationCode(): string
                {
                    return 'AUTH123';
                }

                public function getPaymentTypeCode(): string
                {
                    return 'VD';
                }

                public function getInstallmentsNumber(): int
                {
                    return 0;
                }
            });
    });

    $this->actingAs($this->vendor);

    $results = [];
    for ($i = 0; $i < 5; $i++) {
        $results[] = $this->post(route('webpay.callback'), [
            'token_ws' => 'token-race',
        ]);
    }

    $session->refresh();
    expect($session->status)->toBe('completed');
});

test('MercadoPago webhook idempotency with same payment ID', function () {
    PaymentConfig::create([
        'owner_id' => $this->vendor->id,
        'mercadopago_public_key' => 'test-public',
        'mercadopago_access_token' => 'test-access',
        'mercadopago_mode' => 'sandbox',
        'mercadopago_active' => true,
        'mercadopago_webhook_secret' => 'test-secret',
    ]);

    $pedido = Pedido::withoutGlobalScope(OwnerScope::class)->create([
        'owner_id' => $this->vendor->id,
        'user_id' => $this->vendor->id,
        'public_profile_id' => $this->publicProfile->id,
        'cliente_id' => $this->buyer->id,
        'numero_pedido' => 'MP-IDEMP-001',
        'estado' => 'pendiente',
        'nombre_cliente' => 'Test',
        'total' => 25000,
        'subtotal' => 21008,
        'impuesto' => 3992,
        'metodo_pago' => 'mercadopago',
    ]);

    Http::fake([
        'api.mercadopago.com/v1/payments/*' => Http::response([
            'id' => 'PAY-IDEMP-001',
            'status' => 'approved',
            'transaction_amount' => 25000.00,
            'currency_id' => 'CLP',
            'external_reference' => 'MP-IDEMP-001',
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
        'data' => ['id' => 'PAY-IDEMP-001'],
    ];

    $signature = 'ts='.time().',v1='.hash_hmac('sha256', 'id:PAY-IDEMP-001;request-id:;ts:'.time().';', 'test-secret');

    $this->postJson(route('webhooks.mercadopago'), $payload, [
        'X-MercadoPago-Signature' => $signature,
    ])->assertOk();

    $this->postJson(route('webhooks.mercadopago'), $payload, [
        'X-MercadoPago-Signature' => $signature,
    ])->assertOk();

    expect(Transaction::where('gateway_transaction_id', 'PAY-IDEMP-001')->count())->toBe(1);
});

test('PayPal webhook idempotency with same capture ID', function () {
    PaymentConfig::create([
        'owner_id' => $this->vendor->id,
        'paypal_client_id' => 'test-client',
        'paypal_client_secret' => 'test-secret',
        'paypal_mode' => 'sandbox',
        'paypal_active' => true,
    ]);

    config(['services.paypal.webhook_id' => 'test-webhook-id']);

    $pedido = Pedido::withoutGlobalScope(OwnerScope::class)->create([
        'owner_id' => $this->vendor->id,
        'user_id' => $this->vendor->id,
        'public_profile_id' => $this->publicProfile->id,
        'cliente_id' => $this->buyer->id,
        'numero_pedido' => 'PP-IDEMP-001',
        'estado' => 'pendiente',
        'nombre_cliente' => 'Test',
        'total' => 20000,
        'subtotal' => 16807,
        'impuesto' => 3193,
        'metodo_pago' => 'paypal',
    ]);

    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'fake-token']),
        'api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' => Http::response([
            'verification_status' => 'VERIFIED',
        ]),
    ]);

    $payload = [
        'id' => 'evt-pp-001',
        'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
        'resource' => [
            'id' => 'CAPTURE-IDEMP-001',
            'status' => 'COMPLETED',
            'amount' => ['value' => '20000.00', 'currency_code' => 'CLP'],
            'custom_id' => 'PP-IDEMP-001',
        ],
    ];

    $headers = [
        'PAYPAL-TRANSMISSION-ID' => 'txn-001',
        'PAYPAL-TRANSMISSION-TIME' => now()->toISOString(),
        'PAYPAL-TRANSMISSION-SIG' => 'sig',
        'PAYPAL-CERT-URL' => 'cert',
        'PAYPAL-AUTH-ALGO' => 'SHA256withRSA',
    ];

    $this->postJson(route('webhooks.paypal'), $payload, $headers)->assertOk();
    $this->postJson(route('webhooks.paypal'), $payload, $headers)->assertOk();

    expect(Transaction::where('gateway_transaction_id', 'CAPTURE-IDEMP-001')->count())->toBe(1);
});

test('Webhook cross-tenant isolation: tenant A config cannot process tenant B webhook', function () {
    $vendorB = User::factory()->create();
    $profileB = PublicProfile::factory()->create([
        'user_id' => $vendorB->id,
        'owner_id' => $vendorB->id,
        'is_active' => true,
    ]);

    $pedidoB = Pedido::withoutGlobalScope(OwnerScope::class)->create([
        'owner_id' => $vendorB->id,
        'user_id' => $vendorB->id,
        'public_profile_id' => $profileB->id,
        'cliente_id' => $this->buyer->id,
        'numero_pedido' => 'CROSS-TENANT-001',
        'estado' => 'pendiente',
        'nombre_cliente' => 'Test',
        'total' => 25000,
        'subtotal' => 21008,
        'impuesto' => 3992,
        'metodo_pago' => 'mercadopago',
    ]);

    PaymentConfig::create([
        'owner_id' => $this->vendor->id,
        'mercadopago_public_key' => 'tenant-a-public',
        'mercadopago_access_token' => 'tenant-a-access',
        'mercadopago_mode' => 'sandbox',
        'mercadopago_active' => true,
        'mercadopago_webhook_secret' => 'tenant-a-secret',
    ]);

    Http::fake([
        'api.mercadopago.com/v1/payments/*' => Http::response([
            'id' => 'PAY-CROSS-001',
            'status' => 'approved',
            'transaction_amount' => 25000.00,
            'currency_id' => 'CLP',
            'external_reference' => 'CROSS-TENANT-001',
            'payment_method_id' => 'visa',
            'installments' => 1,
            'transaction_details' => [
                'total_paid_amount' => 25000.00,
                'net_received_amount' => 24000.00,
            ],
        ]),
    ]);

    $payload = [
        'id' => 'evt-cross',
        'type' => 'payment',
        'action' => 'payment.updated',
        'data' => ['id' => 'PAY-CROSS-001'],
    ];

    $signature = 'ts='.time().',v1='.hash_hmac('sha256', 'id:PAY-CROSS-001;request-id:;ts:'.time().';', 'tenant-a-secret');

    $response = $this->postJson(route('webhooks.mercadopago'), $payload, [
        'X-MercadoPago-Signature' => $signature,
    ]);

    $response->assertStatus(400);
    $response->assertJson(['error' => 'Invalid origin']);
});
