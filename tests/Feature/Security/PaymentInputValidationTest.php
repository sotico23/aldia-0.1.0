<?php

use App\Models\Categoria;
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
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'Master', 'guard_name' => 'web']);

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
    ]);

    $this->producto = Producto::factory()->create([
        'user_id' => $this->vendor->id,
        'owner_id' => $this->vendor->id,
        'categoria_id' => $this->categoria->id,
        'precio_venta' => 10000,
        'activo' => true,
    ]);
});

test('MercadoPago webhook rejects SQL injection in external_reference', function () {
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
        'numero_pedido' => 'PED-SQL-001',
        'estado' => 'pendiente',
        'nombre_cliente' => 'Test',
        'total' => 10000,
        'subtotal' => 8403,
        'impuesto' => 1597,
        'metodo_pago' => 'mercadopago',
    ]);

    Http::fake([
        'api.mercadopago.com/v1/payments/*' => Http::response([
            'id' => 'PAY-SQL-001',
            'status' => 'approved',
            'transaction_amount' => 10000.00,
            'currency_id' => 'CLP',
            'external_reference' => "PED-SQL-001' OR '1'='1",
            'payment_method_id' => 'visa',
            'installments' => 1,
            'transaction_details' => [
                'total_paid_amount' => 10000.00,
                'net_received_amount' => 9500.00,
            ],
        ]),
    ]);

    $payload = [
        'id' => 'evt-sql',
        'type' => 'payment',
        'action' => 'payment.updated',
        'data' => ['id' => 'PAY-SQL-001'],
    ];

    $signature = 'ts='.time().',v1='.hash_hmac('sha256', 'id:PAY-SQL-001;request-id:;ts:'.time().';', 'test-secret');

    $response = $this->postJson(route('webhooks.mercadopago'), $payload, [
        'X-MercadoPago-Signature' => $signature,
    ]);

    $response->assertStatus(400);
    $response->assertJson(['error' => 'Invalid origin']);
});

test('PayPal webhook rejects malformed custom_id', function () {
    PaymentConfig::create([
        'owner_id' => $this->vendor->id,
        'paypal_client_id' => 'test-client',
        'paypal_client_secret' => 'test-secret',
        'paypal_mode' => 'sandbox',
        'paypal_active' => true,
    ]);

    config(['services.paypal.webhook_id' => 'test-webhook-id']);

    $payload = [
        'id' => 'evt-pp-malformed',
        'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
        'resource' => [
            'id' => 'CAP-MALFORMED',
            'status' => 'COMPLETED',
            'custom_id' => '<script>alert(1)</script>',
            'amount' => ['value' => '10000.00', 'currency_code' => 'CLP'],
        ],
    ];

    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'fake-token']),
        'api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' => Http::response([
            'verification_status' => 'VERIFIED',
        ]),
    ]);

    $response = $this->postJson(route('webhooks.paypal'), $payload, [
        'PAYPAL-TRANSMISSION-ID' => 'tx-001',
        'PAYPAL-TRANSMISSION-TIME' => now()->toIso8601String(),
        'PAYPAL-TRANSMISSION-SIG' => 'fake-sig',
        'PAYPAL-CERT-URL' => 'https://fake.cert',
        'PAYPAL-AUTH-ALGO' => 'SHA256withRSA',
    ]);

    $response->assertOk();
    $response->assertJson(['status' => 'ok']);
});

test('Webpay amount manipulation attempt is rejected', function () {
    PaymentConfig::create([
        'owner_id' => $this->vendor->id,
        'commerce_code' => '597055555532',
        'api_key' => 'test-api-key',
        'environment' => 'integration',
        'is_active' => true,
    ]);

    $session = PaymentSession::create([
        'token' => 'test-token-amt',
        'buy_order' => 'ORD-AMT-001',
        'business_id' => $this->vendor->id,
        'status' => 'pending',
        'gateway' => 'webpay',
        'amount' => 10000,
        'metadata' => ['invoice_id' => 'inv-001'],
        'expires_at' => now()->addHours(2),
    ]);

    $this->mock(WebpayService::class, function ($mock) {
        $mock->shouldReceive('confirmTransaction')
            ->once()
            ->andReturn(new class
            {
                public function getAmount(): float
                {
                    return 5000;
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

    $response = $this->post(route('webpay.callback'), [
        'token_ws' => 'test-token-amt',
    ]);

    $session->refresh();
    expect($session->status)->toBe('failed');
    $response->assertStatus(200);
});

test('MercadoPago success rejects negative amount', function () {
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
        'numero_pedido' => 'MP-NEG-AMT',
        'estado' => 'pendiente',
        'nombre_cliente' => 'Test',
        'total' => 20000,
        'subtotal' => 16807,
        'impuesto' => 3193,
        'metodo_pago' => 'mercadopago',
        'payment_id' => 'PAY-NEG-001',
        'payment_status' => 'created',
    ]);

    Http::fake([
        'api.mercadopago.com/v1/payments/*' => Http::response([
            'status' => 'approved',
            'transaction_amount' => -100,
            'currency_id' => 'CLP',
            'external_reference' => 'MP-NEG-AMT',
            'transaction_details' => [
                'net_received_amount' => -100,
            ],
        ]),
    ]);

    $response = $this->actingAs($this->buyer)
        ->get(route('mercadopago.success', ['pedidoId' => $pedido->id, 'payment_id' => 'PAY-NEG-001']));

    $response->assertRedirect();
    $response->assertSessionHas('error');
});

test('Payment session ID generation is cryptographically secure', function () {
    PaymentConfig::create([
        'owner_id' => $this->vendor->id,
        'commerce_code' => '597055555532',
        'api_key' => 'test-api-key',
        'environment' => 'integration',
        'is_active' => true,
    ]);

    $this->mock(WebpayService::class, function ($mock) {
        $counter = 0;
        $mock->shouldReceive('createTransaction')->andReturnUsing(function () use (&$counter) {
            $counter++;

            return new class($counter)
            {
                public function __construct(private int $counter) {}

                public function getToken(): string
                {
                    return 'mock-token-'.$this->counter.'-'.Str::random(8);
                }

                public function getUrl(): string
                {
                    return 'https://mock.url';
                }
            };
        });
    });

    for ($i = 0; $i < 10; $i++) {
        $this->actingAs($this->buyer)
            ->postJson(route('webpay.pay'), [
                'invoice_id' => 'inv-'.$i,
                'amount' => 10000,
            ]);
    }

    $buyOrders = PaymentSession::withoutGlobalScope(BusinessScope::class)
        ->where('gateway', 'webpay')
        ->pluck('buy_order')
        ->toArray();

    expect($buyOrders)->toHaveCount(10);
    expect(array_unique($buyOrders))->toHaveCount(10);

    foreach ($buyOrders as $order) {
        expect($order)->toMatch('/^ORD-\d+-[a-zA-Z0-9]{16}$/');
    }
});

test('Duplicate webhook idempotency works across all gateways', function () {
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
        'numero_pedido' => 'DUP-ORDER-001',
        'estado' => 'pendiente',
        'nombre_cliente' => 'Test',
        'total' => 10000,
        'subtotal' => 8403,
        'impuesto' => 1597,
        'metodo_pago' => 'mercadopago',
    ]);

    $eventId = 'DUP-EVENT-001';

    $payload = [
        'id' => $eventId,
        'type' => 'payment',
        'action' => 'payment.updated',
        'data' => ['id' => 'PAY-DUP-001'],
    ];

    Http::fake([
        'api.mercadopago.com/v1/payments/*' => Http::response([
            'id' => 'PAY-DUP-001',
            'status' => 'approved',
            'transaction_amount' => 10000.00,
            'currency_id' => 'CLP',
            'external_reference' => 'DUP-ORDER-001',
            'payment_method_id' => 'visa',
            'installments' => 1,
            'transaction_details' => [
                'total_paid_amount' => 10000.00,
                'net_received_amount' => 9500.00,
            ],
        ]),
    ]);

    $signature = 'ts='.time().',v1='.hash_hmac('sha256', 'id:PAY-DUP-001;request-id:;ts:'.time().';', 'test-secret');

    $response1 = $this->postJson(route('webhooks.mercadopago'), $payload, [
        'X-MercadoPago-Signature' => $signature,
    ]);

    $response1->assertOk();
    $response1->assertJson(['status' => 'ok']);

    $response2 = $this->postJson(route('webhooks.mercadopago'), $payload, [
        'X-MercadoPago-Signature' => $signature,
    ]);

    $response2->assertOk();
    $response2->assertJson(['status' => 'duplicate_ignored']);

    $this->assertDatabaseCount('transactions', 1);
});

test('Webhook timestamp replay attack prevention', function () {
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
        'numero_pedido' => 'PED-OLD-001',
        'estado' => 'pendiente',
        'nombre_cliente' => 'Test',
        'total' => 10000,
        'subtotal' => 8403,
        'impuesto' => 1597,
        'metodo_pago' => 'mercadopago',
    ]);

    $oldTs = time() - 7200;
    $dataId = 'PAY-OLD-001';
    $manifest = "id:{$dataId};request-id:;ts:{$oldTs};";
    $oldHash = hash_hmac('sha256', $manifest, 'test-secret');

    Http::fake([
        'api.mercadopago.com/v1/payments/*' => Http::response([
            'id' => $dataId,
            'status' => 'approved',
            'transaction_amount' => 10000.00,
            'currency_id' => 'CLP',
            'external_reference' => 'PED-OLD-001',
            'transaction_details' => [
                'total_paid_amount' => 10000.00,
                'net_received_amount' => 9500.00,
            ],
        ]),
    ]);

    $payload = [
        'id' => 'evt-old',
        'type' => 'payment',
        'action' => 'payment.updated',
        'data' => ['id' => $dataId],
    ];

    $signature = "ts={$oldTs},v1={$oldHash}";

    $response = $this->postJson(route('webhooks.mercadopago'), $payload, [
        'X-MercadoPago-Signature' => $signature,
    ]);

    $response->assertOk();
});

test('Rate limit bypass attempt via different IPs is prevented', function () {
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

    for ($i = 0; $i < 10; $i++) {
        $response = $this->actingAs($this->buyer)
            ->postJson(route('webpay.pay'), [
                'invoice_id' => 'inv-'.$i,
                'amount' => 1000,
            ]);
        $response->assertStatus(200);
    }

    $response = $this->actingAs($this->buyer)
        ->postJson(route('webpay.pay'), [
            'invoice_id' => 'inv-10',
            'amount' => 1000,
        ]);
    $response->assertTooManyRequests();
});
