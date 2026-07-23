<?php

use App\Models\AutomationConfig;
use App\Models\ChannelCredential;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('channel credentials page loads automation config', function () {
    AutomationConfig::create([
        'owner_id' => $this->user->getOwnerId(),
        'channel' => 'telegram',
        'frequency' => 'daily',
        'execution_time' => '08:00',
        'enabled' => true,
        'selected_reports' => ['ventas', 'inventario'],
    ]);

    $response = $this->actingAs($this->user)->get(route('channel-credentials.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Backend/ChannelCredentials')
        ->has('automation')
        ->where('automation.channel', 'telegram')
        ->where('automation.frequency', 'daily')
        ->where('automation.execution_time', '08:00')
        ->where('automation.enabled', true)
        ->where('automation.selected_reports', ['ventas', 'inventario'])
    );
});

test('channel credentials page returns null automation when not configured', function () {
    $response = $this->actingAs($this->user)->get(route('channel-credentials.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Backend/ChannelCredentials')
        ->where('automation', null)
    );
});

test('store creates automation config successfully', function () {
    $response = $this->actingAs($this->user)->post(route('automation.store'), [
        'channel' => 'telegram',
        'frequency' => 'daily',
        'execution_time' => '08:00',
        'enabled' => true,
        'selected_reports' => ['ventas', 'inventario', 'stock_bajo'],
    ]);

    $response->assertSuccessful();
    $response->assertJson(['success' => true]);

    $this->assertDatabaseHas('automation_configs', [
        'owner_id' => $this->user->getOwnerId(),
        'channel' => 'telegram',
        'frequency' => 'daily',
        'execution_time' => '08:00',
        'enabled' => true,
    ]);

    $config = AutomationConfig::where('owner_id', $this->user->getOwnerId())->first();
    expect($config->selected_reports)->toBe(['ventas', 'inventario', 'stock_bajo']);
});

test('store updates existing automation config', function () {
    AutomationConfig::create([
        'owner_id' => $this->user->getOwnerId(),
        'channel' => 'telegram',
        'frequency' => 'daily',
        'execution_time' => '08:00',
        'enabled' => false,
        'selected_reports' => ['ventas'],
    ]);

    $response = $this->actingAs($this->user)->post(route('automation.store'), [
        'channel' => 'whatsapp',
        'frequency' => 'weekly',
        'execution_time' => '10:30',
        'enabled' => true,
        'selected_reports' => ['resumen_ejecutivo', 'gastos'],
    ]);

    $response->assertSuccessful();

    $configs = AutomationConfig::where('owner_id', $this->user->getOwnerId())->get();
    expect($configs)->toHaveCount(1);
    expect($configs->first()->channel)->toBe('whatsapp');
    expect($configs->first()->frequency)->toBe('weekly');
    expect($configs->first()->enabled)->toBeTrue();
});

test('store validates required fields', function () {
    $response = $this->actingAs($this->user)->post(route('automation.store'), [], [
        'Accept' => 'application/json',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['channel', 'frequency', 'execution_time']);
});

test('store validates channel enum', function () {
    $response = $this->actingAs($this->user)->post(route('automation.store'), [
        'channel' => 'invalid_channel',
        'frequency' => 'daily',
        'execution_time' => '08:00',
    ], ['Accept' => 'application/json']);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['channel']);
});

test('store validates frequency enum', function () {
    $response = $this->actingAs($this->user)->post(route('automation.store'), [
        'channel' => 'telegram',
        'frequency' => 'invalid_frequency',
        'execution_time' => '08:00',
    ], ['Accept' => 'application/json']);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['frequency']);
});

test('store validates execution time format', function () {
    $response = $this->actingAs($this->user)->post(route('automation.store'), [
        'channel' => 'telegram',
        'frequency' => 'daily',
        'execution_time' => '25:00',
    ], ['Accept' => 'application/json']);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['execution_time']);
});

test('store validates selected reports values', function () {
    $response = $this->actingAs($this->user)->post(route('automation.store'), [
        'channel' => 'telegram',
        'frequency' => 'daily',
        'execution_time' => '08:00',
        'selected_reports' => ['invalid_report'],
    ], ['Accept' => 'application/json']);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['selected_reports.0']);
});

test('run test returns error when no config exists', function () {
    $response = $this->actingAs($this->user)->post(route('automation.test'), [], [
        'Accept' => 'application/json',
    ]);

    $response->assertStatus(422);
    $response->assertJson([
        'success' => false,
        'message' => 'No hay configuración de automatización. Guarda la configuración primero.',
    ]);
});

test('run test returns error when no credentials exist', function () {
    AutomationConfig::create([
        'owner_id' => $this->user->getOwnerId(),
        'channel' => 'telegram',
        'frequency' => 'daily',
        'execution_time' => '08:00',
        'enabled' => true,
        'selected_reports' => ['ventas'],
    ]);

    $response = $this->actingAs($this->user)->post(route('automation.test'), [], [
        'Accept' => 'application/json',
    ]);

    $response->assertStatus(422);
    $response->assertJson([
        'success' => false,
        'message' => 'Conecta Telegram o WhatsApp primero en las credenciales.',
    ]);
});

test('run test returns error when no reports selected', function () {
    AutomationConfig::create([
        'owner_id' => $this->user->getOwnerId(),
        'channel' => 'telegram',
        'frequency' => 'daily',
        'execution_time' => '08:00',
        'enabled' => true,
        'selected_reports' => [],
    ]);

    ChannelCredential::create([
        'owner_id' => $this->user->getOwnerId(),
        'telegram_bot_token' => 'test:token',
        'telegram_bot_username' => 'test_bot',
    ]);

    $response = $this->actingAs($this->user)->post(route('automation.test'), [], [
        'Accept' => 'application/json',
    ]);

    $response->assertStatus(422);
    $response->assertJson([
        'success' => false,
        'message' => 'Selecciona al menos un reporte para ejecutar.',
    ]);
});

test('run test calls n8n webhook when configured', function () {
    AutomationConfig::create([
        'owner_id' => $this->user->getOwnerId(),
        'channel' => 'telegram',
        'frequency' => 'daily',
        'execution_time' => '08:00',
        'enabled' => true,
        'selected_reports' => ['ventas', 'inventario'],
    ]);

    ChannelCredential::create([
        'owner_id' => $this->user->getOwnerId(),
        'telegram_bot_token' => 'test:token',
        'telegram_bot_username' => 'test_bot',
    ]);

    config(['services.n8n.webhook_url' => 'https://n8n.example.com/webhook/test']);

    Http::fake([
        'n8n.example.com/*' => Http::response(['success' => true]),
    ]);

    $response = $this->actingAs($this->user)->post(route('automation.test'), [], [
        'Accept' => 'application/json',
    ]);

    $response->assertSuccessful();
    $response->assertJson(['success' => true]);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'n8n.example.com')
            && $request->data()['test_mode'] === true
            && $request->data()['channel'] === 'telegram';
    });

    $config = AutomationConfig::where('owner_id', $this->user->getOwnerId())->first();
    expect($config->last_run_status)->toBe('success');
    expect($config->last_run_at)->not->toBeNull();
});

test('run test marks status as error on n8n failure', function () {
    AutomationConfig::create([
        'owner_id' => $this->user->getOwnerId(),
        'channel' => 'telegram',
        'frequency' => 'daily',
        'execution_time' => '08:00',
        'enabled' => true,
        'selected_reports' => ['ventas'],
    ]);

    ChannelCredential::create([
        'owner_id' => $this->user->getOwnerId(),
        'telegram_bot_token' => 'test:token',
        'telegram_bot_username' => 'test_bot',
    ]);

    config(['services.n8n.webhook_url' => 'https://n8n.example.com/webhook/test']);

    Http::fake([
        'n8n.example.com/*' => Http::response(['error' => 'Internal error'], 500),
    ]);

    $response = $this->actingAs($this->user)->post(route('automation.test'), [], [
        'Accept' => 'application/json',
    ]);

    $response->assertStatus(500);
    $response->assertJson(['success' => false]);

    $config = AutomationConfig::where('owner_id', $this->user->getOwnerId())->first();
    expect($config->last_run_status)->toBe('error');
});

test('unauthenticated user cannot access automation routes', function () {
    $response = $this->post(route('automation.store'), [
        'channel' => 'telegram',
        'frequency' => 'daily',
        'execution_time' => '08:00',
    ]);

    $response->assertRedirect(route('login'));
});
