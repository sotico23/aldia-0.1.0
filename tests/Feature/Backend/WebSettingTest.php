<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'admin.web-settings.viewAny', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'admin.web-settings.edit', 'guard_name' => 'web']);

    $this->user = User::factory()->create();
    $this->user->givePermissionTo('admin.web-settings.viewAny');
});

test('test-telegram-connection requires bot_token and bot_username', function () {
    $response = $this->actingAs($this->user)->postJson(route('configuracion-web.test-telegram'), []);

    $response->assertStatus(422);
});

test('test-telegram-connection returns 422 when token is invalid', function () {
    Http::fake([
        'api.telegram.org*' => Http::response(['ok' => false], 401),
    ]);

    $response = $this->actingAs($this->user)->postJson(route('configuracion-web.test-telegram'), [
        'bot_token' => 'invalid_token',
        'bot_username' => 'test_bot',
    ]);

    $response->assertStatus(422);
    $response->assertJson([
        'success' => false,
        'message' => 'El Token de Telegram ingresado no es válido o expiró.',
    ]);
});

test('test-telegram-connection returns 422 when network exception occurs', function () {
    Http::fake(function () {
        throw new Exception('Network error');
    });

    $response = $this->actingAs($this->user)->postJson(route('configuracion-web.test-telegram'), [
        'bot_token' => 'some_token',
        'bot_username' => 'test_bot',
    ]);

    $response->assertStatus(422);
    $response->assertJson([
        'success' => false,
        'message' => 'El Token de Telegram ingresado no es válido o expiró.',
    ]);
});

test('test-telegram-connection returns 422 when is_bot is false', function () {
    Http::fake([
        'api.telegram.org*' => Http::response([
            'ok' => true,
            'result' => [
                'is_bot' => false,
                'username' => 'test_bot',
            ],
        ], 200),
    ]);

    $response = $this->actingAs($this->user)->postJson(route('configuracion-web.test-telegram'), [
        'bot_token' => 'valid_token',
        'bot_username' => 'test_bot',
    ]);

    $response->assertStatus(422);
    $response->assertJson([
        'success' => false,
        'message' => 'El Token de Telegram ingresado no es válido o expiró.',
    ]);
});

test('test-telegram-connection returns 422 when bot username does not match', function () {
    Http::fake([
        'api.telegram.org*' => Http::response([
            'ok' => true,
            'result' => [
                'is_bot' => true,
                'username' => 'different_bot',
            ],
        ], 200),
    ]);

    $response = $this->actingAs($this->user)->postJson(route('configuracion-web.test-telegram'), [
        'bot_token' => 'valid_token',
        'bot_username' => 'test_bot',
    ]);

    $response->assertStatus(422);
    $response->assertJson([
        'success' => false,
        'message' => 'El username del bot no coincide con el token proporcionado.',
    ]);
});

test('test-telegram-connection returns 200 when token is valid and bot matches', function () {
    Http::fake([
        'api.telegram.org*' => Http::response([
            'ok' => true,
            'result' => [
                'is_bot' => true,
                'username' => 'test_bot',
            ],
        ], 200),
    ]);

    $response = $this->actingAs($this->user)->postJson(route('configuracion-web.test-telegram'), [
        'bot_token' => 'valid_token',
        'bot_username' => 'test_bot',
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
        'message' => 'Conexión con Telegram exitosa. El bot está activo y el webhook está configurado.',
        'webhook_configured' => true,
        'webhook_url' => route('webhooks.telegram'),
        'bot_username' => 'test_bot',
    ]);
});

test('test-telegram-connection handles bot username with @ prefix', function () {
    Http::fake([
        'api.telegram.org*' => Http::response([
            'ok' => true,
            'result' => [
                'is_bot' => true,
                'username' => 'test_bot',
            ],
        ], 200),
    ]);

    $response = $this->actingAs($this->user)->postJson(route('configuracion-web.test-telegram'), [
        'bot_token' => 'valid_token',
        'bot_username' => '@test_bot',
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
    ]);
});

test('test-whatsapp-connection returns 422 when network exception occurs', function () {
    Http::fake(function () {
        throw new Exception('Network error');
    });

    $response = $this->actingAs($this->user)->postJson(route('configuracion-web.test-whatsapp'), [
        'webhook_url' => 'https://invalid-url.example.com/webhook',
    ]);

    $response->assertStatus(422);
    $response->assertJson([
        'success' => false,
        'message' => 'El webhook de WhatsApp ingresado no es válido o no está disponible.',
    ]);
});

test('test-whatsapp-connection returns 422 when webhook returns non-success', function () {
    Http::fake([
        'https://invalid-url.example.com/*' => Http::response([], 404),
    ]);

    $response = $this->actingAs($this->user)->postJson(route('configuracion-web.test-whatsapp'), [
        'webhook_url' => 'https://invalid-url.example.com/webhook',
    ]);

    $response->assertStatus(422);
    $response->assertJson([
        'success' => false,
        'message' => 'No se pudo conectar con la URL del webhook. Verifica la URL.',
    ]);
});

test('test-whatsapp-connection returns 200 when webhook is reachable', function () {
    Http::fake([
        'https://valid-url.example.com/*' => Http::response(['status' => 'ok'], 200),
    ]);

    $response = $this->actingAs($this->user)->postJson(route('configuracion-web.test-whatsapp'), [
        'webhook_url' => 'https://valid-url.example.com/webhook',
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
        'message' => 'Conexión con WhatsApp Webhook exitosa.',
    ]);
});

test('test-telegram-connection returns network message on ConnectionException', function () {
    Http::fake(function () {
        throw new ConnectionException('cURL error 6: Could not resolve host');
    });

    $response = $this->actingAs($this->user)->postJson(route('configuracion-web.test-telegram'), [
        'bot_token' => 'valid_token',
        'bot_username' => 'test_bot',
    ]);

    $response->assertStatus(422);
    $response->assertJson([
        'success' => false,
        'message' => 'No se pudo conectar con la API de Telegram. Verifica tu conexión e inténtalo nuevamente.',
    ]);
});

test('test-whatsapp-connection returns network message on ConnectionException', function () {
    Http::fake(function () {
        throw new ConnectionException('cURL error 28: Connection timed out');
    });

    $response = $this->actingAs($this->user)->postJson(route('configuracion-web.test-whatsapp'), [
        'webhook_url' => 'https://somewhatsappwebhook.example.com/webhook',
    ]);

    $response->assertStatus(422);
    $response->assertJson([
        'success' => false,
        'message' => 'No se pudo conectar con la URL del webhook. Verifica la URL e inténtalo nuevamente.',
    ]);
});
