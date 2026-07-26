<?php

use App\Models\ChannelCredential;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('guest cannot access canales page', function () {
    $response = $this->get(route('channel-credentials.index'));

    $response->assertRedirect(route('login'));
});

test('index returns inertia page with credentials and automation', function () {
    ChannelCredential::create([
        'owner_id' => $this->user->getOwnerId(),
        'telegram_bot_token' => 'test:token',
        'telegram_bot_username' => 'test_bot',
        'whatsapp_phone_number_id' => '123456',
        'whatsapp_access_token' => 'test_token',
        'whatsapp_business_id' => 'bus_123',
        'whatsapp_api_version' => 'v22.0',
    ]);

    $response = $this->actingAs($this->user)->get(route('channel-credentials.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Backend/ChannelCredentials')
        ->has('credentials')
        ->where('credentials.telegram_bot_username', 'test_bot')
        ->where('has_credentials', true)
    );
});

test('index does not expose tokens in response', function () {
    ChannelCredential::create([
        'owner_id' => $this->user->getOwnerId(),
        'telegram_bot_token' => 'secret_token_123',
        'whatsapp_access_token' => 'secret_whatsapp_456',
    ]);

    $response = $this->actingAs($this->user)->get(route('channel-credentials.index'));

    $response->assertInertia(fn ($page) => $page
        ->where('credentials.telegram_bot_token', '••••••••••••••••')
        ->where('credentials.whatsapp_access_token', '••••••••••••••••')
    );
});

test('index returns has_credentials false when no credentials', function () {
    $response = $this->actingAs($this->user)->get(route('channel-credentials.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->where('has_credentials', false)
        ->where('credentials', null)
    );
});

test('update stores new credentials', function () {
    $response = $this->actingAs($this->user)->put(route('channel-credentials.update'), [
        'telegram_bot_token' => 'new_telegram_token',
        'telegram_bot_username' => 'new_bot',
        'whatsapp_phone_number_id' => '987654',
        'whatsapp_access_token' => 'new_whatsapp_token',
        'whatsapp_business_id' => 'bus_999',
        'whatsapp_api_version' => 'v22.0',
    ]);

    $response->assertRedirect(route('channel-credentials.index'));

    $this->assertDatabaseHas('channel_credentials', [
        'owner_id' => $this->user->getOwnerId(),
        'telegram_bot_username' => 'new_bot',
        'whatsapp_phone_number_id' => '987654',
        'whatsapp_business_id' => 'bus_999',
        'whatsapp_api_version' => 'v22.0',
    ]);

    $credential = ChannelCredential::where('owner_id', $this->user->getOwnerId())->first();
    expect($credential->telegram_bot_token)->toBe('new_telegram_token');
    expect($credential->whatsapp_access_token)->toBe('new_whatsapp_token');
});

test('update ignores masked token placeholders', function () {
    ChannelCredential::create([
        'owner_id' => $this->user->getOwnerId(),
        'telegram_bot_token' => 'existing_token',
        'whatsapp_access_token' => 'whatsapp_existing',
    ]);

    $response = $this->actingAs($this->user)->put(route('channel-credentials.update'), [
        'telegram_bot_token' => '••••••••••••••••',
        'whatsapp_access_token' => '••••••••••••••••',
    ]);

    $response->assertRedirect(route('channel-credentials.index'));

    $credential = ChannelCredential::where('owner_id', $this->user->getOwnerId())->first();
    expect($credential->telegram_bot_token)->toBe('existing_token');
    expect($credential->whatsapp_access_token)->toBe('whatsapp_existing');
});

test('update validates required fields types', function () {
    $response = $this->actingAs($this->user)->put(route('channel-credentials.update'), [
        'telegram_bot_token' => str_repeat('a', 300),
    ], ['Accept' => 'application/json']);

    $response->assertStatus(422);
});

test('test-telegram returns error when no token configured', function () {
    $response = $this->actingAs($this->user)->post(route('channel-credentials.test-telegram'), [], [
        'Accept' => 'application/json',
    ]);

    $response->assertJson([
        'success' => false,
        'message' => 'No hay un Token de Bot de Telegram configurado. Ingresa un token o guárdalo primero.',
    ]);
});

test('test-whatsapp returns error when no credentials configured', function () {
    $response = $this->actingAs($this->user)->post(route('channel-credentials.test-whatsapp'), [], [
        'Accept' => 'application/json',
    ]);

    $response->assertJson([
        'success' => false,
        'message' => 'Credenciales de WhatsApp no configuradas.',
    ]);
});

test('test-telemark and test-whatsapp rate limited', function () {
    for ($i = 0; $i < 10; $i++) {
        $this->actingAs($this->user)->post(route('channel-credentials.test-telegram'), [], [
            'Accept' => 'application/json',
        ]);
    }

    $response = $this->actingAs($this->user)->post(route('channel-credentials.test-telegram'), [], [
        'Accept' => 'application/json',
    ]);

    $response->assertStatus(429);
});
