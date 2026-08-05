<?php

use App\Models\ChannelCredential;
use App\Models\SystemIntegration;
use App\Models\User;
use App\Models\WebSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('guest cannot access canales page', function () {
    $response = $this->get(route('channel-credentials.index'));

    $response->assertRedirect(route('login'));
});

test('index returns inertia page with credentials and automation', function () {
    ChannelCredential::create([
        'owner_id' => $this->user->getOwnerId(),
        'telegram_bot_token' => 'test:token',
        'telegram_bot_username' => 'test_bot',
        'whatsapp_phone_number_id' => '123456',
        'whatsapp_access_token' => 'test_token',
        'whatsapp_business_id' => 'bus_123',
        'whatsapp_api_version' => 'v22.0',
    ]);

    $response = $this->actingAs($this->user)->get(route('channel-credentials.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Backend/ChannelCredentials')
        ->has('credentials')
        ->where('credentials.telegram_bot_username', 'test_bot')
        ->where('has_credentials', true)
    );
});

test('index does not expose tokens in response', function () {
    ChannelCredential::create([
        'owner_id' => $this->user->getOwnerId(),
        'telegram_bot_token' => 'secret_token_123',
        'whatsapp_access_token' => 'secret_whatsapp_456',
    ]);

    $response = $this->actingAs($this->user)->get(route('channel-credentials.index'));

    $response->assertInertia(fn ($page) => $page
        ->where('credentials.telegram_bot_token', '••••••••••••••••')
        ->where('credentials.whatsapp_access_token', '••••••••••••••••')
    );
});

test('index returns has_credentials false when no credentials', function () {
    $response = $this->actingAs($this->user)->get(route('channel-credentials.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->where('has_credentials', false)
        ->where('credentials', null)
    );
});

test('update stores new credentials', function () {
    $response = $this->actingAs($this->user)->put(route('channel-credentials.update'), [
        'telegram_bot_token' => 'new_telegram_token',
        'telegram_bot_username' => 'new_bot',
        'whatsapp_phone_number_id' => '987654',
        'whatsapp_access_token' => 'new_whatsapp_token',
        'whatsapp_business_id' => 'bus_999',
        'whatsapp_api_version' => 'v22.0',
    ]);

    $response->assertRedirect(route('channel-credentials.index'));

    $this->assertDatabaseHas('channel_credentials', [
        'owner_id' => $this->user->getOwnerId(),
        'telegram_bot_username' => 'new_bot',
        'whatsapp_phone_number_id' => '987654',
        'whatsapp_business_id' => 'bus_999',
        'whatsapp_api_version' => 'v22.0',
    ]);

    $credential = ChannelCredential::where('owner_id', $this->user->getOwnerId())->first();
    expect($credential->telegram_bot_token)->toBe('new_telegram_token');
    expect($credential->whatsapp_access_token)->toBe('new_whatsapp_token');
});

test('update ignores masked token placeholders', function () {
    ChannelCredential::create([
        'owner_id' => $this->user->getOwnerId(),
        'telegram_bot_token' => 'existing_token',
        'whatsapp_access_token' => 'whatsapp_existing',
    ]);

    $response = $this->actingAs($this->user)->put(route('channel-credentials.update'), [
        'telegram_bot_token' => '••••••••••••••••',
        'whatsapp_access_token' => '••••••••••••••••',
    ]);

    $response->assertRedirect(route('channel-credentials.index'));

    $credential = ChannelCredential::where('owner_id', $this->user->getOwnerId())->first();
    expect($credential->telegram_bot_token)->toBe('existing_token');
    expect($credential->whatsapp_access_token)->toBe('whatsapp_existing');
});

test('update validates required fields types', function () {
    $response = $this->actingAs($this->user)->put(route('channel-credentials.update'), [
        'telegram_bot_token' => str_repeat('a', 300),
    ], ['Accept' => 'application/json']);

    $response->assertStatus(422);
});

test('test-telegram returns error when no token configured', function () {
    $response = $this->actingAs($this->user)->post(route('channel-credentials.test-telegram'), [], [
        'Accept' => 'application/json',
    ]);

    $response->assertJson([
        'success' => false,
        'message' => 'No hay un Token de Bot de Telegram configurado. Ingresa un token o guárdalo primero.',
    ]);
});

test('test-telegram posts test_connection payload to n8n telegram proxy url and succeeds', function () {
    ChannelCredential::create([
        'owner_id' => $this->user->getOwnerId(),
        'telegram_bot_token' => 'test:token',
        'telegram_bot_username' => 'test_bot',
    ]);

    SystemIntegration::create([
        'provider' => 'n8n',
        'base_url' => 'https://n8n.example.com',
        'webhook_url' => 'https://n8n.example.com/webhook/test',
        'telegram_proxy_url' => 'https://n8n.example.com/webhook/telegram-proxy',
        'api_key' => 'secret',
        'is_active' => true,
    ]);

    Http::fake([
        'api.telegram.org*' => Http::response([
            'ok' => true,
            'result' => ['id' => 1, 'username' => 'test_bot', 'first_name' => 'Test Bot'],
        ], 200),
        'n8n.example.com/*' => Http::response(['status' => 'ok'], 200),
    ]);

    $response = $this->actingAs($this->user)->post(route('channel-credentials.test-telegram'), [
        'telegram_bot_token' => 'test:token',
    ], ['Accept' => 'application/json']);

    $response->assertJson([
        'success' => true,
        'bot_username' => 'test_bot',
    ]);

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $request->url() === 'https://n8n.example.com/webhook/telegram-proxy'
            && $request->method() === 'POST'
            && $body['event'] === 'test_connection'
            && $body['is_test'] === true
            && $body['tenant_id'] === $this->user->getOwnerId()
            && $body['bot_token'] === 'test:token'
            && $body['bot_username'] === 'test_bot'
            && $body['is_linked'] === false
            && $body['callback_url'] === route('api.canales.telegram.webhook');
    });
});

test('test-telegram fails when n8n telegram proxy responds with error', function () {
    ChannelCredential::create([
        'owner_id' => $this->user->getOwnerId(),
        'telegram_bot_token' => 'test:token',
        'telegram_bot_username' => 'test_bot',
    ]);

    SystemIntegration::create([
        'provider' => 'n8n',
        'base_url' => 'https://n8n.example.com',
        'telegram_proxy_url' => 'https://n8n.example.com/webhook/telegram-proxy',
        'api_key' => 'secret',
        'is_active' => true,
    ]);

    Http::fake([
        'api.telegram.org*' => Http::response([
            'ok' => true,
            'result' => ['id' => 1, 'username' => 'test_bot', 'first_name' => 'Test Bot'],
        ], 200),
        'n8n.example.com/*' => Http::response(['status' => 'error'], 500),
    ]);

    $response = $this->actingAs($this->user)->post(route('channel-credentials.test-telegram'), [
        'telegram_bot_token' => 'test:token',
    ], ['Accept' => 'application/json']);

    $response->assertJson([
        'success' => false,
    ]);

    $response->assertJsonPath('message', fn ($message) => str_contains($message, 'n8n respondió con estado 500'));
});

test('test-telegram fails with guidance when no n8n url is configured', function () {
    config([
        'services.n8n.webhook_url' => null,
        'services.n8n.telegram_proxy_url' => null,
        'services.n8n.base_url' => null,
    ]);

    ChannelCredential::create([
        'owner_id' => $this->user->getOwnerId(),
        'telegram_bot_token' => 'test:token',
        'telegram_bot_username' => 'test_bot',
    ]);

    Http::fake([
        'api.telegram.org*' => Http::response([
            'ok' => true,
            'result' => ['id' => 1, 'username' => 'test_bot', 'first_name' => 'Test Bot'],
        ], 200),
    ]);

    $response = $this->actingAs($this->user)->post(route('channel-credentials.test-telegram'), [
        'telegram_bot_token' => 'test:token',
    ], ['Accept' => 'application/json']);

    $response->assertJson([
        'success' => false,
    ]);

    $response->assertJsonPath('message', fn ($message) => str_contains($message, 'No hay URL de proxy de Telegram configurada'));
});

test('test-whatsapp returns error when no credentials configured', function () {
    $response = $this->actingAs($this->user)->post(route('channel-credentials.test-whatsapp'), [], [
        'Accept' => 'application/json',
    ]);

    $response->assertJson([
        'success' => false,
        'message' => 'Credenciales de WhatsApp no configuradas.',
    ]);
});

test('index returns global_telegram_bot_username and app_name props', function () {
    WebSetting::create([
        'app_name' => 'Aldia',
        'global_telegram_bot_username' => 'aldia_global_bot',
    ]);

    $response = $this->actingAs($this->user)->get(route('channel-credentials.index'));

    $response->assertInertia(fn ($page) => $page
        ->where('global_telegram_bot_username', 'aldia_global_bot')
        ->where('app_name', 'Aldia')
    );
});

test('index returns bot_type in credentials', function () {
    ChannelCredential::create([
        'owner_id' => $this->user->getOwnerId(),
        'telegram_bot_token' => 'test:token',
        'telegram_bot_username' => 'test_bot',
        'bot_type' => 'global',
    ]);

    $response = $this->actingAs($this->user)->get(route('channel-credentials.index'));

    $response->assertInertia(fn ($page) => $page
        ->where('credentials.bot_type', 'global')
    );
});

test('update stores bot_type field', function () {
    ChannelCredential::create([
        'owner_id' => $this->user->getOwnerId(),
        'telegram_bot_token' => 'existing_token',
        'telegram_bot_username' => 'existing_bot',
    ]);

    $response = $this->actingAs($this->user)->put(route('channel-credentials.update'), [
        'telegram_bot_token' => 'new_token',
        'bot_type' => 'global',
    ]);

    $response->assertRedirect(route('channel-credentials.index'));

    $this->assertDatabaseHas('channel_credentials', [
        'owner_id' => $this->user->getOwnerId(),
        'bot_type' => 'global',
    ]);
});

test('test-telemark and test-whatsapp rate limited', function () {
    for ($i = 0; $i < 10; $i++) {
        $this->actingAs($this->user)->post(route('channel-credentials.test-telegram'), [], [
            'Accept' => 'application/json',
        ]);
    }

    $response = $this->actingAs($this->user)->post(route('channel-credentials.test-telegram'), [], [
        'Accept' => 'application/json',
    ]);

    $response->assertStatus(429);
});
