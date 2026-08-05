<?php

use App\Events\ChannelConfigurationUpdated;
use App\Listeners\SendChannelConfigurationToN8n;
use App\Models\ChannelCredential;
use App\Models\SystemIntegration;
use App\Models\User;
use App\Models\WebSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'admin.web-settings.viewAny', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'admin.web-settings.edit', 'guard_name' => 'web']);

    $this->user = User::factory()->create();
    $this->user->givePermissionTo('admin.web-settings.viewAny');
    $this->user->givePermissionTo('admin.web-settings.edit');

    config(['services.n8n.telegram_proxy_url' => 'https://n8n.redcliente.cl/webhook/telegram-proxy']);
});

test('updating channel credentials dispatches ChannelConfigurationUpdated event', function () {
    Event::fake();

    $response = $this->actingAs($this->user)->put(route('channel-credentials.update'), [
        'telegram_bot_token' => 'new_telegram_token',
        'telegram_bot_username' => 'new_bot',
        'bot_type' => 'custom',
    ]);

    $response->assertRedirect();

    Event::assertDispatched(ChannelConfigurationUpdated::class, function ($event) {
        return $event->ownerId === $this->user->getOwnerId()
            && $event->userId === $this->user->id
            && $event->botType === 'custom';
    });
});

test('updating global web settings dispatches ChannelConfigurationUpdated event', function () {
    Event::fake();

    $config = WebSetting::firstOrCreate([]);

    $response = $this->actingAs($this->user)->put(route('configuracion-web.update', $config), [
        'app_name' => 'AlDia Test',
        'app_title' => 'Title',
        'timezone' => 'America/Santiago',
        'locale' => 'es',
        'currency' => 'CLP',
        'currency_symbol' => '$',
        'global_telegram_bot_token' => 'global_token_123',
        'global_telegram_bot_username' => 'global_bot',
    ]);

    $response->assertRedirect();

    Event::assertDispatched(ChannelConfigurationUpdated::class, function ($event) {
        return $event->ownerId === $this->user->getOwnerId()
            && $event->userId === $this->user->id
            && $event->botType === 'global';
    });
});

test('listener SendChannelConfigurationToN8n sends correct payload to n8n proxy', function () {
    Http::fake([
        'https://n8n.redcliente.cl/*' => Http::response(['status' => 'ok'], 200),
    ]);

    // Create channel credential with token
    $credentials = ChannelCredential::create([
        'owner_id' => $this->user->getOwnerId(),
        'telegram_bot_token' => 'my_personal_token',
        'telegram_bot_username' => 'my_personal_bot',
        'telegram_chat_id' => '123456789',
        'bot_type' => 'custom',
    ]);

    // Trigger event
    $event = new ChannelConfigurationUpdated(
        $this->user->getOwnerId(),
        $this->user->id,
        'custom'
    );

    $listener = new SendChannelConfigurationToN8n;
    $listener->handle($event);

    Http::assertSent(function ($request) {
        $data = $request->data();

        return $request->url() === 'https://n8n.redcliente.cl/webhook/telegram-proxy'
            && $data['event'] === 'send_linking_code'
            && $data['tenant_id'] === $this->user->getOwnerId()
            && $data['chat_id'] === '123456789'
            && $data['bot_token'] === 'my_personal_token'
            && $data['bot_username'] === 'my_personal_bot'
            && $data['is_linked'] === true
            && $data['bot_type'] === 'personal'
            && preg_match('/my_personal_bot\?start=[A-Za-z0-9_]{1,64}$/', $data['linking_url']) === 1;
    });
});

test('listener sends /canales fallback linking_url when no bot username configured', function () {
    Http::fake([
        'https://n8n.redcliente.cl/*' => Http::response(['status' => 'ok'], 200),
    ]);

    ChannelCredential::create([
        'owner_id' => $this->user->getOwnerId(),
        'telegram_bot_token' => 'my_personal_token',
        'bot_type' => 'custom',
    ]);

    $event = new ChannelConfigurationUpdated(
        $this->user->getOwnerId(),
        $this->user->id,
        'custom'
    );

    $listener = new SendChannelConfigurationToN8n;
    $listener->handle($event);

    Http::assertSent(function ($request) {
        $data = $request->data();

        return $request->url() === 'https://n8n.redcliente.cl/webhook/telegram-proxy'
            && $data['event'] === 'send_linking_code'
            && $data['linking_url'] === config('app.url').'/canales'
            && $data['linking_url'] !== '';
    });
});

test('listener uses telegram_proxy_url from SystemIntegration DB over env config', function () {
    Http::fake([
        'https://db-proxy.example.com/*' => Http::response(['status' => 'ok'], 200),
    ]);

    // Store the URL in the DB (SystemIntegration)
    SystemIntegration::create([
        'provider' => 'n8n',
        'base_url' => 'https://n8n.example.com',
        'telegram_proxy_url' => 'https://db-proxy.example.com/webhook/telegram-proxy',
        'is_active' => true,
    ]);

    // Set a different URL in config — should NOT be used
    config(['services.n8n.telegram_proxy_url' => 'https://env-proxy.example.com/webhook/telegram-proxy']);

    ChannelCredential::create([
        'owner_id' => $this->user->getOwnerId(),
        'telegram_bot_token' => 'test_token',
        'telegram_bot_username' => 'test_bot',
        'bot_type' => 'custom',
    ]);

    $event = new ChannelConfigurationUpdated(
        $this->user->getOwnerId(),
        $this->user->id,
        'custom'
    );

    $listener = new SendChannelConfigurationToN8n;
    $listener->handle($event);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'db-proxy.example.com');
    });
});

test('system integration API saves and returns telegram_proxy_url', function () {
    $response = $this->actingAs($this->user)->put('/api/system-integrations/n8n', [
        'base_url' => 'https://n8n.example.com',
        'webhook_url' => 'https://n8n.example.com/webhook/test',
        'telegram_proxy_url' => 'https://n8n.example.com/webhook/telegram-proxy',
        'api_key' => 'test_key_123',
        'is_active' => true,
    ]);

    $response->assertOk();
    $response->assertJsonPath('data.telegram_proxy_url', 'https://n8n.example.com/webhook/telegram-proxy');

    $this->assertDatabaseHas('system_integrations', [
        'provider' => 'n8n',
        'telegram_proxy_url' => 'https://n8n.example.com/webhook/telegram-proxy',
    ]);
});

test('test connection API sends POST request to telegram_proxy_url', function () {
    Http::fake([
        'https://n8n.example.com/webhook/telegram-proxy' => Http::response(['status' => 'ok'], 200),
    ]);

    SystemIntegration::create([
        'provider' => 'n8n',
        'base_url' => 'https://n8n.example.com',
        'telegram_proxy_url' => 'https://n8n.example.com/webhook/telegram-proxy',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)->post('/api/system-integrations/n8n/test');

    $response->assertOk();
    $response->assertJsonPath('success', true);

    Http::assertSent(function ($request) {
        return $request->method() === 'POST'
            && $request->url() === 'https://n8n.example.com/webhook/telegram-proxy'
            && $request['event'] === 'test_connection';
    });
});
