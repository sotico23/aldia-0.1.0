<?php

use App\Models\SystemIntegration;
use App\Models\Setting;
use App\Models\User;
use App\Support\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'admin.web-settings.viewAny', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'admin.web-settings.edit', 'guard_name' => 'web']);

    $this->user = User::factory()->create();
    $this->user->givePermissionTo([
        'admin.web-settings.viewAny',
        'admin.web-settings.edit',
    ]);
});

test('gateway settings show includes official channel configuration without a master payment config', function () {
    $response = $this->actingAs($this->user)
        ->getJson(route('configuracion-web.gateway-settings'));

    $response->assertSuccessful()
        ->assertJsonPath('data.telegram.bot_username', '')
        ->assertJsonPath('data.telegram.has_bot_token', false)
        ->assertJsonPath('data.whatsapp.phone_number_id', '')
        ->assertJsonPath('data.whatsapp.has_access_token', false);
});

test('gateway settings expose service configuration without returning stored secrets', function () {
    SystemSettings::save([
        'REVERB_APP_SECRET' => [
            'value' => 'reverb-secret-test',
            'secret' => true,
            'group' => 'reverb',
        ],
        'OPENROUTER_API_KEY' => [
            'value' => 'openrouter-secret-test',
            'secret' => true,
            'group' => 'openrouter',
        ],
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('configuracion-web.gateway-settings'));

    $response->assertSuccessful()
        ->assertJsonPath('data.system_settings.reverb.fields.REVERB_APP_SECRET.configured', true)
        ->assertJsonPath('data.system_settings.sii.fields.SII_SEMILLA_URL.type', 'url')
        ->assertJsonMissingPath('data.system_settings.reverb.fields.REVERB_APP_SECRET.value');

    expect($response->getContent())
        ->not->toContain('reverb-secret-test')
        ->not->toContain('openrouter-secret-test');
});

test('gateway service settings can be updated and secrets are encrypted', function () {
    $response = $this->actingAs($this->user)
        ->putJson(route('configuracion-web.gateway-settings.services'), [
            'values' => [
                'REVERB_APP_ID' => 'app-test',
                'REVERB_HOST' => 'reverb.example.test',
                'REVERB_PORT' => '8081',
                'REVERB_SCHEME' => 'http',
                'REVERB_ALLOWED_ORIGINS' => 'app.example.test,admin.example.test',
                'REVERB_SERVER_PORT' => '8081',
                'SII_SEMILLA_URL' => 'https://sii.example.test/seed',
                'AWS_USE_PATH_STYLE_ENDPOINT' => 'true',
            ],
            'secrets' => [
                'REVERB_APP_SECRET' => 'reverb-secret-to-encrypt',
                'OPENROUTER_API_KEY' => 'openrouter-secret-to-encrypt',
            ],
        ]);

    $response->assertSuccessful()
        ->assertJsonPath('success', true);

    $storedSecret = Setting::query()->where('key', 'REVERB_APP_SECRET')->firstOrFail();
    $rawSecret = DB::table('settings')->where('key', 'REVERB_APP_SECRET')->value('value');

    expect($storedSecret->is_encrypted)->toBeTrue()
        ->and($storedSecret->value)->toBe('reverb-secret-to-encrypt')
        ->and($rawSecret)->not->toBe('reverb-secret-to-encrypt')
        ->and(SystemSettings::get('OPENROUTER_API_KEY'))->toBe('openrouter-secret-to-encrypt')
        ->and(config('broadcasting.connections.reverb.options.host'))->toBe('reverb.example.test')
        ->and(config('broadcasting.connections.reverb.options.port'))->toBe(8081)
        ->and(config('broadcasting.connections.reverb.options.useTLS'))->toBeFalse()
        ->and(config('reverb.apps.apps.0.allowed_origins'))->toBe([
            'app.example.test',
            'admin.example.test',
        ])
        ->and(config('filesystems.disks.s3.use_path_style_endpoint'))->toBeTrue()
        ->and(config('sii.urls.certificacion.semilla'))->toBe('https://sii.example.test/seed');
});

test('gateway service settings reject unknown fields and invalid service URLs', function () {
    $this->actingAs($this->user)
        ->putJson(route('configuracion-web.gateway-settings.services'), [
            'values' => [
                'SII_SEMILLA_URL' => 'javascript:alert(1)',
                'UNSUPPORTED_SETTING' => 'unexpected',
            ],
            'secrets' => [],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'values',
            'values.SII_SEMILLA_URL',
        ]);
});

test('gateway service settings require edit permission', function () {
    $userWithoutPermission = User::factory()->create();
    $userWithoutPermission->givePermissionTo(
        Permission::firstOrCreate([
            'name' => 'admin.web-settings.viewAny',
            'guard_name' => 'web',
        ])
    );

    $this->actingAs($userWithoutPermission)
        ->putJson(route('configuracion-web.gateway-settings.services'), [
            'values' => [],
            'secrets' => [],
        ])
        ->assertForbidden();
});

test('telegram official configuration can be saved and masks the token', function () {
    $response = $this->actingAs($this->user)
        ->putJson(route('configuracion-web.gateway-settings.telegram'), [
            'bot_username' => '@aldia_bot',
            'bot_token' => '123456:ABCdef',
            'is_active' => true,
        ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.bot_username', 'aldia_bot')
        ->assertJsonPath('data.bot_token', '••••••••••••••••')
        ->assertJsonPath('data.has_bot_token', true);

    $integration = SystemIntegration::forProvider('telegram')->first();

    expect($integration)->not->toBeNull()
        ->and($integration->telegram_bot_token)->toBe('123456:ABCdef')
        ->and($integration->telegram_bot_username)->toBe('aldia_bot')
        ->and($integration->telegram_webhook_secret)->not->toBeEmpty()
        ->and($integration->is_active)->toBeTrue();
});

test('telegram official configuration preserves the token when the masked value is sent', function () {
    SystemIntegration::create([
        'provider' => 'telegram',
        'telegram_bot_token' => 'original-token',
        'telegram_bot_username' => 'original_bot',
        'is_active' => true,
    ]);

    $this->actingAs($this->user)
        ->putJson(route('configuracion-web.gateway-settings.telegram'), [
            'bot_username' => 'new_bot',
            'bot_token' => '••••••••••••••••',
            'is_active' => true,
        ])
        ->assertSuccessful();

    $integration = SystemIntegration::forProvider('telegram')->first();

    expect($integration->telegram_bot_token)->toBe('original-token')
        ->and($integration->telegram_bot_username)->toBe('new_bot');
});

test('telegram official configuration requires a token when none exists', function () {
    $response = $this->actingAs($this->user)
        ->putJson(route('configuracion-web.gateway-settings.telegram'), [
            'bot_username' => 'aldia_bot',
            'is_active' => true,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('bot_token');
});

test('telegram test returns 422 when no token is configured', function () {
    $response = $this->actingAs($this->user)
        ->postJson(route('configuracion-web.gateway-settings.telegram.test'));

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'No hay un token de bot de Telegram configurado.',
        ]);
});

test('telegram test returns 422 when the token is invalid', function () {
    Http::fake([
        'api.telegram.org*' => Http::response(['ok' => false], 401),
    ]);

    $response = $this->actingAs($this->user)
        ->postJson(route('configuracion-web.gateway-settings.telegram.test'), [
            'bot_token' => 'invalid-token',
            'bot_username' => 'test_bot',
        ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'El Token de Telegram ingresado no es válido o expiró.',
        ]);
});

test('telegram test returns 422 when is_bot is false', function () {
    Http::fake([
        'api.telegram.org*' => Http::response([
            'ok' => true,
            'result' => [
                'is_bot' => false,
                'username' => 'test_bot',
            ],
        ], 200),
    ]);

    $response = $this->actingAs($this->user)
        ->postJson(route('configuracion-web.gateway-settings.telegram.test'), [
            'bot_token' => 'valid-token',
            'bot_username' => 'test_bot',
        ]);

    $response->assertStatus(422);
});

test('telegram test returns 422 when the username does not match', function () {
    Http::fake([
        'api.telegram.org*' => Http::response([
            'ok' => true,
            'result' => [
                'is_bot' => true,
                'username' => 'different_bot',
            ],
        ], 200),
    ]);

    $response = $this->actingAs($this->user)
        ->postJson(route('configuracion-web.gateway-settings.telegram.test'), [
            'bot_token' => 'valid-token',
            'bot_username' => 'test_bot',
        ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'El username del bot no coincide con el token proporcionado.',
        ]);
});

test('telegram test returns 200 when the token is valid', function () {
    Http::fake([
        'api.telegram.org*' => Http::response([
            'ok' => true,
            'result' => [
                'id' => 123,
                'is_bot' => true,
                'username' => 'test_bot',
                'first_name' => 'Test Bot',
            ],
        ], 200),
    ]);

    $response = $this->actingAs($this->user)
        ->postJson(route('configuracion-web.gateway-settings.telegram.test'), [
            'bot_token' => 'valid-token',
            'bot_username' => '@test_bot',
        ]);

    $response->assertSuccessful()
        ->assertJson([
            'success' => true,
            'bot_username' => 'test_bot',
        ]);
});

test('telegram test returns a network message on connection exception', function () {
    Http::fake(function () {
        throw new ConnectionException('cURL error 6: Could not resolve host');
    });

    $response = $this->actingAs($this->user)
        ->postJson(route('configuracion-web.gateway-settings.telegram.test'), [
            'bot_token' => 'valid-token',
            'bot_username' => 'test_bot',
        ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'No se pudo conectar con la API de Telegram. Verifica tu conexión e inténtalo nuevamente.',
        ]);
});

test('telegram webhook requires a stored token', function () {
    $response = $this->actingAs($this->user)
        ->postJson(route('configuracion-web.gateway-settings.telegram.webhook'));

    $response->assertStatus(422)
        ->assertJsonValidationErrors('bot_token');
});

test('telegram webhook registers the global callback with the secret token', function () {
    Http::fake([
        'api.telegram.org*' => Http::response([
            'ok' => true,
            'result' => true,
        ], 200),
    ]);

    SystemIntegration::create([
        'provider' => 'telegram',
        'telegram_bot_token' => 'valid-token',
        'telegram_bot_username' => 'test_bot',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson(route('configuracion-web.gateway-settings.telegram.webhook'));

    $response->assertSuccessful()
        ->assertJson([
            'success' => true,
            'webhook_configured' => true,
            'webhook_url' => route('webhooks.telegram.global'),
        ]);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/setWebhook')
        && $request['url'] === route('webhooks.telegram.global')
        && filled($request['secret_token']));
});

test('telegram webhook can be deleted', function () {
    Http::fake([
        'api.telegram.org*' => Http::response([
            'ok' => true,
            'result' => true,
        ], 200),
    ]);

    SystemIntegration::create([
        'provider' => 'telegram',
        'telegram_bot_token' => 'valid-token',
        'telegram_bot_username' => 'test_bot',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->deleteJson(route('configuracion-web.gateway-settings.telegram.webhook.delete'));

    $response->assertSuccessful()
        ->assertJson([
            'success' => true,
            'message' => 'Webhook de Telegram eliminado correctamente.',
        ]);
});

test('whatsapp official configuration can be saved with generated secrets', function () {
    $response = $this->actingAs($this->user)
        ->putJson(route('configuracion-web.gateway-settings.whatsapp'), [
            'phone_number_id' => '1234567890',
            'access_token' => 'access-token',
            'business_id' => '9876543210',
            'api_version' => 'v23.0',
            'app_secret' => 'app-secret',
            'is_active' => true,
        ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.phone_number_id', '1234567890')
        ->assertJsonPath('data.has_access_token', true)
        ->assertJsonPath('data.has_app_secret', true);

    $integration = SystemIntegration::forProvider('whatsapp')->first();

    expect($integration)->not->toBeNull()
        ->and($integration->whatsapp_access_token)->toBe('access-token')
        ->and($integration->whatsapp_app_secret)->toBe('app-secret')
        ->and($integration->whatsapp_verify_token)->not->toBeEmpty()
        ->and($integration->whatsapp_webhook_key)->not->toBeEmpty()
        ->and($integration->is_active)->toBeTrue();
});

test('whatsapp official configuration requires credentials when active', function () {
    $response = $this->actingAs($this->user)
        ->putJson(route('configuracion-web.gateway-settings.whatsapp'), [
            'phone_number_id' => '1234567890',
            'is_active' => true,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('access_token');
});

test('whatsapp test returns 422 when credentials are missing', function () {
    $response = $this->actingAs($this->user)
        ->postJson(route('configuracion-web.gateway-settings.whatsapp.test'));

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'No hay Access Token o Phone Number ID de WhatsApp configurado.',
        ]);
});

test('whatsapp test validates credentials against the graph api', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'id' => '1234567890',
            'business' => ['id' => '9876543210'],
            'display_phone' => '+51 999 999 999',
            'name' => 'Aldia',
        ], 200),
    ]);

    $response = $this->actingAs($this->user)
        ->postJson(route('configuracion-web.gateway-settings.whatsapp.test'), [
            'access_token' => 'access-token',
            'phone_number_id' => '1234567890',
        ]);

    $response->assertSuccessful()
        ->assertJson([
            'success' => true,
            'business_id' => '9876543210',
        ]);
});

test('whatsapp test returns the graph api error', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'error' => ['message' => 'Invalid OAuth access token'],
        ], 400),
    ]);

    $response = $this->actingAs($this->user)
        ->postJson(route('configuracion-web.gateway-settings.whatsapp.test'), [
            'access_token' => 'bad-token',
            'phone_number_id' => '1234567890',
        ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'Error: Invalid OAuth access token',
        ]);
});

test('whatsapp webhook requires stored credentials', function () {
    $response = $this->actingAs($this->user)
        ->postJson(route('configuracion-web.gateway-settings.whatsapp.webhook'));

    $response->assertStatus(422)
        ->assertJsonValidationErrors('access_token');
});

test('whatsapp webhook subscribes the app and returns the manual setup data', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['success' => true], 200),
    ]);

    SystemIntegration::create([
        'provider' => 'whatsapp',
        'whatsapp_phone_number_id' => '1234567890',
        'whatsapp_access_token' => 'access-token',
        'whatsapp_business_id' => '9876543210',
        'whatsapp_api_version' => 'v22.0',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson(route('configuracion-web.gateway-settings.whatsapp.webhook'));

    $response->assertSuccessful()
        ->assertJson([
            'success' => true,
            'manual_setup' => true,
            'webhook_url' => route('webhooks.whatsapp.global'),
        ])
        ->assertJsonPath('verify_token', fn ($value) => filled($value));

    Http::assertSent(fn ($request) => str_contains($request->url(), '/subscribed_apps'));
});

test('whatsapp webhook returns the graph api error', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'error' => ['message' => 'Invalid OAuth access token'],
        ], 400),
    ]);

    SystemIntegration::create([
        'provider' => 'whatsapp',
        'whatsapp_phone_number_id' => '1234567890',
        'whatsapp_access_token' => 'bad-token',
        'whatsapp_business_id' => '9876543210',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson(route('configuracion-web.gateway-settings.whatsapp.webhook'));

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'Error: Invalid OAuth access token',
        ]);
});
