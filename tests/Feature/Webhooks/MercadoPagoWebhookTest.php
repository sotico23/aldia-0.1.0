<?php

use App\Models\PaymentConfig;
use App\Models\Pedido;
use App\Models\User;
use App\Scopes\OwnerScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function mpSignature(array $payload, string $secret, string $requestId = ''): string
{
    $dataId = $payload['data']['id'] ?? $payload['id'] ?? '';
    $ts = (string) time();
    $manifest = "id:{$dataId};request-id:{$requestId};ts:{$ts};";
    $hash = hash_hmac('sha256', $manifest, $secret);

    return "ts={$ts},v1={$hash}";
}

beforeEach(function () {
    Role::firstOrCreate(['name' => 'Master', 'guard_name' => 'web']);

    $this->webhookSecret = 'test-mp-webhook-secret-12345';

    $this->master = User::factory()->create();
    $this->master->assignRole('Master');

    PaymentConfig::create([
        'owner_id' => $this->master->id,
        'commerce_code' => 'MP-TEST',
        'api_key' => 'test-api-key',
        'environment' => 'integration',
        'is_active' => true,
        'mercadopago_access_token' => 'test-access-token',
        'mercadopago_public_key' => 'test-public-key',
        'mercadopago_mode' => 'sandbox',
        'mercadopago_active' => true,
        'mercadopago_webhook_secret' => $this->webhookSecret,
    ]);

    $this->buyer = User::factory()->create(['name' => 'Buyer']);
    $this->pedido = Pedido::withoutGlobalScope(OwnerScope::class)->create([
        'owner_id' => $this->master->id,
        'user_id' => $this->master->id,
        'cliente_id' => $this->buyer->id,
        'numero_pedido' => 'TEST-123',
        'metodo_pago' => 'mercadopago',
        'total' => 250.00,
        'subtotal' => 250.00,
        'estado' => 'pendiente',
        'payment_status' => 'pending',
    ]);
});

it('accepts mercadopago payment created webhook', function () {
    $paymentId = 'PAY-'.fake()->uuid();

    Http::fake([
        'api.mercadopago.com/v1/payments/*' => Http::response([
            'id' => $paymentId,
            'status' => 'approved',
            'transaction_amount' => 250.00,
            'currency_id' => 'CLP',
            'external_reference' => 'TEST-123',
            'payment_method_id' => 'visa',
            'installments' => 1,
            'transaction_details' => [
                'total_paid_amount' => 250.00,
                'net_received_amount' => 240.00,
            ],
        ]),
    ]);

    $payload = [
        'id' => fake()->uuid(),
        'type' => 'payment.created',
        'action' => 'payment.created',
        'data' => ['id' => $paymentId],
    ];

    $response = $this->postJson(route('webhooks.mercadopago'), $payload, [
        'X-MercadoPago-Signature' => mpSignature($payload, $this->webhookSecret),
    ]);

    $response->assertOk();
});

it('accepts mercadopago payment updated webhook with approved payment', function () {
    $paymentId = 'PAY-'.fake()->uuid();

    Http::fake([
        'api.mercadopago.com/v1/payments/*' => Http::response([
            'id' => $paymentId,
            'status' => 'approved',
            'status_detail' => 'accredited',
            'transaction_amount' => 250.00,
            'currency_id' => 'CLP',
            'external_reference' => 'TEST-123',
            'payment_method_id' => 'visa',
            'installments' => 1,
            'transaction_details' => [
                'total_paid_amount' => 250.00,
                'net_received_amount' => 240.00,
            ],
        ]),
    ]);

    $payload = [
        'id' => fake()->uuid(),
        'type' => 'payment',
        'action' => 'payment.updated',
        'data' => ['id' => $paymentId],
    ];

    $response = $this->postJson(route('webhooks.mercadopago'), $payload, [
        'X-MercadoPago-Signature' => mpSignature($payload, $this->webhookSecret),
    ]);

    $response->assertOk();
    $response->assertJson(['status' => 'ok']);

    $this->assertDatabaseHas('transactions', [
        'gateway' => 'mercadopago',
        'gateway_transaction_id' => $paymentId,
        'amount' => 250.00,
        'fee' => 10.00,
        'net_amount' => 240.00,
        'type' => 'customer_payment',
        'status' => 'approved',
    ]);
});

it('accepts mercadopago payment cancelled webhook', function () {
    $paymentId = 'PAY-'.fake()->uuid();

    Http::fake([
        'api.mercadopago.com/v1/payments/*' => Http::response([
            'id' => $paymentId,
            'status' => 'cancelled',
            'transaction_amount' => 100.00,
            'currency_id' => 'CLP',
            'status_detail' => 'by_payer',
            'external_reference' => 'TEST-123',
            'transaction_details' => [
                'total_paid_amount' => 0,
                'net_received_amount' => 0,
            ],
        ]),
    ]);

    $payload = [
        'id' => fake()->uuid(),
        'type' => 'payment',
        'action' => 'payment.updated',
        'data' => ['id' => $paymentId],
    ];

    $response = $this->postJson(route('webhooks.mercadopago'), $payload, [
        'X-MercadoPago-Signature' => mpSignature($payload, $this->webhookSecret),
    ]);

    $response->assertOk();
    $response->assertJson(['status' => 'ok']);

    $this->assertDatabaseHas('transactions', [
        'gateway' => 'mercadopago',
        'gateway_transaction_id' => $paymentId,
        'status' => 'failed',
    ]);
});

it('accepts mercadopago merchant order webhook', function () {
    $dataId = 'ORD-'.fake()->uuid();

    Http::fake([
        'api.mercadopago.com/v1/payments/*' => Http::response([
            'id' => $dataId,
            'status' => 'approved',
            'transaction_amount' => 250.00,
            'currency_id' => 'CLP',
            'external_reference' => 'TEST-123',
            'transaction_details' => [
                'total_paid_amount' => 250.00,
                'net_received_amount' => 240.00,
            ],
        ]),
    ]);

    $payload = [
        'id' => fake()->uuid(),
        'type' => 'merchant_order',
        'action' => 'merchant_order.created',
        'data' => ['id' => $dataId],
    ];

    $response = $this->postJson(route('webhooks.mercadopago'), $payload, [
        'X-MercadoPago-Signature' => mpSignature($payload, $this->webhookSecret),
    ]);

    $response->assertOk();
    $response->assertJson(['status' => 'ok']);
});

it('ignores duplicate mercadopago webhooks', function () {
    $eventId = 'DUP-'.fake()->uuid();
    Cache::put('mercadopago_webhook_'.$eventId, true, 3600);

    $dataId = 'PAY-'.fake()->uuid();

    Http::fake([
        'api.mercadopago.com/v1/payments/*' => Http::response([
            'id' => $dataId,
            'status' => 'approved',
            'transaction_amount' => 250.00,
            'currency_id' => 'CLP',
            'external_reference' => 'TEST-123',
            'transaction_details' => [
                'total_paid_amount' => 250.00,
                'net_received_amount' => 240.00,
            ],
        ]),
    ]);

    $payload = [
        'id' => $eventId,
        'type' => 'payment',
        'data' => ['id' => $dataId],
    ];

    $response = $this->postJson(route('webhooks.mercadopago'), $payload, [
        'X-MercadoPago-Signature' => mpSignature($payload, $this->webhookSecret),
    ]);

    $response->assertOk();
    $response->assertJson(['status' => 'duplicate_ignored']);
});

it('handles unknown mercadopago event type gracefully', function () {
    $dataId = 'RES-'.fake()->uuid();

    Http::fake([
        'api.mercadopago.com/v1/payments/*' => Http::response([
            'id' => $dataId,
            'status' => 'approved',
            'transaction_amount' => 250.00,
            'currency_id' => 'CLP',
            'external_reference' => 'TEST-123',
            'transaction_details' => [
                'total_paid_amount' => 250.00,
                'net_received_amount' => 240.00,
            ],
        ]),
    ]);

    $payload = [
        'id' => fake()->uuid(),
        'type' => 'unknown.event.type',
        'data' => ['id' => $dataId],
    ];

    $response = $this->postJson(route('webhooks.mercadopago'), $payload, [
        'X-MercadoPago-Signature' => mpSignature($payload, $this->webhookSecret),
    ]);

    $response->assertOk();
    $response->assertJson(['status' => 'ignored']);
});

it('rejects mercadopago webhook without signature header', function () {
    Http::fake();

    $payload = [
        'id' => fake()->uuid(),
        'type' => 'payment',
        'data' => ['id' => 'PAY-'.fake()->uuid()],
    ];

    $response = $this->postJson(route('webhooks.mercadopago'), $payload);

    $response->assertStatus(400);
    $response->assertJson(['error' => 'Invalid origin']);
});

it('rejects mercadopago webhook with invalid signature', function () {
    Http::fake();

    $payload = [
        'id' => fake()->uuid(),
        'type' => 'payment.created',
        'data' => ['id' => 'PAY-'.fake()->uuid()],
    ];

    $response = $this->postJson(route('webhooks.mercadopago'), $payload, [
        'X-MercadoPago-Signature' => 'ts=1234567890,v1=invalidsignature',
    ]);

    $response->assertStatus(400);
    $response->assertJson(['error' => 'Invalid origin']);
});
