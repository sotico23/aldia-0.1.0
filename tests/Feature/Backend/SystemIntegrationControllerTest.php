<?php

use App\Models\ChannelCredential;
use App\Models\SystemIntegration;
use App\Models\TelegramLinkingToken;
use App\Models\User;
use App\Models\WebSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'Super Admin'], ['owner_id' => null]);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Super Admin');
});

test('show returns null when no integration config exists', function () {
    $response = $this->actingAs($this->admin)
        ->getJson('/api/system-integrations/n8n');

    $response->assertOk();
    $response->assertJson(['data' => null]);
});

test('show returns integration config with masked api key', function () {
    SystemIntegration::create([
        'provider' => 'n8n',
        'base_url' => 'https://n8n.example.com',
        'webhook_url' => 'https://n8n.example.com/webhook/test',
        'api_key' => 'secret-key-123',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->admin)
        ->getJson('/api/system-integrations/n8n');

    $response->assertOk();
    $response->assertJson([
        'data' => [
            'provider' => 'n8n',
            'base_url' => 'https://n8n.example.com',
            'webhook_url' => 'https://n8n.example.com/webhook/test',
            'api_key' => '••••••••••••••••',
            'is_active' => true,
        ],
    ]);
});

test('update creates new integration config', function () {
    $response = $this->actingAs($this->admin)
        ->putJson('/api/system-integrations/n8n', [
            'base_url' => 'https://n8n.example.com',
            'webhook_url' => 'https://n8n.example.com/webhook/test',
            'api_key' => 'new-secret-key',
            'is_active' => true,
        ]);

    $response->assertOk();
    $response->assertJson(['success' => true]);

    $this->assertDatabaseHas('system_integrations', [
        'provider' => 'n8n',
        'base_url' => 'https://n8n.example.com',
        'webhook_url' => 'https://n8n.example.com/webhook/test',
        'is_active' => true,
    ]);
});

test('update requires api key when creating', function () {
    $response = $this->actingAs($this->admin)
        ->putJson('/api/system-integrations/n8n', [
            'base_url' => 'https://n8n.example.com',
            'webhook_url' => 'https://n8n.example.com/webhook/test',
            'is_active' => true,
        ]);

    $response->assertStatus(422);
    $response->assertJson([
        'success' => false,
        'message' => 'La API Key es requerida al crear la configuración.',
    ]);
});

test('update keeps existing api key when not provided', function () {
    SystemIntegration::create([
        'provider' => 'n8n',
        'base_url' => 'https://n8n.example.com',
        'webhook_url' => 'https://n8n.example.com/webhook/test',
        'api_key' => 'existing-secret',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->admin)
        ->putJson('/api/system-integrations/n8n', [
            'base_url' => 'https://n8n-updated.example.com',
            'webhook_url' => 'https://n8n-updated.example.com/webhook/test',
            'is_active' => false,
        ]);

    $response->assertOk();

    $config = SystemIntegration::forProvider('n8n')->first();
    expect($config->api_key)->toBe('existing-secret');
    expect($config->base_url)->toBe('https://n8n-updated.example.com');
    expect($config->is_active)->toBeFalse();
});

test('update validates required fields', function () {
    $response = $this->actingAs($this->admin)
        ->putJson('/api/system-integrations/n8n', []);

    $response->assertStatus(422);
    $response->assertJson([
        'success' => false,
        'message' => 'La URL Base es requerida al crear la configuración.',
    ]);
});

test('test connection returns error when no url configured', function () {
    config(['services.n8n.webhook_url' => null]);
    config(['services.n8n.telegram_proxy_url' => null]);
    config(['services.n8n.base_url' => null]);

    $response = $this->actingAs($this->admin)
        ->postJson('/api/system-integrations/n8n/test');

    $response->assertOk();
    $response->assertJson([
        'success' => false,
        'message' => 'No hay URL de n8n configurada. Ingresa Telegram Proxy URL, Webhook URL o Base URL.',
    ]);
});

test('test connection posts flow payload to webhook url', function () {
    SystemIntegration::create([
        'provider' => 'n8n',
        'base_url' => 'https://n8n.example.com',
        'webhook_url' => 'https://n8n.example.com/webhook/test',
        'api_key' => 'secret',
        'is_active' => true,
    ]);

    ChannelCredential::create([
        'owner_id' => $this->admin->getOwnerId(),
        'telegram_bot_token' => 'test:token',
        'telegram_bot_username' => 'test_bot',
        'telegram_chat_id' => '123456789',
    ]);

    $config = SystemIntegration::forProvider('n8n')->first();
    expect($config)->not->toBeNull();

    Http::fake([
        'n8n.example.com/*' => Http::response(['status' => 'ok'], 200),
    ]);

    $response = $this->actingAs($this->admin)
        ->postJson('/api/system-integrations/n8n/test');

    $response->assertOk();
    $response->assertJson(['success' => true]);

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $request->method() === 'POST'
            && $request->url() === 'https://n8n.example.com/webhook/test'
            && $body['event'] === 'message'
            && $body['type'] === 'message'
            && $body['is_test'] === false
            && $body['chat_id'] === '123456789'
            && $body['is_linked'] === true
            && $body['linking_url'] === null
            && $body['text'] === 'Inicio de prueba de flujo'
            && $body['user_message'] === 'Inicio de prueba de flujo'
            && $body['message'] === ['text' => 'Inicio de prueba de flujo']
            && array_key_exists('bot_token', $body)
            && array_key_exists('bot_username', $body);
    });
});

test('test connection does NOT create phantom linking tokens and downgrades to short-circuit when no chat is linked', function () {
    WebSetting::create([
        'app_name' => 'Aldia',
        'global_telegram_bot_username' => 'aldia_global_bot',
    ]);

    SystemIntegration::create([
        'provider' => 'n8n',
        'base_url' => 'https://n8n.example.com',
        'webhook_url' => 'https://n8n.example.com/webhook/test',
        'api_key' => 'secret',
        'is_active' => true,
    ]);

    Http::fake([
        'n8n.example.com/*' => Http::response(['status' => 'ok'], 200),
    ]);

    $response = $this->actingAs($this->admin)
        ->postJson('/api/system-integrations/n8n/test');

    $response->assertOk();

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $request->url() === 'https://n8n.example.com/webhook/test'
            && $body['event'] === 'test_connection'
            && $body['type'] === 'test_connection'
            && $body['is_test'] === true
            && $body['message'] === 'Prueba de conexión desde la plataforma'
            && $body['linking_url'] === null
            && $body['chat_id'] === null
            && $body['is_linked'] === false;
    });

    // A connection test must NOT mint linking tokens (used_at/chat_id stay NULL).
    expect(TelegramLinkingToken::where('owner_id', $this->admin->getOwnerId())->count())->toBe(0);
});

test('test connection marks status on failure', function () {
    SystemIntegration::create([
        'provider' => 'n8n',
        'base_url' => 'https://n8n.example.com',
        'webhook_url' => 'https://n8n.example.com/webhook/test',
        'api_key' => 'secret',
        'is_active' => true,
    ]);

    Http::fake([
        'n8n.example.com/*' => Http::response(null, 500),
    ]);

    $response = $this->actingAs($this->admin)
        ->postJson('/api/system-integrations/n8n/test');

    $response->assertOk();
    $response->assertJson(['success' => false]);

    $config = SystemIntegration::forProvider('n8n')->first();
    expect($config->last_check_status)->toBe('error');
    expect($config->last_check_at)->not->toBeNull();
});

test('forbidden for user without Master or Super Admin role', function () {
    User::factory()->create(['email' => 'dummy@setup.test']);
    $regularUser = User::factory()->create(['email' => 'regular@test.com']);

    $response = $this->actingAs($regularUser)
        ->getJson('/api/system-integrations/n8n');

    $response->assertForbidden();
});

test('update with WhatsApp fields', function () {
    $response = $this->actingAs($this->admin)
        ->putJson('/api/system-integrations/n8n', [
            'base_url' => 'https://n8n.example.com',
            'webhook_url' => 'https://n8n.example.com/webhook/test',
            'api_key' => 'new-secret-key',
            'whatsapp_phone_number_id' => '1234567890',
            'whatsapp_access_token' => 'whatsapp-token-123',
            'whatsapp_business_id' => '9876543210',
            'whatsapp_api_version' => 'v22.0',
            'is_active' => true,
        ]);

    $response->assertOk();
    $response->assertJson(['success' => true]);

    $this->assertDatabaseHas('system_integrations', [
        'provider' => 'n8n',
        'base_url' => 'https://n8n.example.com',
        'whatsapp_phone_number_id' => '1234567890',
        'whatsapp_business_id' => '9876543210',
        'whatsapp_api_version' => 'v22.0',
    ]);
});

test('test WhatsApp connection returns error when no credentials configured', function () {
    $response = $this->actingAs($this->admin)
        ->postJson('/api/system-integrations/n8n/test-whatsapp');

    $response->assertStatus(422);
    $response->assertJson([
        'success' => false,
        'message' => 'No hay Phone Number ID de WhatsApp configurado.',
    ]);
});

test('test WhatsApp connection returns error when no access token', function () {
    SystemIntegration::create([
        'provider' => 'n8n',
        'base_url' => 'https://n8n.example.com',
        'whatsapp_phone_number_id' => '1234567890',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->admin)
        ->postJson('/api/system-integrations/n8n/test-whatsapp', [
            'whatsapp_phone_number_id' => '1234567890',
        ]);

    $response->assertStatus(422);
    $response->assertJson([
        'success' => false,
        'message' => 'No hay Access Token de WhatsApp configurado.',
    ]);
});

test('test WhatsApp connection calls Facebook Graph API', function () {
    SystemIntegration::create([
        'provider' => 'n8n',
        'base_url' => 'https://n8n.example.com',
        'whatsapp_phone_number_id' => '1234567890',
        'whatsapp_access_token' => 'whatsapp-token-123',
        'whatsapp_business_id' => '9876543210',
        'whatsapp_api_version' => 'v22.0',
        'is_active' => true,
    ]);

    Http::fake([
        'graph.facebook.com/v22.0/1234567890' => Http::response([
            'id' => '1234567890',
            'business' => ['id' => '9876543210'],
            'display_phone' => '+1234567890',
            'name' => 'Test Business',
        ], 200),
    ]);

    $response = $this->actingAs($this->admin)
        ->postJson('/api/system-integrations/n8n/test-whatsapp');

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'message' => 'Conexión exitosa con WhatsApp Cloud API.',
    ]);
});
