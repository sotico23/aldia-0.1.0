<?php

use App\Models\ApiIntegration;
use App\Models\ChannelCredential;
use App\Models\PaymentConfig;
use App\Models\SystemIntegration;
use App\Models\User;
use App\Scopes\OwnerScope;
use App\Services\TenantCredentialsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('guest cannot access integration endpoints', function () {
    $this->get(route('integraciones-api.index'))->assertRedirect(route('login'));
    $this->post(route('integraciones-api.save'))->assertRedirect(route('login'));
    $this->post(route('integraciones-api.test', ['provider' => 'webpay']))->assertRedirect(route('login'));
    $this->get(route('tenant-credentials.autocomplete'))->assertRedirect(route('login'));
});

test('index renders the integrations page with masked credentials', function () {
    ApiIntegration::factory()->create([
        'owner_id' => $this->user->getOwnerId(),
        'provider' => 'webpay',
        'credentials' => [
            'commerce_code' => '597055555532',
            'api_key' => 'secret_api_key',
        ],
        'environment' => 'integration',
        'is_active' => true,
    ]);

    $this->actingAs($this->user)
        ->get(route('integraciones-api.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Backend/Integraciones/Index')
            ->has('integraciones', 1)
            ->where('integraciones.0.provider', 'webpay')
            ->where('integraciones.0.credentials.commerce_code', '597055555532')
            ->where('integraciones.0.credentials.api_key', '••••••••••••••••')
        )
        ->assertDontSee('secret_api_key');
});

test('index only returns integrations of the current tenant', function () {
    $otherUser = User::factory()->create();
    $permission = Permission::firstOrCreate(['name' => 'sistema.integraciones.viewAny']);
    $otherUser->givePermissionTo($permission);

    ApiIntegration::factory()->create([
        'owner_id' => $otherUser->getOwnerId(),
        'provider' => 'webpay',
    ]);

    $this->actingAs($this->user)
        ->get(route('integraciones-api.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('integraciones', 0));
});

test('user without permission receives 403', function () {
    $otherUser = User::factory()->create();

    $this->actingAs($otherUser)
        ->get(route('integraciones-api.index'))
        ->assertForbidden();
});

test('save creates the integration with encrypted credentials', function () {
    $response = $this->actingAs($this->user)->postJson(route('integraciones-api.save'), [
        'provider' => 'webpay',
        'environment' => 'integration',
        'is_active' => true,
        'credentials' => [
            'commerce_code' => '597055555532',
            'api_key' => 'my_secret_key',
        ],
    ]);

    $response->assertOk()
        ->assertJson(['success' => true])
        ->assertJsonPath('data.credentials.api_key', '••••••••••••••••');

    $integration = ApiIntegration::withoutGlobalScope(OwnerScope::class)
        ->where('owner_id', $this->user->getOwnerId())
        ->where('provider', 'webpay')
        ->first();

    expect($integration)->not->toBeNull()
        ->and($integration->credentials['commerce_code'])->toBe('597055555532')
        ->and($integration->credentials['api_key'])->toBe('my_secret_key')
        ->and($integration->environment)->toBe('integration')
        ->and($integration->is_active)->toBeTrue();

    $raw = DB::table('api_integrations')
        ->where('owner_id', $this->user->getOwnerId())
        ->where('provider', 'webpay')
        ->value('credentials');

    expect($raw)->not->toBe('my_secret_key')
        ->and(Crypt::decryptString($raw))->toContain('my_secret_key');
});

test('save keeps the previously stored secret when the field is masked', function () {
    ApiIntegration::factory()->create([
        'owner_id' => $this->user->getOwnerId(),
        'provider' => 'webpay',
        'credentials' => [
            'commerce_code' => '597055555532',
            'api_key' => 'existing_secret_key',
        ],
    ]);

    $this->actingAs($this->user)->postJson(route('integraciones-api.save'), [
        'provider' => 'webpay',
        'credentials' => [
            'commerce_code' => '597055555532',
            'api_key' => '••••••••••••••••',
        ],
    ])->assertOk();

    $integration = ApiIntegration::withoutGlobalScope(OwnerScope::class)
        ->where('owner_id', $this->user->getOwnerId())
        ->where('provider', 'webpay')
        ->first();

    expect($integration->credentials['api_key'])->toBe('existing_secret_key');
});

test('save rejects an unknown provider', function () {
    $this->actingAs($this->user)->postJson(route('integraciones-api.save'), [
        'provider' => 'unknown_provider',
        'credentials' => ['foo' => 'bar'],
    ])->assertStatus(422)
        ->assertJsonValidationErrors('provider');
});

test('save dual-writes payment providers to PaymentConfig', function () {
    $this->actingAs($this->user)->postJson(route('integraciones-api.save'), [
        'provider' => 'paypal',
        'environment' => 'live',
        'is_active' => true,
        'credentials' => [
            'paypal_client_id' => 'client_123',
            'paypal_client_secret' => 'secret_456',
        ],
    ])->assertOk();

    $config = PaymentConfig::withoutGlobalScope(OwnerScope::class)
        ->where('owner_id', $this->user->getOwnerId())
        ->first();

    expect($config)->not->toBeNull()
        ->and($config->paypal_client_id)->toBe('client_123')
        ->and($config->paypal_client_secret)->toBe('secret_456')
        ->and($config->paypal_mode)->toBe('live')
        ->and($config->paypal_active)->toBeTrue();
});

test('tenant credentials service falls back to legacy PaymentConfig', function () {
    PaymentConfig::withoutGlobalScope(OwnerScope::class)->create([
        'owner_id' => $this->user->getOwnerId(),
        'commerce_code' => '597055555532',
        'api_key' => 'legacy_secret',
        'environment' => 'integration',
        'is_active' => true,
    ]);

    $service = app(TenantCredentialsService::class);
    $credentials = $service->get('webpay', $this->user->id);

    expect($credentials['commerce_code'])->toBe('597055555532')
        ->and($credentials['api_key'])->toBe('legacy_secret');
});

test('tenant credentials service falls back to global n8n integration', function () {
    SystemIntegration::create([
        'provider' => 'n8n',
        'telegram_proxy_url' => 'https://global-n8n.example.com/webhook/proxy',
        'api_key' => 'global_n8n_key',
        'is_active' => true,
    ]);

    $service = app(TenantCredentialsService::class);
    $credentials = $service->get('n8n', $this->user->id);

    expect($credentials['telegram_proxy_url'])->toBe('https://global-n8n.example.com/webhook/proxy')
        ->and($credentials['api_key'])->toBe('global_n8n_key');
});

test('autocomplete returns decrypted credentials of the tenant', function () {
    ApiIntegration::factory()->create([
        'owner_id' => $this->user->getOwnerId(),
        'provider' => 'mercadopago',
        'credentials' => [
            'mercadopago_public_key' => 'PUB_KEY',
            'mercadopago_access_token' => 'ACCESS_SECRET',
        ],
    ]);

    $this->actingAs($this->user)
        ->getJson(route('tenant-credentials.autocomplete'))
        ->assertOk()
        ->assertJsonPath('data.mercadopago.mercadopago_public_key', 'PUB_KEY')
        ->assertJsonPath('data.mercadopago.mercadopago_access_token', 'ACCESS_SECRET');
});

test('autocomplete does not leak other tenants credentials', function () {
    $otherUser = User::factory()->create();
    $permission = Permission::firstOrCreate(['name' => 'sistema.integraciones.viewAny']);
    $otherUser->givePermissionTo($permission);

    ApiIntegration::factory()->create([
        'owner_id' => $otherUser->getOwnerId(),
        'provider' => 'webpay',
        'credentials' => ['commerce_code' => '11111111', 'api_key' => 'other_secret'],
    ]);

    $this->actingAs($this->user)
        ->getJson(route('tenant-credentials.autocomplete'))
        ->assertOk()
        ->assertJsonPath('data.webpay', null);
});

test('test connection succeeds for telegram with valid token', function () {
    ApiIntegration::factory()->create([
        'owner_id' => $this->user->getOwnerId(),
        'provider' => 'telegram',
        'credentials' => [
            'telegram_bot_token' => '123456:ABC-DEF',
            'telegram_bot_username' => 'mi_bot',
        ],
    ]);

    Http::fake([
        'api.telegram.org/*' => Http::response([
            'ok' => true,
            'result' => ['id' => 1, 'is_bot' => true, 'username' => 'mi_bot'],
        ], 200),
    ]);

    $this->actingAs($this->user)
        ->postJson(route('integraciones-api.test', ['provider' => 'telegram']))
        ->assertOk()
        ->assertJson(['success' => true]);

    $integration = ApiIntegration::withoutGlobalScope(OwnerScope::class)
        ->where('owner_id', $this->user->getOwnerId())
        ->where('provider', 'telegram')
        ->first();

    expect($integration->last_tested_status)->toBe('ok')
        ->and($integration->last_tested_at)->not->toBeNull();
});

test('test connection fails for telegram with invalid token', function () {
    ApiIntegration::factory()->create([
        'owner_id' => $this->user->getOwnerId(),
        'provider' => 'telegram',
        'credentials' => ['telegram_bot_token' => 'bad_token'],
    ]);

    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => false], 401),
    ]);

    $this->actingAs($this->user)
        ->postJson(route('integraciones-api.test', ['provider' => 'telegram']))
        ->assertStatus(422)
        ->assertJson(['success' => false]);

    $integration = ApiIntegration::withoutGlobalScope(OwnerScope::class)
        ->where('owner_id', $this->user->getOwnerId())
        ->where('provider', 'telegram')
        ->first();

    expect($integration->last_tested_status)->toBe('error');
});

test('test connection fails when no credentials are saved', function () {
    $this->actingAs($this->user)
        ->postJson(route('integraciones-api.test', ['provider' => 'webpay']))
        ->assertStatus(422)
        ->assertJson(['success' => false]);
});

test('test connection fails when secret is masked', function () {
    ApiIntegration::factory()->create([
        'owner_id' => $this->user->getOwnerId(),
        'provider' => 'mercadopago',
        'credentials' => [
            'mercadopago_access_token' => '••••••••••••••••',
        ],
    ]);

    $this->actingAs($this->user)
        ->postJson(route('integraciones-api.test', ['provider' => 'mercadopago']))
        ->assertStatus(422)
        ->assertJson(['success' => false]);
});

test('test connection succeeds for mercadopago', function () {
    ApiIntegration::factory()->create([
        'owner_id' => $this->user->getOwnerId(),
        'provider' => 'mercadopago',
        'credentials' => ['mercadopago_access_token' => 'APP_USR_TOKEN'],
    ]);

    Http::fake([
        'api.mercadopago.com/*' => Http::response(['id' => 123], 200),
    ]);

    $this->actingAs($this->user)
        ->postJson(route('integraciones-api.test', ['provider' => 'mercadopago']))
        ->assertOk()
        ->assertJson(['success' => true]);
});

test('test connection uses live paypal endpoint when environment is live', function () {
    ApiIntegration::factory()->create([
        'owner_id' => $this->user->getOwnerId(),
        'provider' => 'paypal',
        'environment' => 'live',
        'credentials' => [
            'paypal_client_id' => 'client_1',
            'paypal_client_secret' => 'secret_1',
        ],
    ]);

    Http::fake([
        'api-m.paypal.com/*' => Http::response(['access_token' => 'tok'], 200),
    ]);

    Http::assertNothingSent();

    $this->actingAs($this->user)
        ->postJson(route('integraciones-api.test', ['provider' => 'paypal']))
        ->assertOk()
        ->assertJson(['success' => true]);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'api-m.paypal.com'));
});

test('n8n test reuses the tenant proxy url from the integration', function () {
    ApiIntegration::factory()->create([
        'owner_id' => $this->user->getOwnerId(),
        'provider' => 'n8n',
        'credentials' => ['telegram_proxy_url' => 'https://tenant-n8n.example.com/webhook/proxy'],
    ]);

    Http::fake([
        'https://tenant-n8n.example.com/*' => Http::response(['status' => 'ok'], 200),
    ]);

    $this->actingAs($this->user)
        ->postJson(route('integraciones-api.test', ['provider' => 'n8n']))
        ->assertOk()
        ->assertJson(['success' => true]);
});

test('telegram and whatsapp legacy fallback reads from ChannelCredential', function () {
    ChannelCredential::withoutGlobalScope(OwnerScope::class)->create([
        'owner_id' => $this->user->getOwnerId(),
        'telegram_bot_token' => 'tg_token',
        'telegram_bot_username' => '@mi_bot',
        'whatsapp_access_token' => 'wa_token',
        'whatsapp_phone_number_id' => '123456789',
    ]);

    $service = app(TenantCredentialsService::class);
    $telegram = $service->get('telegram', $this->user->id);
    $whatsapp = $service->get('whatsapp', $this->user->id);

    expect($telegram['telegram_bot_token'])->toBe('tg_token')
        ->and($whatsapp['whatsapp_access_token'])->toBe('wa_token')
        ->and($whatsapp['whatsapp_phone_number_id'])->toBe('123456789');
});
