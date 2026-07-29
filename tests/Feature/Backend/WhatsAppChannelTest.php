<?php

use App\Models\ChannelCredential;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('send-whatsapp-test-message returns error when no phone_number_id saved', function () {
    $response = $this->actingAs($this->user)->postJson(route('channel-credentials.send-whatsapp-test-message'));

    $response->assertJson([
        'success' => false,
        'message' => 'No hay un Phone Number ID de WhatsApp configurado.',
    ]);
});

test('send-whatsapp-test-message returns error when no destination number provided', function () {
    ChannelCredential::create([
        'owner_id' => $this->user->getOwnerId(),
        'whatsapp_access_token' => 'test_token',
        'whatsapp_phone_number_id' => '1234567890',
    ]);

    Http::fake([
        'graph.facebook.com*' => Http::response(['ok' => true], 200),
    ]);

    $response = $this->actingAs($this->user)->postJson(route('channel-credentials.send-whatsapp-test-message'));

    $response->assertJson([
        'success' => false,
        'message' => 'Debes proporcionar un número de WhatsApp destino.',
    ]);
});

test('send-whatsapp-test-message sends message successfully', function () {
    ChannelCredential::create([
        'owner_id' => $this->user->getOwnerId(),
        'whatsapp_access_token' => 'test_token',
        'whatsapp_phone_number_id' => '1234567890',
    ]);

    Http::fake([
        'graph.facebook.com/v22.0/1234567890/messages' => Http::response([
            'messages' => [['id' => 'wamid.test123']],
        ], 200),
    ]);

    $response = $this->actingAs($this->user)->postJson(
        route('channel-credentials.send-whatsapp-test-message'),
        ['whatsapp_to' => '+34600123456']
    );

    $response->assertJson(['success' => true]);
});

test('send-whatsapp-test-message uses stored credentials when no phoneNumberId in body', function () {
    ChannelCredential::create([
        'owner_id' => $this->user->getOwnerId(),
        'whatsapp_access_token' => 'test_token',
        'whatsapp_phone_number_id' => '9876543210',
    ]);

    Http::fake([
        'graph.facebook.com/v22.0/9876543210/messages' => Http::response([
            'messages' => [['id' => 'wamid.stored456']],
        ], 200),
    ]);

    $response = $this->actingAs($this->user)->postJson(
        route('channel-credentials.send-whatsapp-test-message'),
        ['whatsapp_to' => '+34600987654']
    );

    $response->assertJson(['success' => true]);
});

test('test-whatsapp returns error when no credentials configured', function () {
    $response = $this->actingAs($this->user)->postJson(route('channel-credentials.test-whatsapp'));

    $response->assertJson([
        'success' => false,
        'message' => 'Credenciales de WhatsApp no configuradas.',
    ]);
});

test('test-whatsapp rate limited', function () {
    for ($i = 0; $i < 10; $i++) {
        $this->actingAs($this->user)->post(route('channel-credentials.test-whatsapp'), [], [
            'Accept' => 'application/json',
        ]);
    }

    $response = $this->actingAs($this->user)->post(route('channel-credentials.test-whatsapp'), [], [
        'Accept' => 'application/json',
    ]);

    $response->assertStatus(429);
});
