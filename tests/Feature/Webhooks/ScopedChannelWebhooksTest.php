<?php

use App\Events\WebhookReceived;
use App\Models\ChannelCredential;
use App\Models\ChannelLink;
use App\Models\SystemIntegration;
use App\Models\TelegramLinkingToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('global telegram webhook requires the configured secret token', function () {
    SystemIntegration::create([
        'provider' => 'telegram',
        'telegram_bot_token' => 'global-token',
        'telegram_webhook_secret' => 'global-secret',
        'is_active' => true,
    ]);

    $this->postJson(route('webhooks.telegram.global'), ['update_id' => 1])
        ->assertForbidden();

    $this->postJson(route('webhooks.telegram.global'), ['update_id' => 1], [
        'X-Telegram-Bot-Api-Secret-Token' => 'global-secret',
    ])->assertSuccessful()->assertJsonPath('status', 'ok');
});

test('tenant telegram webhook resolves only a custom tenant webhook key', function () {
    $tenant = User::factory()->create();
    ChannelCredential::create([
        'owner_id' => $tenant->getOwnerId(),
        'telegram_mode' => 'custom',
        'telegram_bot_token' => 'tenant-token',
        'telegram_webhook_secret' => 'tenant-secret',
        'telegram_webhook_key' => 'tenant-key',
    ]);

    $this->postJson(route('webhooks.telegram.tenant', ['key' => 'unknown']), ['update_id' => 1])
        ->assertNotFound();

    $this->postJson(route('webhooks.telegram.tenant', ['key' => 'tenant-key']), ['update_id' => 1], [
        'X-Telegram-Bot-Api-Secret-Token' => 'tenant-secret',
    ])->assertSuccessful();
});

test('tenant telegram webhook rejects a linking token issued for another bot scope', function () {
    $tenant = User::factory()->create();
    ChannelCredential::create([
        'owner_id' => $tenant->getOwnerId(),
        'telegram_mode' => 'custom',
        'telegram_bot_token' => 'tenant-token',
        'telegram_webhook_secret' => 'tenant-secret',
        'telegram_webhook_key' => 'tenant-key',
    ]);
    $linkingToken = TelegramLinkingToken::create([
        'owner_id' => $tenant->getOwnerId(),
        'user_id' => $tenant->id,
        'token' => 'global-link-token',
        'channel' => 'telegram',
        'bot_scope' => 'global',
        'bot_type' => 'global',
        'expires_at' => now()->addMinutes(15),
    ]);

    Http::fake();

    $this->postJson(route('webhooks.telegram.tenant', ['key' => 'tenant-key']), [
        'message' => [
            'chat' => ['id' => 12345],
            'text' => '/start '.$linkingToken->token,
        ],
    ], [
        'X-Telegram-Bot-Api-Secret-Token' => 'tenant-secret',
    ])->assertSuccessful();

    expect($linkingToken->fresh()->used_at)->toBeNull()
        ->and(ChannelLink::count())->toBe(0);

    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'bottenant-token/sendMessage'));
});

test('tenant telegram webhook links a chat only within its tenant scope', function () {
    $tenant = User::factory()->create();
    ChannelCredential::create([
        'owner_id' => $tenant->getOwnerId(),
        'telegram_mode' => 'custom',
        'telegram_bot_token' => 'tenant-token',
        'telegram_webhook_secret' => 'tenant-secret',
        'telegram_webhook_key' => 'tenant-key',
    ]);
    $linkingToken = TelegramLinkingToken::create([
        'owner_id' => $tenant->getOwnerId(),
        'user_id' => $tenant->id,
        'token' => 'tenant-link-token',
        'channel' => 'telegram',
        'bot_scope' => 'tenant:'.$tenant->getOwnerId(),
        'bot_type' => 'custom',
        'expires_at' => now()->addMinutes(15),
    ]);

    Http::fake();

    $this->postJson(route('webhooks.telegram.tenant', ['key' => 'tenant-key']), [
        'message' => [
            'chat' => ['id' => 54321],
            'text' => '/start '.$linkingToken->token,
        ],
    ], [
        'X-Telegram-Bot-Api-Secret-Token' => 'tenant-secret',
    ])->assertSuccessful();

    expect($linkingToken->fresh()->used_at)->not->toBeNull()
        ->and($linkingToken->fresh()->external_id)->toBe('54321');

    $this->assertDatabaseHas('channel_links', [
        'owner_id' => $tenant->getOwnerId(),
        'channel' => 'telegram',
        'bot_scope' => 'tenant:'.$tenant->getOwnerId(),
        'external_id' => '54321',
    ]);

    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'bottenant-token/sendMessage'));
});

test('expired global telegram linking tokens reply through the global bot', function () {
    $user = User::factory()->create();

    SystemIntegration::create([
        'provider' => 'telegram',
        'telegram_bot_token' => 'global-token',
        'telegram_webhook_secret' => 'global-secret',
        'is_active' => true,
    ]);
    TelegramLinkingToken::create([
        'owner_id' => $user->getOwnerId(),
        'user_id' => $user->id,
        'token' => 'expired-global-token',
        'channel' => 'telegram',
        'bot_scope' => 'global',
        'expires_at' => now()->subMinute(),
    ]);

    Http::fake();

    $this->postJson(route('webhooks.telegram.global'), [
        'message' => [
            'chat' => ['id' => 13579],
            'text' => '/start expired-global-token',
        ],
    ], [
        'X-Telegram-Bot-Api-Secret-Token' => 'global-secret',
    ])->assertSuccessful();

    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'botglobal-token/sendMessage'));
});

test('global whatsapp webhook verifies the challenge and signed payload', function () {
    SystemIntegration::create([
        'provider' => 'whatsapp',
        'whatsapp_phone_number_id' => 'phone-1',
        'whatsapp_access_token' => 'access-token',
        'whatsapp_verify_token' => 'verify-secret',
        'whatsapp_app_secret' => 'app-secret',
        'is_active' => true,
    ]);

    $this->get(route('webhooks.whatsapp.global').'?hub.mode=subscribe&hub.verify_token=verify-secret&hub.challenge=challenge-value')
        ->assertSuccessful()
        ->assertSeeText('challenge-value');

    $payload = ['entry' => [['changes' => [['field' => 'messages']]]]];
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = 'sha256='.hash_hmac('sha256', $body, 'app-secret');

    Event::fake([WebhookReceived::class]);

    $this->call(
        'POST',
        route('webhooks.whatsapp.global'),
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $signature,
        ],
        $body
    )->assertSuccessful();

    Event::assertDispatched(WebhookReceived::class, fn (WebhookReceived $event): bool => $event->businessId === null);
});

test('tenant whatsapp webhook verifies its signature and emits the tenant owner', function () {
    $tenant = User::factory()->create();
    ChannelCredential::create([
        'owner_id' => $tenant->getOwnerId(),
        'whatsapp_mode' => 'custom',
        'whatsapp_phone_number_id' => 'tenant-phone',
        'whatsapp_access_token' => 'tenant-access-token',
        'whatsapp_verify_token' => 'tenant-verify-secret',
        'whatsapp_app_secret' => 'tenant-app-secret',
        'whatsapp_webhook_key' => 'tenant-whatsapp-key',
    ]);

    $payload = ['entry' => [['changes' => [['field' => 'messages']]]]];
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = 'sha256='.hash_hmac('sha256', $body, 'tenant-app-secret');

    Event::fake([WebhookReceived::class]);

    $this->call(
        'POST',
        route('webhooks.whatsapp.tenant', ['key' => 'tenant-whatsapp-key']),
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $signature,
        ],
        $body
    )->assertSuccessful();

    Event::assertDispatched(
        WebhookReceived::class,
        fn (WebhookReceived $event): bool => $event->businessId === $tenant->getOwnerId()
    );
});

test('whatsapp webhook rejects invalid signatures and unknown tenant keys', function () {
    SystemIntegration::create([
        'provider' => 'whatsapp',
        'whatsapp_phone_number_id' => 'phone-1',
        'whatsapp_access_token' => 'access-token',
        'whatsapp_verify_token' => 'verify-secret',
        'whatsapp_app_secret' => 'app-secret',
        'is_active' => true,
    ]);

    $this->postJson(route('webhooks.whatsapp.global'), ['entry' => []], [
        'X-Hub-Signature-256' => 'sha256=invalid',
    ])->assertForbidden();

    $this->postJson(route('webhooks.whatsapp.tenant', ['key' => 'unknown']), ['entry' => []])
        ->assertNotFound();
});

test('whatsapp webhook rejects a signed event for a different phone number', function () {
    SystemIntegration::create([
        'provider' => 'whatsapp',
        'whatsapp_phone_number_id' => 'configured-phone',
        'whatsapp_access_token' => 'access-token',
        'whatsapp_verify_token' => 'verify-secret',
        'whatsapp_app_secret' => 'app-secret',
        'is_active' => true,
    ]);

    $payload = ['entry' => [['changes' => [[
        'field' => 'messages',
        'value' => ['metadata' => ['phone_number_id' => 'other-phone']],
    ]]]]];
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = 'sha256='.hash_hmac('sha256', $body, 'app-secret');

    Event::fake([WebhookReceived::class]);

    $this->call(
        'POST',
        route('webhooks.whatsapp.global'),
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $signature,
        ],
        $body
    )->assertForbidden();

    Event::assertNotDispatched(WebhookReceived::class);
});

test('webhook registration dry run does not persist keys or call external APIs', function () {
    SystemIntegration::create([
        'provider' => 'telegram',
        'telegram_bot_token' => 'global-token',
        'is_active' => true,
    ]);
    $tenant = User::factory()->create();
    $credential = ChannelCredential::create([
        'owner_id' => $tenant->getOwnerId(),
        'telegram_mode' => 'custom',
        'telegram_bot_token' => 'tenant-token',
    ]);

    Http::preventStrayRequests();

    $this->artisan('channels:register-webhooks', ['--dry-run' => true])
        ->assertSuccessful();

    expect($credential->fresh()->telegram_webhook_key)->toBeNull()
        ->and($credential->fresh()->telegram_webhook_secret)->toBeNull();
});

test('webhook registration configures global and tenant telegram callbacks with secrets', function () {
    SystemIntegration::create([
        'provider' => 'telegram',
        'telegram_bot_token' => 'global-token',
        'is_active' => true,
    ]);
    $tenant = User::factory()->create();
    $credential = ChannelCredential::create([
        'owner_id' => $tenant->getOwnerId(),
        'telegram_mode' => 'custom',
        'telegram_bot_token' => 'tenant-token',
    ]);

    Http::fake([
        'api.telegram.org/*/setWebhook' => Http::response(['ok' => true, 'result' => true]),
    ]);

    $this->artisan('channels:register-webhooks')
        ->assertSuccessful();

    expect($credential->fresh()->telegram_webhook_key)->not->toBeEmpty()
        ->and($credential->fresh()->telegram_webhook_secret)->not->toBeEmpty();

    Http::assertSentCount(2);
    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.telegram.org/botglobal-token/setWebhook'
        && str_ends_with($request['url'], '/webhooks/telegram/global')
        && filled($request['secret_token']));
    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.telegram.org/bottenant-token/setWebhook'
        && str_contains($request['url'], '/webhooks/telegram/tenant/')
        && filled($request['secret_token']));
});

test('webhook registration subscribes configured global and tenant whatsapp resources', function () {
    SystemIntegration::create([
        'provider' => 'whatsapp',
        'whatsapp_phone_number_id' => 'global-phone',
        'whatsapp_access_token' => 'global-access-token',
        'whatsapp_business_id' => 'global-business',
        'whatsapp_api_version' => 'v22.0',
        'whatsapp_app_secret' => 'global-app-secret',
        'is_active' => true,
    ]);
    $tenant = User::factory()->create();
    $credential = ChannelCredential::create([
        'owner_id' => $tenant->getOwnerId(),
        'whatsapp_mode' => 'custom',
        'whatsapp_phone_number_id' => 'tenant-phone',
        'whatsapp_access_token' => 'tenant-access-token',
        'whatsapp_business_id' => 'tenant-business',
        'whatsapp_api_version' => 'v22.0',
        'whatsapp_app_secret' => 'tenant-app-secret',
    ]);

    Http::fake([
        'graph.facebook.com/*/subscribed_apps' => Http::response(['success' => true]),
    ]);

    $this->artisan('channels:register-webhooks')
        ->expectsOutputToContain(route('webhooks.whatsapp.global'))
        ->assertSuccessful();

    expect($credential->fresh()->whatsapp_webhook_key)->not->toBeEmpty()
        ->and($credential->fresh()->whatsapp_verify_token)->not->toBeEmpty();

    Http::assertSentCount(2);
    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/global-business/subscribed_apps')
        && $request->hasHeader('Authorization', 'Bearer global-access-token'));
    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/tenant-business/subscribed_apps')
        && $request->hasHeader('Authorization', 'Bearer tenant-access-token'));
});
