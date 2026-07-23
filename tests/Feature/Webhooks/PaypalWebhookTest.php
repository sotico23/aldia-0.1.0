<?php

use App\Models\PaymentConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'Master', 'guard_name' => 'web']);

    $this->master = User::factory()->create();
    $this->master->assignRole('Master');

    config(['services.paypal.webhook_id' => 'test-webhook-id']);

    Http::fake([
        'api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' => Http::response([
            'verification_status' => 'VERIFIED',
        ]),
    ]);

    PaymentConfig::create([
        'owner_id' => $this->master->id,
        'commerce_code' => 'PP-TEST',
        'api_key' => 'test-api-key',
        'environment' => 'integration',
        'is_active' => true,
        'paypal_client_id' => 'test-client-id',
        'paypal_client_secret' => 'test-client-secret',
        'paypal_mode' => 'sandbox',
        'paypal_active' => true,
    ]);
});

function paypalHeaders(): array
{
    return [
        'PAYPAL-TRANSMISSION-ID' => 'txn-'.fake()->uuid(),
        'PAYPAL-TRANSMISSION-TIME' => now()->toIso8601String(),
        'PAYPAL-TRANSMISSION-SIG' => 'sig_'.fake()->sha1(),
        'PAYPAL-CERT-URL' => 'https://api.paypal.com/cert',
        'PAYPAL-AUTH-ALGO' => 'SHA256WithRSA',
    ];
}

it('accepts paypal payment capture completed webhook', function () {
    $payload = [
        'id' => 'WH-'.fake()->uuid(),
        'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
        'resource' => [
            'id' => 'CAPTURE-'.fake()->uuid(),
            'amount' => [
                'value' => '100.00',
                'currency_code' => 'USD',
            ],
            'status' => 'COMPLETED',
        ],
        'summary' => 'Payment completed',
    ];

    $response = $this->postJson(route('webhooks.paypal'), $payload, paypalHeaders());

    $response->assertOk();
    $response->assertJson(['status' => 'ok']);

    $this->assertDatabaseHas('transactions', [
        'gateway' => 'paypal',
        'amount' => 100.00,
        'type' => 'customer_payment',
        'status' => 'approved',
    ]);
});

it('accepts paypal subscription activated webhook', function () {
    $payload = [
        'id' => 'WH-'.fake()->uuid(),
        'event_type' => 'BILLING.SUBSCRIPTION.ACTIVATED',
        'resource' => [
            'id' => 'SUB-'.fake()->uuid(),
            'status' => 'ACTIVE',
        ],
    ];

    $response = $this->postJson(route('webhooks.paypal'), $payload, paypalHeaders());

    $response->assertOk();
    $response->assertJson(['status' => 'ok']);
});

it('accepts paypal subscription cancelled webhook', function () {
    $payload = [
        'id' => 'WH-'.fake()->uuid(),
        'event_type' => 'BILLING.SUBSCRIPTION.CANCELLED',
        'resource' => [
            'id' => 'SUB-'.fake()->uuid(),
            'status' => 'CANCELLED',
        ],
    ];

    $response = $this->postJson(route('webhooks.paypal'), $payload, paypalHeaders());

    $response->assertOk();
    $response->assertJson(['status' => 'ok']);
});

it('accepts paypal payment failed webhook', function () {
    $payload = [
        'id' => 'WH-'.fake()->uuid(),
        'event_type' => 'PAYMENT.CAPTURE.DENIED',
        'resource' => [
            'id' => 'CAPTURE-'.fake()->uuid(),
            'amount' => [
                'value' => '50.00',
                'currency_code' => 'USD',
            ],
            'status' => 'DENIED',
        ],
    ];

    $response = $this->postJson(route('webhooks.paypal'), $payload, paypalHeaders());

    $response->assertOk();
    $response->assertJson(['status' => 'ok']);

    $this->assertDatabaseHas('transactions', [
        'gateway' => 'paypal',
        'amount' => 50.00,
        'type' => 'customer_payment',
        'status' => 'failed',
    ]);
});

it('ignores duplicate paypal webhooks', function () {
    $eventId = 'WH-DUP-'.fake()->uuid();
    Cache::put('paypal_webhook_'.$eventId, true, 3600);

    $payload = [
        'id' => $eventId,
        'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
        'resource' => [
            'id' => 'CAPTURE-'.fake()->uuid(),
            'amount' => ['value' => '50.00', 'currency_code' => 'USD'],
            'status' => 'COMPLETED',
        ],
    ];

    $response = $this->postJson(route('webhooks.paypal'), $payload, paypalHeaders());

    $response->assertOk();
    $response->assertJson(['status' => 'duplicate_ignored']);
});

it('handles unknown paypal webhook event type gracefully', function () {
    $payload = [
        'id' => 'WH-'.fake()->uuid(),
        'event_type' => 'UNKNOWN.EVENT.TYPE',
        'resource' => ['id' => 'RES-'.fake()->uuid()],
    ];

    $response = $this->postJson(route('webhooks.paypal'), $payload, paypalHeaders());

    $response->assertOk();
    $response->assertJson(['status' => 'ignored']);
});
