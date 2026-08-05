<?php

use App\Models\ChannelCredential;
use App\Models\SystemIntegration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('guest cannot access tenant n8n config endpoints', function () {
    $this->get(route('channel-credentials.n8n-config'))->assertRedirect(route('login'));
    $this->put(route('channel-credentials.n8n-config.update'))->assertRedirect(route('login'));
    $this->post(route('channel-credentials.n8n-config.test'))->assertRedirect(route('login'));
});

test('show returns empty config when no credentials exist', function () {
    $response = $this->actingAs($this->user)->getJson(route('channel-credentials.n8n-config'));

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'data' => [
                'n8n_base_url' => '',
                'n8n_telegram_proxy_webhook_url' => '',
                'has_api_key' => false,
                'masked_api_key' => '',
            ],
        ]);
});

test('show masks the api key and never exposes plaintext', function () {
    ChannelCredential::create([
        'owner_id' => $this->user->getOwnerId(),
        'n8n_base_url' => 'https://tenant.example.com',
        'n8n_telegram_proxy_webhook_url' => 'https://tenant.example.com/webhook/proxy',
        'n8n_api_key' => 'super_secret_key_123',
    ]);

    $response = $this->actingAs($this->user)->getJson(route('channel-credentials.n8n-config'));

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'data' => [
                'n8n_base_url' => 'https://tenant.example.com',
                'n8n_telegram_proxy_webhook_url' => 'https://tenant.example.com/webhook/proxy',
                'has_api_key' => true,
                'masked_api_key' => '••••••••••••••••',
            ],
        ])
        ->assertDontSee('super_secret_key_123');
});

test('update saves the webhook url and encrypts the api key', function () {
    $response = $this->actingAs($this->user)->putJson(route('channel-credentials.n8n-config.update'), [
        'n8n_base_url' => 'https://tenant.example.com',
        'n8n_telegram_proxy_webhook_url' => 'https://tenant.example.com/webhook/proxy',
        'n8n_api_key' => 'my_secret_api_key',
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'data' => [
                'n8n_base_url' => 'https://tenant.example.com',
                'n8n_telegram_proxy_webhook_url' => 'https://tenant.example.com/webhook/proxy',
                'has_api_key' => true,
            ],
        ]);

    $credential = ChannelCredential::where('owner_id', $this->user->getOwnerId())->first();

    expect($credential)->not->toBeNull()
        ->and($credential->n8n_base_url)->toBe('https://tenant.example.com')
        ->and($credential->n8n_telegram_proxy_webhook_url)->toBe('https://tenant.example.com/webhook/proxy')
        ->and($credential->n8n_api_key)->toBe('my_secret_api_key');

    $raw = DB::table('channel_credentials')->where('owner_id', $this->user->getOwnerId())->value('n8n_api_key');

    expect($raw)->not->toBe('my_secret_api_key')
        ->and(Crypt::decryptString($raw))->toBe('my_secret_api_key');
});

test('update keeps the previously stored api key when field is empty', function () {
    ChannelCredential::create([
        'owner_id' => $this->user->getOwnerId(),
        'n8n_telegram_proxy_webhook_url' => 'https://old.example.com/webhook/proxy',
        'n8n_api_key' => 'existing_secret_key',
    ]);

    $response = $this->actingAs($this->user)->putJson(route('channel-credentials.n8n-config.update'), [
        'n8n_telegram_proxy_webhook_url' => 'https://new.example.com/webhook/proxy',
        'n8n_api_key' => '',
    ]);

    $response->assertOk()->assertJson(['success' => true]);

    $credential = ChannelCredential::where('owner_id', $this->user->getOwnerId())->first();

    expect($credential->n8n_telegram_proxy_webhook_url)->toBe('https://new.example.com/webhook/proxy')
        ->and($credential->n8n_api_key)->toBe('existing_secret_key');
});

test('update keeps the previously stored api key when field is masked', function () {
    ChannelCredential::create([
        'owner_id' => $this->user->getOwnerId(),
        'n8n_api_key' => 'existing_secret_key',
    ]);

    $response = $this->actingAs($this->user)->putJson(route('channel-credentials.n8n-config.update'), [
        'n8n_telegram_proxy_webhook_url' => null,
        'n8n_api_key' => '••••••••••••••••',
    ]);

    $response->assertOk()->assertJson(['success' => true]);

    $credential = ChannelCredential::where('owner_id', $this->user->getOwnerId())->first();

    expect($credential->n8n_api_key)->toBe('existing_secret_key');
});

test('update allows replacing the api key with a new value', function () {
    ChannelCredential::create([
        'owner_id' => $this->user->getOwnerId(),
        'n8n_api_key' => 'old_secret_key',
    ]);

    $response = $this->actingAs($this->user)->putJson(route('channel-credentials.n8n-config.update'), [
        'n8n_telegram_proxy_webhook_url' => null,
        'n8n_api_key' => 'new_secret_key',
    ]);

    $response->assertOk()->assertJson(['success' => true]);

    $credential = ChannelCredential::where('owner_id', $this->user->getOwnerId())->first();

    expect($credential->n8n_api_key)->toBe('new_secret_key');
});

test('update rejects an invalid webhook url', function () {
    $response = $this->actingAs($this->user)->putJson(route('channel-credentials.n8n-config.update'), [
        'n8n_telegram_proxy_webhook_url' => 'not-a-valid-url',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('n8n_telegram_proxy_webhook_url');
});

test('test connection returns error when no url is configured anywhere', function () {
    $response = $this->actingAs($this->user)->postJson(route('channel-credentials.n8n-config.test'));

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
        ]);
});

test('test connection succeeds against the tenant webhook url', function () {
    Http::fake([
        'https://tenant.example.com/*' => Http::response(['status' => 'ok'], 200),
    ]);

    $response = $this->actingAs($this->user)->postJson(route('channel-credentials.n8n-config.test'), [
        'n8n_telegram_proxy_webhook_url' => 'https://tenant.example.com/webhook/proxy',
    ]);

    $response->assertOk()->assertJson(['success' => true]);
});

test('test connection falls back to the global n8n webhook url', function () {
    SystemIntegration::create([
        'provider' => 'n8n',
        'telegram_proxy_url' => 'https://global-n8n.example.com/webhook/proxy',
        'is_active' => true,
    ]);

    Http::fake([
        'https://global-n8n.example.com/*' => Http::response(['status' => 'ok'], 200),
    ]);

    $response = $this->actingAs($this->user)->postJson(route('channel-credentials.n8n-config.test'));

    $response->assertOk()->assertJson(['success' => true]);
});

test('test connection sends flow payload with is_test=false when a chat is linked', function () {
    ChannelCredential::create([
        'owner_id' => $this->user->getOwnerId(),
        'telegram_chat_id' => '555444333',
    ]);

    Http::fake([
        'https://tenant.example.com/*' => Http::response(['status' => 'ok'], 200),
    ]);

    $response = $this->actingAs($this->user)->postJson(route('channel-credentials.n8n-config.test'), [
        'n8n_telegram_proxy_webhook_url' => 'https://tenant.example.com/webhook/proxy',
    ]);

    $response->assertOk()->assertJson(['success' => true]);

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $request->url() === 'https://tenant.example.com/webhook/proxy'
            && $body['event'] === 'message'
            && $body['is_test'] === false
            && $body['chat_id'] === '555444333'
            && $body['is_linked'] === true
            && $body['text'] === 'Inicio de prueba de flujo';
    });
});

test('test connection downgrades to short-circuit when no chat is linked', function () {
    Http::fake([
        'https://tenant.example.com/*' => Http::response(['status' => 'ok'], 200),
    ]);

    $response = $this->actingAs($this->user)->postJson(route('channel-credentials.n8n-config.test'), [
        'n8n_telegram_proxy_webhook_url' => 'https://tenant.example.com/webhook/proxy',
    ]);

    $response->assertOk()->assertJson(['success' => true]);

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $request->url() === 'https://tenant.example.com/webhook/proxy'
            && $body['event'] === 'test_connection'
            && $body['is_test'] === true
            && $body['chat_id'] === null
            && $body['is_linked'] === false;
    });
});

test('update saves n8n base url', function () {
    $response = $this->actingAs($this->user)->putJson(route('channel-credentials.n8n-config.update'), [
        'n8n_base_url' => 'https://mi-n8n.ejemplo.com',
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'data' => [
                'n8n_base_url' => 'https://mi-n8n.ejemplo.com',
            ],
        ]);

    $credential = ChannelCredential::where('owner_id', $this->user->getOwnerId())->first();

    expect($credential)->not->toBeNull()
        ->and($credential->n8n_base_url)->toBe('https://mi-n8n.ejemplo.com');
});

test('update rejects an invalid base url', function () {
    $response = $this->actingAs($this->user)->putJson(route('channel-credentials.n8n-config.update'), [
        'n8n_base_url' => 'not-a-valid-url',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('n8n_base_url');
});

test('test connection falls back to tenant base url via healthz', function () {
    ChannelCredential::create([
        'owner_id' => $this->user->getOwnerId(),
        'n8n_base_url' => 'https://mi-n8n.ejemplo.com',
    ]);

    config()->set('services.n8n.telegram_proxy_url', null);
    config()->set('services.n8n.webhook_url', null);

    Http::fake([
        'https://mi-n8n.ejemplo.com/healthz' => Http::response('ok', 200),
    ]);

    $response = $this->actingAs($this->user)->postJson(route('channel-credentials.n8n-config.test'));

    $response->assertOk()->assertJson(['success' => true]);
});
