<?php

use App\Models\SystemIntegration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create();
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
    $response->assertJsonValidationErrors(['base_url']);
});

test('test connection returns error when no config saved', function () {
    $response = $this->actingAs($this->admin)
        ->postJson('/api/system-integrations/n8n/test');

    $response->assertStatus(422);
    $response->assertJson([
        'success' => false,
        'message' => 'No se pudo conectar con n8n. Verifica la URL e intenta nuevamente.',
    ]);
});

test('test connection calls n8n health endpoint', function () {
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
    $response->assertJson(['success' => true]);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://n8n.example.com/healthz';
    });
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

    $response->assertStatus(422);
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
