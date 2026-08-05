<?php

use App\Http\Controllers\Backend\TelegramCallbackController;
use App\Http\Controllers\Webhooks\TelegramWebhookController;
use App\Models\ChannelCredential;
use App\Models\TelegramLinkingToken;
use App\Models\User;
use App\Models\WebSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('send-test-message returns error when no token saved', function () {
    $response = $this->actingAs($this->user)->postJson(route('channel-credentials.send-test-message'));

    $response->assertJson([
        'success' => false,
        'message' => 'No hay un Token de Bot de Telegram configurado.',
    ]);
});

test('send-test-message returns error when no telegram_chat_id stored', function () {
    ChannelCredential::create([
        'owner_id' => $this->user->getOwnerId(),
        'telegram_bot_token' => 'test:token',
        'telegram_bot_username' => 'test_bot',
    ]);

    Http::fake([
        'api.telegram.org*' => Http::response(['ok' => true], 200),
    ]);

    $response = $this->actingAs($this->user)->postJson(route('channel-credentials.send-test-message'));

    $response->assertJson([
        'success' => false,
        'message' => 'No se ha vinculado una cuenta de Telegram. Primero abre el chat del bot y presiona "Iniciar" (/start) o usa el widget de inicio de sesión de Telegram.',
    ]);
});

test('send-test-message sends message successfully when chat_id is stored', function () {
    ChannelCredential::create([
        'owner_id' => $this->user->getOwnerId(),
        'telegram_bot_token' => 'test:token',
        'telegram_bot_username' => 'test_bot',
        'telegram_chat_id' => '123456789',
    ]);

    Http::fake([
        'api.telegram.org/bottest:token/sendMessage' => Http::response([
            'ok' => true,
            'result' => ['message_id' => 42],
        ], 200),
    ]);

    $response = $this->actingAs($this->user)->postJson(route('channel-credentials.send-test-message'));

    $response->assertJson([
        'success' => true,
        'message_id' => 42,
    ]);
});

test('send-test-message uses stored token when no token in request body', function () {
    ChannelCredential::create([
        'owner_id' => $this->user->getOwnerId(),
        'telegram_bot_token' => 'saved_token',
        'telegram_bot_username' => 'test_bot',
        'telegram_chat_id' => '987654321',
    ]);

    Http::fake([
        'api.telegram.org/botsaved_token/sendMessage' => Http::response([
            'ok' => true,
            'result' => ['message_id' => 100],
        ], 200),
    ]);

    $response = $this->actingAs($this->user)->postJson(route('channel-credentials.send-test-message'));

    $response->assertJson(['success' => true, 'message_id' => 100]);
});

test('login-callback returns 400 when no hash provided', function () {
    $response = $this->actingAs($this->user)->postJson(route('telegram.login-callback'), [
        'id' => 123456,
        'first_name' => 'Test',
        'auth_date' => now()->timestamp,
    ]);

    $response->assertJson([
        'success' => false,
        'message' => 'Datos de autenticación de Telegram incompletos.',
    ]);
});

test('login-callback returns 403 when hash verification fails', function () {
    config(['services.telegram.bot_token' => 'test:bot_token']);

    $response = $this->actingAs($this->user)->postJson(route('telegram.login-callback'), [
        'id' => 123456,
        'first_name' => 'Test',
        'auth_date' => now()->timestamp,
        'hash' => 'invalid_hash',
    ]);

    $response->assertJson([
        'success' => false,
        'message' => 'Autenticación de Telegram inválida.',
    ]);
});

test('login-callback controller stores telegram_chat_id when hash is valid', function () {
    config(['services.telegram.bot_token' => 'test:bot_token']);

    ChannelCredential::create([
        'owner_id' => $this->user->getOwnerId(),
        'telegram_bot_token' => 'test:bot_token',
        'telegram_bot_username' => 'test_bot',
    ]);

    $telegramUserId = '999888';

    $controller = new TelegramCallbackController;
    $request = Request::create('/telegram/login-callback', 'POST', [
        'id' => $telegramUserId,
        'first_name' => 'Test',
        'auth_date' => (string) time(),
    ]);

    $authData = $request->input();
    unset($authData['hash']);

    $dataCheckString = collect($authData)
        ->sortKeys()
        ->map(fn ($value, $key) => "{$key}=".(is_array($value) ? implode(',', array_map('strval', $value)) : strval($value)))
        ->join("\n");

    $hash = hash_hmac('sha256', $dataCheckString, 'test:bot_token');

    $request->merge(['hash' => $hash]);

    Auth::setUser($this->user);
    $response = $controller->handle($request);

    expect($response->getStatusCode())->toBe(302);
    $this->assertDatabaseHas('channel_credentials', [
        'owner_id' => $this->user->getOwnerId(),
        'telegram_chat_id' => $telegramUserId,
    ]);

    expect(ChannelCredential::where('owner_id', $this->user->getOwnerId())->first()->telegram_linked_at)->not->toBeNull();
});

test('generate-link returns error when no bot username configured', function () {
    $response = $this->actingAs($this->user)->postJson(route('telegram.generate-link'));

    $response->assertJson([
        'success' => false,
        'message' => 'No hay un bot de Telegram configurado. Guarda las credenciales primero.',
    ]);
    $response->assertStatus(422);
});

test('generate-link generates a linking link successfully', function () {
    ChannelCredential::create([
        'owner_id' => $this->user->getOwnerId(),
        'telegram_bot_token' => 'test:token',
        'telegram_bot_username' => 'test_bot',
    ]);

    $response = $this->actingAs($this->user)->postJson(route('telegram.generate-link'));

    $response->assertJson([
        'success' => true,
    ]);
    $data = $response->json();
    expect($data['telegram_url'])->toStartWith('https://t.me/test_bot?start=');
    expect($data['telegram_url'])->toMatch('/\?start=[A-Za-z0-9_]{1,64}$/');

    $this->assertDatabaseHas('telegram_linking_tokens', [
        'owner_id' => $this->user->getOwnerId(),
        'user_id' => $this->user->id,
    ]);
});

test('webhook handle processes linking token and saves chat_id', function () {
    ChannelCredential::create([
        'owner_id' => $this->user->getOwnerId(),
        'telegram_bot_token' => 'test:token',
        'telegram_bot_username' => 'test_bot',
    ]);

    $token = TelegramLinkingToken::create([
        'owner_id' => $this->user->getOwnerId(),
        'user_id' => $this->user->id,
        'token' => 'test_token_123',
        'expires_at' => now()->addMinutes(15),
    ]);

    $controller = app(TelegramWebhookController::class);
    $request = Request::create('/webhooks/telegram', 'POST', [
        'message' => [
            'chat' => ['id' => 999888],
            'text' => '/start test_token_123',
        ],
    ]);

    $response = $controller->handle($request);

    expect(json_decode($response->getContent(), true)['status'])->toBe('linked');

    $this->assertDatabaseHas('channel_credentials', [
        'owner_id' => $this->user->getOwnerId(),
        'telegram_chat_id' => '999888',
    ]);

    expect(ChannelCredential::where('owner_id', $this->user->getOwnerId())->first()->telegram_linked_at)->not->toBeNull();

    $token->refresh();
    expect($token->telegram_chat_id)->toBe('999888');
    expect($token->used_at)->not->toBeNull();
});

test('webhook handle ignores invalid or expired tokens', function () {
    ChannelCredential::create([
        'owner_id' => $this->user->getOwnerId(),
        'telegram_bot_token' => 'test:token',
        'telegram_bot_username' => 'test_bot',
    ]);

    TelegramLinkingToken::create([
        'owner_id' => $this->user->getOwnerId(),
        'user_id' => $this->user->id,
        'token' => 'expired_token',
        'expires_at' => now()->subMinutes(1),
    ]);

    $controller = app(TelegramWebhookController::class);
    $request = Request::create('/webhooks/telegram', 'POST', [
        'message' => [
            'chat' => ['id' => 999888],
            'text' => '/start expired_token',
        ],
    ]);

    $response = $controller->handle($request);

    expect(json_decode($response->getContent(), true)['status'])->toBe('invalid_token');

    $this->assertDatabaseMissing('channel_credentials', [
        'owner_id' => $this->user->getOwnerId(),
        'telegram_chat_id' => '999888',
    ]);
});

test('webhook handle forwards messages to n8n after linking', function () {
    config([
        'services.n8n.telegram_proxy_url' => 'https://n8n.example.com/webhook/telegram',
        'services.n8n.webhook_url' => 'https://n8n.example.com/webhook/telegram',
        'services.n8n.token' => 'n8n_api_token',
    ]);

    ChannelCredential::create([
        'owner_id' => $this->user->getOwnerId(),
        'telegram_bot_token' => 'test:token',
        'telegram_bot_username' => 'test_bot',
        'telegram_chat_id' => '999888',
    ]);

    Http::fake([
        'n8n.example.com*' => Http::response(['ok' => true], 200),
    ]);

    $controller = app(TelegramWebhookController::class);
    $request = Request::create('/webhooks/telegram', 'POST', [
        'message' => [
            'chat' => ['id' => 999888],
            'text' => 'Hello from Telegram',
        ],
    ]);

    $response = $controller->handle($request);

    expect(json_decode($response->getContent(), true)['status'])->toBe('ok');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://n8n.example.com/webhook/telegram'
            && $request['tenant_id'] === $this->user->getOwnerId()
            && $request['chat_id'] === '999888'
            && $request['user_message'] === 'Hello from Telegram'
            && $request['event'] === 'message';
    });
});

test('webhook handle ignores non-start messages when chat_id not linked', function () {
    ChannelCredential::create([
        'owner_id' => $this->user->getOwnerId(),
        'telegram_bot_token' => 'test:token',
        'telegram_bot_username' => 'test_bot',
    ]);

    $controller = app(TelegramWebhookController::class);
    $request = Request::create('/webhooks/telegram', 'POST', [
        'message' => [
            'chat' => ['id' => 999888],
            'text' => 'Hello from Telegram',
        ],
    ]);

    $response = $controller->handle($request);

    expect(json_decode($response->getContent(), true)['status'])->toBe('ok');
});

test('send-test-message does not accept telegram_chat_id from request body', function () {
    ChannelCredential::create([
        'owner_id' => $this->user->getOwnerId(),
        'telegram_bot_token' => 'test:token',
        'telegram_bot_username' => 'test_bot',
        'telegram_chat_id' => '987654321',
    ]);

    Http::fake([
        'api.telegram.org/bottest:token/sendMessage' => Http::response([
            'ok' => true,
            'result' => ['message_id' => 42],
        ], 200),
    ]);

    $response = $this->actingAs($this->user)->postJson(route('channel-credentials.send-test-message'), [
        'telegram_chat_id' => '111222333',
    ]);

    $response->assertJson(['success' => true, 'message_id' => 42]);

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $body['chat_id'] === '987654321';
    });
});

test('generate-link with type global returns link for platform bot', function () {
    WebSetting::create([
        'app_name' => 'Aldia',
        'global_telegram_bot_username' => 'aldia_global_bot',
    ]);

    $response = $this->actingAs($this->user)->postJson(route('telegram.generate-link'), [
        'type' => 'global',
    ]);

    $response->assertJson([
        'success' => true,
        'bot_type' => 'global',
        'bot_username' => 'aldia_global_bot',
    ]);
    $data = $response->json();
    expect($data['telegram_url'])->toStartWith('https://t.me/aldia_global_bot?start=');
    expect($data['telegram_url'])->toMatch('/\?start=[A-Za-z0-9_]{1,64}$/');

    $this->assertDatabaseHas('telegram_linking_tokens', [
        'owner_id' => $this->user->getOwnerId(),
        'user_id' => $this->user->id,
        'bot_type' => 'global',
    ]);
});

test('generate-link with type global returns error when no global bot configured', function () {
    $response = $this->actingAs($this->user)->postJson(route('telegram.generate-link'), [
        'type' => 'global',
    ]);

    $response->assertJson([
        'success' => false,
        'message' => 'No hay un bot oficial configurado en la plataforma.',
    ]);
    $response->assertStatus(422);
});

test('webhook processLinking saves bot_type from linking token', function () {
    ChannelCredential::create([
        'owner_id' => $this->user->getOwnerId(),
        'telegram_bot_token' => 'test:token',
        'telegram_bot_username' => 'test_bot',
    ]);

    $token = TelegramLinkingToken::create([
        'owner_id' => $this->user->getOwnerId(),
        'user_id' => $this->user->id,
        'token' => 'global_token_123',
        'bot_type' => 'global',
        'expires_at' => now()->addMinutes(15),
    ]);

    $controller = app(TelegramWebhookController::class);
    $request = Request::create('/webhooks/telegram', 'POST', [
        'message' => [
            'chat' => ['id' => 999888],
            'text' => '/start global_token_123',
        ],
    ]);

    $response = $controller->handle($request);

    expect(json_decode($response->getContent(), true)['status'])->toBe('linked');

    $this->assertDatabaseHas('channel_credentials', [
        'owner_id' => $this->user->getOwnerId(),
        'telegram_chat_id' => '999888',
        'bot_type' => 'global',
    ]);

    $token->refresh();
    expect($token->used_at)->not->toBeNull();
});

test('webhook forwards to n8n includes bot_type and app_name', function () {
    config([
        'services.n8n.telegram_proxy_url' => 'https://n8n.example.com/webhook/telegram',
        'services.n8n.webhook_url' => 'https://n8n.example.com/webhook/telegram',
        'services.n8n.token' => 'n8n_api_token',
    ]);

    WebSetting::create([
        'app_name' => 'Aldia',
        'global_telegram_bot_username' => 'aldia_global_bot',
    ]);

    ChannelCredential::create([
        'owner_id' => $this->user->getOwnerId(),
        'telegram_bot_token' => 'test:token',
        'telegram_bot_username' => 'test_bot',
        'telegram_chat_id' => '999888',
        'bot_type' => 'global',
    ]);

    Http::fake([
        'n8n.example.com*' => Http::response(['ok' => true], 200),
    ]);

    $controller = app(TelegramWebhookController::class);
    $request = Request::create('/webhooks/telegram', 'POST', [
        'message' => [
            'chat' => ['id' => 999888],
            'text' => 'Hello from Telegram',
        ],
    ]);

    $response = $controller->handle($request);

    expect(json_decode($response->getContent(), true)['status'])->toBe('ok');

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $request->url() === 'https://n8n.example.com/webhook/telegram'
            && $body['tenant_id'] === $this->user->getOwnerId()
            && $body['chat_id'] === '999888'
            && $body['bot_type'] === 'oficial'
            && $body['event'] === 'message'
            && $body['user_message'] === 'Hello from Telegram'
            && $body['callback_url'] === route('api.canales.telegram.webhook')
            && $body['webhook_url'] === route('api.canales.telegram.webhook');
    });
});

test('webhook accepts POST without /api prefix (n8n compatibility alias)', function () {
    $response = $this->postJson('/canales/telegram/webhook', [
        'message' => [
            'chat' => ['id' => 123],
            'text' => 'hola',
        ],
    ]);

    $response->assertOk();
    $response->assertJson(['status' => 'ok']);
});

test('api canales telegram webhook links account via start token, sends Telegram confirmation, and forwards to n8n as is_linked=true', function () {
    config([
        'services.n8n.telegram_proxy_url' => 'https://n8n.redcliente.cl/webhook/telegram-proxy',
    ]);

    ChannelCredential::create([
        'owner_id' => $this->user->getOwnerId(),
        'telegram_bot_token' => 'my_bot_token_123',
        'telegram_bot_username' => 'asistentealdiabot',
    ]);

    $token = TelegramLinkingToken::create([
        'owner_id' => $this->user->getOwnerId(),
        'user_id' => $this->user->id,
        'token' => 'bind_token_xyz',
        'expires_at' => now()->addMinutes(15),
    ]);

    Http::fake([
        'api.telegram.org/botmy_bot_token_123/sendMessage' => Http::response(['ok' => true], 200),
        'n8n.redcliente.cl/*' => Http::response(['status' => 'ok'], 200),
    ]);

    $response = $this->postJson('/api/canales/telegram/webhook', [
        'message' => [
            'chat' => ['id' => 777888],
            'text' => '/start bind_token_xyz',
        ],
    ]);

    $response->assertOk();
    $response->assertJson(['status' => 'linked']);

    $this->assertDatabaseHas('channel_credentials', [
        'owner_id' => $this->user->getOwnerId(),
        'telegram_chat_id' => '777888',
    ]);

    expect(ChannelCredential::where('owner_id', $this->user->getOwnerId())->first()->telegram_linked_at)->not->toBeNull();

    $token->refresh();
    expect($token->telegram_chat_id)->toBe('777888');
    expect($token->used_at)->not->toBeNull();

    // Verify Telegram confirmation message
    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'api.telegram.org')
            && $request['chat_id'] === '777888'
            && str_contains($request['text'], '🎉 ¡Cuenta vinculada exitosamente! Ya puedes interactuar con tu asistente de Al Día.');
    });

    // Verify the linked update is NOT forwarded to n8n
    Http::assertNotSent(function ($request) {
        return str_contains($request->url(), 'n8n.redcliente.cl/webhook/telegram-proxy');
    });
});

test('api canales telegram webhook forwards unlinked message to n8n with is_linked=false', function () {
    config([
        'services.n8n.telegram_proxy_url' => 'https://n8n.redcliente.cl/webhook/telegram-proxy',
    ]);

    Http::fake([
        'n8n.redcliente.cl/*' => Http::response(['status' => 'ok'], 200),
    ]);

    $response = $this->postJson('/api/canales/telegram/webhook', [
        'message' => [
            'chat' => ['id' => 555444],
            'text' => 'Hello from Telegram',
        ],
    ]);

    $response->assertOk();

    Http::assertSent(function ($request) {
        $body = $request->data();

        return str_contains($request->url(), 'n8n.redcliente.cl/webhook/telegram-proxy')
            && $body['is_linked'] === false
            && $body['event'] === 'send_linking_code'
            && $body['tenant_id'] === null
            && $body['chat_id'] === '555444'
            && $body['linking_url'] === config('app.url').'/canales'
            && array_key_exists('bot_token', $body)
            && array_key_exists('bot_username', $body);
    });
});

test('api canales telegram webhook responds with instructions when user sends /start without token', function () {
    config([
        'services.telegram.bot_token' => 'test:bot_token',
        'services.n8n.telegram_proxy_url' => 'https://n8n.redcliente.cl/webhook/telegram-proxy',
    ]);

    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true], 200),
        'n8n.redcliente.cl/*' => Http::response(['status' => 'ok'], 200),
    ]);

    $response = $this->postJson('/api/canales/telegram/webhook', [
        'message' => [
            'chat' => ['id' => 444333],
            'text' => '/start',
        ],
    ]);

    $response->assertOk();
    $response->assertJson(['status' => 'missing_token']);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'api.telegram.org')
            && $request['chat_id'] === '444333'
            && str_contains($request['text'], 'Abrir Chat en Telegram')
            && str_contains($request['text'], '/start TU_TOKEN');
    });

    Http::assertNotSent(function ($request) {
        return str_contains($request->url(), 'n8n.redcliente.cl');
    });
});

test('api canales telegram webhook rejects invalid start token with error message', function () {
    config([
        'services.telegram.bot_token' => 'test:bot_token',
        'services.n8n.telegram_proxy_url' => 'https://n8n.redcliente.cl/webhook/telegram-proxy',
    ]);

    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true], 200),
        'n8n.redcliente.cl/*' => Http::response(['status' => 'ok'], 200),
    ]);

    $response = $this->postJson('/api/canales/telegram/webhook', [
        'message' => [
            'chat' => ['id' => 666777],
            'text' => '/start invalid_token_xyz',
        ],
    ]);

    $response->assertOk();
    $response->assertJson(['status' => 'invalid_token']);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'api.telegram.org')
            && $request['chat_id'] === '666777'
            && str_contains($request['text'], 'El token de vinculación es inválido o ha expirado');
    });

    Http::assertNotSent(function ($request) {
        return str_contains($request->url(), 'n8n.redcliente.cl');
    });

    $this->assertDatabaseMissing('channel_credentials', [
        'telegram_chat_id' => '666777',
    ]);
});

test('webhook handle links account via /start@botname token format', function () {
    ChannelCredential::create([
        'owner_id' => $this->user->getOwnerId(),
        'telegram_bot_token' => 'test:token',
        'telegram_bot_username' => 'test_bot',
    ]);

    $token = TelegramLinkingToken::create([
        'owner_id' => $this->user->getOwnerId(),
        'user_id' => $this->user->id,
        'token' => 'mentions_token_123',
        'expires_at' => now()->addMinutes(15),
    ]);

    $controller = app(TelegramWebhookController::class);
    $request = Request::create('/webhooks/telegram', 'POST', [
        'message' => [
            'chat' => ['id' => 888777],
            'text' => '/start@test_bot mentions_token_123',
        ],
    ]);

    $response = $controller->handle($request);

    expect(json_decode($response->getContent(), true)['status'])->toBe('linked');

    $this->assertDatabaseHas('channel_credentials', [
        'owner_id' => $this->user->getOwnerId(),
        'telegram_chat_id' => '888777',
    ]);

    $token->refresh();
    expect($token->telegram_chat_id)->toBe('888777');
    expect($token->used_at)->not->toBeNull();
});

test('webhook handle links account when update is wrapped by n8n proxy', function () {
    ChannelCredential::create([
        'owner_id' => $this->user->getOwnerId(),
        'telegram_bot_token' => 'test:token',
        'telegram_bot_username' => 'test_bot',
    ]);

    $token = TelegramLinkingToken::create([
        'owner_id' => $this->user->getOwnerId(),
        'user_id' => $this->user->id,
        'token' => 'wrapped_token_123',
        'expires_at' => now()->addMinutes(15),
    ]);

    $controller = app(TelegramWebhookController::class);
    $request = Request::create('/webhooks/telegram', 'POST', [
        'body' => [
            'message' => [
                'chat' => ['id' => 777666],
                'text' => '/start wrapped_token_123',
            ],
        ],
    ]);

    $response = $controller->handle($request);

    expect(json_decode($response->getContent(), true)['status'])->toBe('linked');

    $this->assertDatabaseHas('channel_credentials', [
        'owner_id' => $this->user->getOwnerId(),
        'telegram_chat_id' => '777666',
    ]);

    $token->refresh();
    expect($token->telegram_chat_id)->toBe('777666');
    expect($token->used_at)->not->toBeNull();
});

test('webhook handle trims punctuation from start token', function () {
    ChannelCredential::create([
        'owner_id' => $this->user->getOwnerId(),
        'telegram_bot_token' => 'test:token',
        'telegram_bot_username' => 'test_bot',
    ]);

    $token = TelegramLinkingToken::create([
        'owner_id' => $this->user->getOwnerId(),
        'user_id' => $this->user->id,
        'token' => 'clean_token_123',
        'expires_at' => now()->addMinutes(15),
    ]);

    $controller = app(TelegramWebhookController::class);
    $request = Request::create('/webhooks/telegram', 'POST', [
        'message' => [
            'chat' => ['id' => 666555],
            'text' => '/start clean_token_123!',
        ],
    ]);

    $response = $controller->handle($request);

    expect(json_decode($response->getContent(), true)['status'])->toBe('linked');

    $this->assertDatabaseHas('channel_credentials', [
        'owner_id' => $this->user->getOwnerId(),
        'telegram_chat_id' => '666555',
    ]);

    $token->refresh();
    expect($token->telegram_chat_id)->toBe('666555');
});

test('proxy gate intercepts linking update and does not forward to n8n', function () {
    config([
        'services.n8n.telegram_proxy_url' => 'https://n8n.redcliente.cl/webhook/telegram-proxy',
    ]);

    ChannelCredential::create([
        'owner_id' => $this->user->getOwnerId(),
        'telegram_bot_token' => 'test:token',
        'telegram_bot_username' => 'test_bot',
    ]);

    $token = TelegramLinkingToken::create([
        'owner_id' => $this->user->getOwnerId(),
        'user_id' => $this->user->id,
        'token' => 'proxy_token_1',
        'expires_at' => now()->addMinutes(15),
    ]);

    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true], 200),
        'n8n.redcliente.cl/*' => Http::response(['status' => 'ok'], 200),
    ]);

    $response = $this->postJson(route('telegram.webhook'), [
        'message' => [
            'chat' => ['id' => 123987],
            'text' => '/start proxy_token_1',
        ],
    ]);

    $response->assertOk();
    $response->assertJson(['status' => 'linked']);

    $this->assertDatabaseHas('channel_credentials', [
        'owner_id' => $this->user->getOwnerId(),
        'telegram_chat_id' => '123987',
    ]);

    $token->refresh();
    expect($token->telegram_chat_id)->toBe('123987');
    expect($token->used_at)->not->toBeNull();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'api.telegram.org')
            && $request['chat_id'] === '123987'
            && str_contains($request['text'], '🎉 ¡Cuenta vinculada exitosamente!');
    });

    Http::assertNotSent(function ($request) {
        return str_contains($request->url(), 'n8n.redcliente.cl');
    });
});

test('proxy gate forwards normal message from linked chat with is_linked=true', function () {
    config([
        'services.n8n.telegram_proxy_url' => 'https://n8n.redcliente.cl/webhook/telegram-proxy',
    ]);

    ChannelCredential::create([
        'owner_id' => $this->user->getOwnerId(),
        'telegram_bot_token' => 'test:token',
        'telegram_bot_username' => 'test_bot',
        'telegram_chat_id' => '321654',
    ]);

    Http::fake([
        'n8n.redcliente.cl/*' => Http::response(['status' => 'ok'], 200),
    ]);

    $response = $this->postJson(route('telegram.webhook'), [
        'message' => [
            'chat' => ['id' => 321654],
            'text' => 'Hello from linked chat',
        ],
    ]);

    $response->assertOk();
    $response->assertJson(['status' => 'ok']);

    Http::assertSent(function ($request) {
        $body = $request->data();

        return str_contains($request->url(), 'n8n.redcliente.cl/webhook/telegram-proxy')
            && $body['event'] === 'message'
            && $body['is_linked'] === true
            && $body['tenant_id'] === $this->user->getOwnerId()
            && $body['chat_id'] === '321654'
            && $body['user_message'] === 'Hello from linked chat';
    });
});

test('webhook handle extracts chat_id from callback_query nested chat', function () {
    config([
        'services.n8n.telegram_proxy_url' => 'https://n8n.example.com/webhook/telegram',
    ]);

    ChannelCredential::create([
        'owner_id' => $this->user->getOwnerId(),
        'telegram_bot_token' => 'test:token',
        'telegram_bot_username' => 'test_bot',
        'telegram_chat_id' => '555666',
    ]);

    Http::fake([
        'n8n.example.com*' => Http::response(['ok' => true], 200),
    ]);

    $response = $this->postJson(route('telegram.webhook'), [
        'callback_query' => [
            'id' => '123',
            'from' => ['id' => 555666, 'is_bot' => false, 'first_name' => 'Test'],
            'message' => [
                'message_id' => 1,
                'chat' => ['id' => 555666],
                'text' => 'press me',
            ],
            'data' => 'some_callback_data',
        ],
    ]);

    $response->assertOk();
    $response->assertJson(['status' => 'ok']);

    Http::assertSent(function ($request) {
        $body = $request->data();

        return str_contains($request->url(), 'n8n.example.com')
            && $body['chat_id'] === '555666'
            && $body['user_message'] === 'some_callback_data';
    });
});

test('webhook handle links account when body arrives as JSON string (n8n wrapped)', function () {
    ChannelCredential::create([
        'owner_id' => $this->user->getOwnerId(),
        'telegram_bot_token' => 'test:token',
        'telegram_bot_username' => 'test_bot',
    ]);

    $token = TelegramLinkingToken::create([
        'owner_id' => $this->user->getOwnerId(),
        'user_id' => $this->user->id,
        'token' => 'string_body_token',
        'expires_at' => now()->addMinutes(15),
    ]);

    $controller = app(TelegramWebhookController::class);
    $request = Request::create('/webhooks/telegram', 'POST', [
        'body' => '{"message":{"chat":{"id":777666},"text":"/start string_body_token"}}',
    ]);

    $response = $controller->handle($request);

    expect(json_decode($response->getContent(), true)['status'])->toBe('linked');

    $token->refresh();
    expect($token->telegram_chat_id)->toBe('777666');
    expect($token->used_at)->not->toBeNull();
});

test('telegram login callback with token in body still validates hash and marks token as used', function () {
    config(['services.telegram.bot_token' => 'test:bot_token']);

    ChannelCredential::create([
        'owner_id' => $this->user->getOwnerId(),
        'telegram_bot_token' => 'test:bot_token',
        'telegram_bot_username' => 'test_bot',
    ]);

    $linkingToken = TelegramLinkingToken::create([
        'owner_id' => $this->user->getOwnerId(),
        'user_id' => $this->user->id,
        'token' => 'login_token_123',
        'expires_at' => now()->addMinutes(15),
    ]);

    $telegramUserId = '999888';

    $authData = [
        'id' => $telegramUserId,
        'first_name' => 'Test',
        'auth_date' => (string) time(),
    ];

    // Simulate Telegram's signed data (token is NOT part of the signature).
    $dataCheckString = collect($authData)
        ->sortKeys()
        ->map(fn ($value, $key) => "{$key}=".strval($value))
        ->join("\n");

    $hash = hash_hmac('sha256', $dataCheckString, 'test:bot_token');

    $authData['hash'] = $hash;
    $authData['token'] = $linkingToken->token;

    $request = Request::create('/telegram/login-callback', 'POST', $authData);

    Auth::setUser($this->user);
    $controller = new TelegramCallbackController;
    $response = $controller->handle($request);

    expect($response->getStatusCode())->toBe(302);

    $linkingToken->refresh();
    expect($linkingToken->telegram_chat_id)->toBe($telegramUserId);
    expect($linkingToken->used_at)->not->toBeNull();
});

test('webhook treats a re-delivered /start TOKEN for the same chat as linked (idempotent)', function () {
    ChannelCredential::create([
        'owner_id' => $this->user->getOwnerId(),
        'telegram_bot_token' => 'test:token',
        'telegram_bot_username' => 'test_bot',
    ]);

    $token = TelegramLinkingToken::create([
        'owner_id' => $this->user->getOwnerId(),
        'user_id' => $this->user->id,
        'token' => 'dup_token_456',
        'expires_at' => now()->addMinutes(15),
        'used_at' => now(),
        'telegram_chat_id' => '999888',
    ]);

    $controller = app(TelegramWebhookController::class);
    $request = Request::create('/webhooks/telegram', 'POST', [
        'message' => [
            'chat' => ['id' => 999888],
            'text' => '/start dup_token_456',
        ],
    ]);

    $response = $controller->handle($request);

    expect(json_decode($response->getContent(), true)['status'])->toBe('linked');
});

test('telegram login-callback without a token does not mark any linking token as used', function () {
    config(['services.telegram.bot_token' => 'test:bot_token']);

    ChannelCredential::create([
        'owner_id' => $this->user->getOwnerId(),
        'telegram_bot_token' => 'test:bot_token',
        'telegram_bot_username' => 'test_bot',
    ]);

    $unusedToken = TelegramLinkingToken::create([
        'owner_id' => $this->user->getOwnerId(),
        'user_id' => $this->user->id,
        'token' => 'untouched_token',
        'expires_at' => now()->addMinutes(15),
    ]);

    $telegramUserId = '999888';

    $authData = [
        'id' => $telegramUserId,
        'first_name' => 'Test',
        'auth_date' => (string) time(),
    ];

    $dataCheckString = collect($authData)
        ->sortKeys()
        ->map(fn ($value, $key) => "{$key}=".strval($value))
        ->join("\n");

    $hash = hash_hmac('sha256', $dataCheckString, 'test:bot_token');
    $authData['hash'] = $hash;

    $request = Request::create('/telegram/login-callback', 'POST', $authData);

    Auth::setUser($this->user);
    $controller = new TelegramCallbackController;
    $response = $controller->handle($request);

    expect($response->getStatusCode())->toBe(302);

    $unusedToken->refresh();
    expect($unusedToken->used_at)->toBeNull();
    expect($unusedToken->telegram_chat_id)->toBeNull();

    $this->assertDatabaseHas('channel_credentials', [
        'owner_id' => $this->user->getOwnerId(),
        'telegram_chat_id' => $telegramUserId,
    ]);
});

test('generate-link returns the linking token alongside the url', function () {
    ChannelCredential::create([
        'owner_id' => $this->user->getOwnerId(),
        'telegram_bot_token' => 'test:token',
        'telegram_bot_username' => 'test_bot',
    ]);

    $response = $this->actingAs($this->user)->postJson(route('telegram.generate-link'));

    $response->assertJson(['success' => true]);

    $data = $response->json();
    expect($data['telegram_url'])->toStartWith('https://t.me/test_bot?start=');
    expect(isset($data['token']))->toBeTrue();
    expect($data['token'])->not->toBeEmpty();
});

test('web linking page redirects to canales with success flash when token is valid', function () {
    $token = TelegramLinkingToken::create([
        'owner_id' => $this->user->getOwnerId(),
        'user_id' => $this->user->id,
        'token' => 'web_valid_token',
        'expires_at' => now()->addMinutes(15),
    ]);

    $response = $this->get(route('telegram.vincular', $token->token));

    $response->assertRedirect(route('channel-credentials.index'));
    $response->assertSessionHas('success', 'Enlace generado. Por favor confirma en Telegram.');
});

test('web linking page redirects with error flash when token is invalid or expired', function () {
    TelegramLinkingToken::create([
        'owner_id' => $this->user->getOwnerId(),
        'user_id' => $this->user->id,
        'token' => 'web_expired_token',
        'expires_at' => now()->subMinutes(1),
    ]);

    $response = $this->get(route('telegram.vincular', 'web_expired_token'));

    $response->assertRedirect(route('channel-credentials.index'));
    $response->assertSessionHas('error', 'El enlace de vinculación es inválido o ha expirado.');
});

test('web linking page redirects with error flash when token does not exist', function () {
    $response = $this->get(route('telegram.vincular', 'nonexistent_token'));

    $response->assertRedirect(route('channel-credentials.index'));
    $response->assertSessionHas('error', 'El enlace de vinculación es inválido o ha expirado.');
    $this->assertDatabaseMissing('telegram_linking_tokens', ['token' => 'nonexistent_token']);
});
