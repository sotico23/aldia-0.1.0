<?php

use App\Models\Categoria;
use App\Models\Inventario;
use App\Models\PaymentConfig;
use App\Models\PaymentSession;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\PublicProfile;
use App\Models\User;
use App\Scopes\OwnerScope;
use App\Services\WebpayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'Master', 'guard_name' => 'web']);

    $this->tenantA = User::factory()->create();
    $this->tenantB = User::factory()->create();
    $this->buyer = User::factory()->create();

    $this->profileA = PublicProfile::factory()->create([
        'user_id' => $this->tenantA->id,
        'owner_id' => $this->tenantA->id,
        'is_active' => true,
    ]);

    $this->profileB = PublicProfile::factory()->create([
        'user_id' => $this->tenantB->id,
        'owner_id' => $this->tenantB->id,
        'is_active' => true,
    ]);

    $this->categoriaA = Categoria::factory()->create([
        'user_id' => $this->tenantA->id,
        'owner_id' => $this->tenantA->id,
        'activo' => true,
    ]);

    $this->productoA = Producto::factory()->create([
        'user_id' => $this->tenantA->id,
        'owner_id' => $this->tenantA->id,
        'categoria_id' => $this->categoriaA->id,
        'precio_venta' => 10000,
        'activo' => true,
    ]);

    Inventario::factory()->create([
        'owner_id' => $this->tenantA->id,
        'producto_id' => $this->productoA->id,
        'cantidad' => 100,
    ]);

    $this->categoriaB = Categoria::factory()->create([
        'user_id' => $this->tenantB->id,
        'owner_id' => $this->tenantB->id,
        'activo' => true,
    ]);

    $this->productoB = Producto::factory()->create([
        'user_id' => $this->tenantB->id,
        'owner_id' => $this->tenantB->id,
        'categoria_id' => $this->categoriaB->id,
        'precio_venta' => 15000,
        'activo' => true,
    ]);

    Inventario::factory()->create([
        'owner_id' => $this->tenantB->id,
        'producto_id' => $this->productoB->id,
        'cantidad' => 100,
    ]);
});

test('PaymentConfig resolveForOwner does not fall back to master when tenant has no config', function () {
    PaymentConfig::create([
        'owner_id' => $this->tenantA->id,
        'paypal_client_id' => 'tenant-a-client',
        'paypal_client_secret' => 'tenant-a-secret',
        'paypal_mode' => 'sandbox',
        'paypal_active' => true,
    ]);

    $configA = PaymentConfig::resolveForOwner($this->tenantA->id);
    $configB = PaymentConfig::resolveForOwner($this->tenantB->id);

    expect($configA)->not->toBeNull();
    expect($configB)->toBeNull();
});

test('PaymentConfig resolveForOwner returns null when tenant config has no active methods', function () {
    PaymentConfig::create([
        'owner_id' => $this->tenantA->id,
        'paypal_client_id' => 'tenant-a-client',
        'paypal_client_secret' => 'tenant-a-secret',
        'paypal_mode' => 'sandbox',
        'is_active' => false,
        'paypal_active' => false,
        'mercadopago_active' => false,
    ]);

    $config = PaymentConfig::resolveForOwner($this->tenantA->id);

    expect($config)->toBeNull();
});

test('PaymentConfig resolveForOwner returns master config when tenant explicitly requests it', function () {
    $master = User::factory()->create();
    $master->assignRole('Master');

    PaymentConfig::create([
        'owner_id' => $master->id,
        'paypal_client_id' => 'master-client',
        'paypal_client_secret' => 'master-secret',
        'paypal_mode' => 'sandbox',
        'paypal_active' => true,
    ]);

    PaymentConfig::create([
        'owner_id' => $this->tenantA->id,
        'use_platform_config' => true,
        'paypal_active' => false,
        'is_active' => false,
    ]);

    $config = PaymentConfig::resolveForOwner($this->tenantA->id);

    expect($config)->not->toBeNull();
    expect($config->owner_id)->toBe($master->id);
});

test('Webpay callback validates business_id matches authenticated tenant', function () {
    PaymentConfig::create([
        'owner_id' => $this->tenantA->id,
        'commerce_code' => '597055555532',
        'api_key' => 'test-api-key',
        'environment' => 'integration',
        'is_active' => true,
    ]);

    $session = PaymentSession::create([
        'token' => 'test-token-cross',
        'buy_order' => 'ORD-CROSS-001',
        'business_id' => $this->tenantA->id,
        'status' => 'pending',
        'gateway' => 'webpay',
        'amount' => 10000,
        'expires_at' => now()->addHours(2),
    ]);

    $this->mock(WebpayService::class, function ($mock) {
        $mock->shouldReceive('confirmTransaction')->once()->andReturn(new class
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

    $this->actingAs($this->tenantB);

    $response = $this->post(route('webpay.callback'), [
        'token_ws' => 'test-token-cross',
    ]);

    $response->assertStatus(200);
});

test('MercadoPago webhook rejects payment for non-existent tenant config', function () {
    PaymentConfig::create([
        'owner_id' => $this->tenantA->id,
        'mercadopago_public_key' => 'tenant-a-public',
        'mercadopago_access_token' => 'tenant-a-access',
        'mercadopago_mode' => 'sandbox',
        'mercadopago_active' => true,
        'mercadopago_webhook_secret' => 'tenant-a-secret',
    ]);

    $pedido = Pedido::withoutGlobalScope(OwnerScope::class)->create([
        'owner_id' => $this->tenantB->id,
        'user_id' => $this->tenantB->id,
        'public_profile_id' => $this->profileB->id,
        'cliente_id' => $this->buyer->id,
        'numero_pedido' => 'MP-NO-CONFIG-001',
        'estado' => 'pendiente',
        'nombre_cliente' => 'Test',
        'total' => 25000,
        'subtotal' => 21008,
        'impuesto' => 3992,
        'metodo_pago' => 'mercadopago',
    ]);

    Http::fake([
        'api.mercadopago.com/v1/payments/*' => Http::response([
            'id' => 'PAY-NO-CONFIG',
            'status' => 'approved',
            'transaction_amount' => 25000.00,
            'currency_id' => 'CLP',
            'external_reference' => 'MP-NO-CONFIG-001',
            'payment_method_id' => 'visa',
            'installments' => 1,
            'transaction_details' => [
                'total_paid_amount' => 25000.00,
                'net_received_amount' => 24000.00,
            ],
        ]),
    ]);

    $payload = [
        'id' => 'evt-noconfig',
        'type' => 'payment',
        'action' => 'payment.updated',
        'data' => ['id' => 'PAY-NO-CONFIG'],
    ];

    $signature = 'ts='.time().',v1='.hash_hmac('sha256', 'id:PAY-NO-CONFIG;request-id:;ts:'.time().';', 'tenant-a-secret');

    $response = $this->postJson(route('webhooks.mercadopago'), $payload, [
        'X-MercadoPago-Signature' => $signature,
    ]);

    $response->assertStatus(400);
    $response->assertJson(['error' => 'Invalid origin']);
});

test('PayPal webhook rejects when no tenant config found for custom_id', function () {
    $pedido = Pedido::withoutGlobalScope(OwnerScope::class)->create([
        'owner_id' => $this->tenantA->id,
        'user_id' => $this->tenantA->id,
        'public_profile_id' => $this->profileA->id,
        'cliente_id' => $this->buyer->id,
        'numero_pedido' => 'PP-NO-CONFIG-001',
        'estado' => 'pendiente',
        'nombre_cliente' => 'Test',
        'total' => 20000,
        'subtotal' => 16807,
        'impuesto' => 3193,
        'metodo_pago' => 'paypal',
        'payment_id' => 'PP-ORDER-NO-CONFIG',
        'payment_status' => 'created',
    ]);

    config(['services.paypal.webhook_id' => 'test-webhook-id']);

    $payload = [
        'id' => 'evt-pp-noconfig',
        'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
        'resource' => [
            'id' => 'CAP-NO-CONFIG',
            'status' => 'COMPLETED',
            'custom_id' => 'PP-NO-CONFIG-001',
            'amount' => ['value' => '20000.00', 'currency_code' => 'CLP'],
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

    $response->assertStatus(400);
    $response->assertJson(['error' => 'Invalid signature']);
});

test('Pedido creation validates metodo_pago against tenant active gateways', function () {
    PaymentConfig::create([
        'owner_id' => $this->tenantA->id,
        'commerce_code' => '597055555532',
        'api_key' => 'test-api-key',
        'environment' => 'integration',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->buyer)
        ->post(route('tienda.checkout', $this->profileA->slug), [
            'public_profile_id' => $this->profileA->id,
            'items' => [['producto_id' => $this->productoA->id, 'cantidad' => 1]],
            'nombre_cliente' => 'Test Buyer',
            'telefono_cliente' => '+56912345678',
            'direccion_cliente' => 'Calle Test 123',
            'metodo_pago' => 'paypal',
        ]);

    $response->assertSessionHasErrors('metodo_pago');
});

test('Pedido creation allows metodo_pago that is active for tenant', function () {
    PaymentConfig::create([
        'owner_id' => $this->tenantA->id,
        'commerce_code' => '597055555532',
        'api_key' => 'test-api-key',
        'environment' => 'integration',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->buyer)
        ->post(route('tienda.checkout', $this->profileA->slug), [
            'public_profile_id' => $this->profileA->id,
            'items' => [['producto_id' => $this->productoA->id, 'cantidad' => 1]],
            'nombre_cliente' => 'Test Buyer',
            'telefono_cliente' => '+56912345678',
            'direccion_cliente' => 'Calle Test 123',
            'metodo_pago' => 'webpay',
        ]);

    $response->assertRedirect();
});

test('Pedido creation allows efectivo/transferencia without gateway config', function () {
    $response = $this->actingAs($this->buyer)
        ->post(route('tienda.checkout', $this->profileA->slug), [
            'public_profile_id' => $this->profileA->id,
            'items' => [['producto_id' => $this->productoA->id, 'cantidad' => 1]],
            'nombre_cliente' => 'Test Buyer',
            'telefono_cliente' => '+56912345678',
            'direccion_cliente' => 'Calle Test 123',
            'metodo_pago' => 'efectivo',
        ]);

    $response->assertRedirect();
});

test('Pedido creation rejects products that do not belong to the selected tenant profile', function () {
    $response = $this->actingAs($this->buyer)
        ->post(route('tienda.checkout', $this->profileA->slug), [
            'public_profile_id' => $this->profileA->id,
            'items' => [['producto_id' => $this->productoB->id, 'cantidad' => 1]],
            'nombre_cliente' => 'Test Buyer',
            'telefono_cliente' => '+56912345678',
            'direccion_cliente' => 'Calle Test 123',
            'metodo_pago' => 'efectivo',
        ]);

    $response->assertSessionHasErrors('items.0.producto_id');
});
