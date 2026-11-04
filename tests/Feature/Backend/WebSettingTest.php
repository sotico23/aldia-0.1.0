<?php

use App\Models\User;
use App\Models\WebSetting;
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

test('telegram-login-credentials can be saved with valid values', function () {
    $this->user->givePermissionTo('admin.web-settings.edit');

    $settings = WebSetting::factory()->create();

    $response = $this->actingAs($this->user)->putJson(route('configuracion-web.update', $settings->id), [
        'app_name' => 'GrowERP',
        'app_title' => 'GrowERP - Tu ERP todo-en-uno',
        'timezone' => 'UTC',
        'locale' => 'es_CL',
        'currency' => 'CLP',
        'currency_symbol' => '$',
        'telegram_login_bot_name' => 'mi_plataforma_bot',
        'telegram_login_bot_token' => '123456789:AAExampleToken',
        'telegram_login_redirect_uri' => 'https://example.test/auth/telegram/callback',
    ]);

    $response->assertRedirect(route('configuracion-web.index'));

    $settings->refresh();

    expect($settings->telegram_login_bot_name)->toBe('mi_plataforma_bot');
    expect($settings->telegram_login_bot_token)->toBe('123456789:AAExampleToken');
    expect($settings->telegram_login_redirect_uri)->toBe('https://example.test/auth/telegram/callback');
});

test('telegram-login-username is sanitized removing the leading @', function () {
    $this->user->givePermissionTo('admin.web-settings.edit');

    $settings = WebSetting::factory()->create();

    $response = $this->actingAs($this->user)->putJson(route('configuracion-web.update', $settings->id), [
        'app_name' => 'GrowERP',
        'app_title' => 'GrowERP - Tu ERP todo-en-uno',
        'timezone' => 'UTC',
        'locale' => 'es_CL',
        'currency' => 'CLP',
        'currency_symbol' => '$',
        'telegram_login_bot_name' => '@my_platform_bot',
        'telegram_login_bot_token' => '123456789:AAExampleToken',
        'telegram_login_redirect_uri' => 'https://example.test/auth/telegram/callback',
    ]);

    $response->assertRedirect(route('configuracion-web.index'));

    expect($settings->refresh()->telegram_login_bot_name)->toBe('my_platform_bot');
});

test('telegram-login-credentials require the three fields together', function () {
    $this->user->givePermissionTo('admin.web-settings.edit');

    $settings = WebSetting::factory()->create();

    $response = $this->actingAs($this->user)->putJson(route('configuracion-web.update', $settings->id), [
        'app_name' => 'GrowERP',
        'app_title' => 'GrowERP - Tu ERP todo-en-uno',
        'timezone' => 'UTC',
        'locale' => 'es_CL',
        'currency' => 'CLP',
        'currency_symbol' => '$',
        'telegram_login_bot_name' => 'my_platform_bot',
        'telegram_login_redirect_uri' => 'https://example.test/auth/telegram/callback',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('telegram_login_bot_token');
});

test('telegram-login empty fields are not persisted', function () {
    $this->user->givePermissionTo('admin.web-settings.edit');

    $settings = WebSetting::factory()->create([
        'telegram_login_bot_name' => 'old_bot',
        'telegram_login_bot_token' => 'old_token',
        'telegram_login_redirect_uri' => 'https://old.example.test/callback',
    ]);

    $response = $this->actingAs($this->user)->putJson(route('configuracion-web.update', $settings->id), [
        'app_name' => 'GrowERP',
        'app_title' => 'GrowERP - Tu ERP todo-en-uno',
        'timezone' => 'UTC',
        'locale' => 'es_CL',
        'currency' => 'CLP',
        'currency_symbol' => '$',
        'telegram_login_bot_name' => '',
        'telegram_login_bot_token' => '',
        'telegram_login_redirect_uri' => '',
    ]);

    $response->assertRedirect(route('configuracion-web.index'));

    $settings->refresh();

    expect($settings->telegram_login_bot_name)->toBeNull();
    expect($settings->telegram_login_bot_token)->toBeNull();
    expect($settings->telegram_login_redirect_uri)->toBeNull();
});

test('telegram-login credentials can be disconnected', function () {
    $this->user->givePermissionTo('admin.web-settings.edit');

    $settings = WebSetting::factory()->create([
        'telegram_login_bot_name' => 'my_platform_bot',
        'telegram_login_bot_token' => '123456789:AAExampleToken',
        'telegram_login_redirect_uri' => 'https://example.test/auth/telegram/callback',
    ]);

    $response = $this->actingAs($this->user)->postJson(route('web-settings.telegram-login.disconnect'));

    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
    ]);

    $settings->refresh();

    expect($settings->telegram_login_bot_name)->toBeNull();
    expect($settings->telegram_login_bot_token)->toBeNull();
    expect($settings->telegram_login_redirect_uri)->toBeNull();
});

test('disconnect-telegram-login requires the edit permission', function () {
    // The first user created in a fresh database becomes Super Admin and
    // bypasses permission checks, so use a dedicated second user here.
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('admin.web-settings.viewAny');

    WebSetting::factory()->create();

    $response = $this->actingAs($viewer)->postJson(route('web-settings.telegram-login.disconnect'));

    $response->assertStatus(403);
});

test('telegram-login test requires a bot token', function () {
    $response = $this->actingAs($this->user)->postJson(route('web-settings.telegram-login.test'), []);

    $response->assertStatus(422);
});

test('telegram-login test returns 422 when the token is invalid', function () {
    Http::fake([
        'api.telegram.org*' => Http::response(['ok' => false], 401),
    ]);

    $response = $this->actingAs($this->user)->postJson(route('web-settings.telegram-login.test'), [
        'bot_token' => 'invalid_token',
        'bot_username' => 'test_bot',
    ]);

    $response->assertStatus(422);
    $response->assertJson([
        'success' => false,
        'message' => 'El Token de Telegram ingresado no es válido o expiró.',
    ]);
});

test('telegram-login test returns 422 when is_bot is false', function () {
    Http::fake([
        'api.telegram.org*' => Http::response([
            'ok' => true,
            'result' => [
                'is_bot' => false,
                'username' => 'test_bot',
            ],
        ], 200),
    ]);

    $response = $this->actingAs($this->user)->postJson(route('web-settings.telegram-login.test'), [
        'bot_token' => 'valid_token',
        'bot_username' => 'test_bot',
    ]);

    $response->assertStatus(422);
});

test('telegram-login test returns 422 when the bot username does not match', function () {
    Http::fake([
        'api.telegram.org*' => Http::response([
            'ok' => true,
            'result' => [
                'is_bot' => true,
                'username' => 'different_bot',
            ],
        ], 200),
    ]);

    $response = $this->actingAs($this->user)->postJson(route('web-settings.telegram-login.test'), [
        'bot_token' => 'valid_token',
        'bot_username' => 'test_bot',
    ]);

    $response->assertStatus(422);
    $response->assertJson([
        'success' => false,
        'message' => 'El username del bot no coincide con el token proporcionado.',
    ]);
});

test('telegram-login test returns 200 with a valid token', function () {
    Http::fake([
        'api.telegram.org*' => Http::response([
            'ok' => true,
            'result' => [
                'is_bot' => true,
                'username' => 'test_bot',
            ],
        ], 200),
    ]);

    $response = $this->actingAs($this->user)->postJson(route('web-settings.telegram-login.test'), [
        'bot_token' => 'valid_token',
        'bot_username' => 'test_bot',
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
        'bot_username' => 'test_bot',
    ]);
});

test('telegram-login test handles a username with @ prefix', function () {
    Http::fake([
        'api.telegram.org*' => Http::response([
            'ok' => true,
            'result' => [
                'is_bot' => true,
                'username' => 'test_bot',
            ],
        ], 200),
    ]);

    $response = $this->actingAs($this->user)->postJson(route('web-settings.telegram-login.test'), [
        'bot_token' => 'valid_token',
        'bot_username' => '@test_bot',
    ]);

    $response->assertStatus(200);
});

test('telegram-login test returns network message on ConnectionException', function () {
    Http::fake(function () {
        throw new ConnectionException('cURL error 6: Could not resolve host');
    });

    $response = $this->actingAs($this->user)->postJson(route('web-settings.telegram-login.test'), [
        'bot_token' => 'valid_token',
        'bot_username' => 'test_bot',
    ]);

    $response->assertStatus(422);
    $response->assertJson([
        'success' => false,
        'message' => 'No se pudo conectar con la API de Telegram. Verifica tu conexión e inténtalo nuevamente.',
    ]);
});
