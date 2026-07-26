<?php

use App\Http\Controllers\Backend\TelegramCallbackController;
use App\Models\ChannelCredential;
use App\Models\User;
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
});
