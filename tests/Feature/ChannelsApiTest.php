<?php

use App\Models\ChannelCredential;
use App\Models\ChannelDataToken;
use App\Models\ChannelLink;
use App\Models\SystemIntegration;
use App\Models\User;
use App\Services\ChannelDataTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function createChannelApiGlobalKey(): void
{
    SystemIntegration::create([
        'provider' => 'n8n',
        'api_key' => 'channel-api-global-key',
        'is_active' => true,
    ]);
}

test('resolve returns all tenants linked to a shared whatsapp identity with tenant data tokens', function () {
    createChannelApiGlobalKey();

    $firstTenant = User::factory()->create(['business_name' => 'First company']);
    $secondTenant = User::factory()->create(['business_name' => 'Second company']);

    foreach ([$firstTenant, $secondTenant] as $tenant) {
        ChannelLink::create([
            'owner_id' => $tenant->getOwnerId(),
            'channel' => 'whatsapp',
            'bot_scope' => 'global',
            'external_id' => '56912345678',
            'linked_at' => now(),
        ]);
    }

    $response = $this->postJson('/api/v1/channels/resolve', [
        'channel' => 'whatsapp',
        'external_id' => '56912345678',
    ], [
        'X-API-Key' => 'channel-api-global-key',
    ])->assertSuccessful();

    $matches = $response->json('matches');

    expect($matches)->toHaveCount(2)
        ->and(collect($matches)->pluck('business_name')->all())->toBe(['First company', 'Second company'])
        ->and($matches[0]['data_token'])->not->toBe($matches[1]['data_token'])
        ->and(ChannelDataToken::count())->toBe(2);
});

test('resolve only accepts the configured global n8n key', function () {
    createChannelApiGlobalKey();
    User::factory()->create(['n8n_api_key' => 'tenant-key']);

    $this->postJson('/api/v1/channels/resolve', [
        'channel' => 'telegram',
        'external_id' => '123456',
    ], [
        'X-API-Key' => 'tenant-key',
    ])->assertUnauthorized();
});

test('reply validates the tenant data token and sends through the official telegram bot', function () {
    $tenant = User::factory()->create();
    $dataToken = app(ChannelDataTokenService::class)->tokenForOwner($tenant->getOwnerId());
    ChannelLink::create([
        'owner_id' => $tenant->getOwnerId(),
        'channel' => 'telegram',
        'bot_scope' => 'global',
        'external_id' => '987654321',
        'linked_at' => now(),
    ]);
    SystemIntegration::create([
        'provider' => 'telegram',
        'telegram_bot_token' => 'official-telegram-token',
        'is_active' => true,
    ]);

    Http::fake([
        'api.telegram.org/botofficial-telegram-token/sendMessage' => Http::response([
            'ok' => true,
            'result' => ['message_id' => 42],
        ]),
    ]);

    $this->postJson('/api/v1/channels/reply', [
        'channel' => 'telegram',
        'external_id' => '987654321',
        'bot_scope' => 'global',
        'message' => 'Hello',
    ], [
        'Authorization' => 'Bearer '.$dataToken,
    ])->assertSuccessful()
        ->assertJson([
            'success' => true,
            'channel' => 'telegram',
            'external_id' => '987654321',
            'message_id' => 42,
        ]);

    Http::assertSent(fn ($request): bool => str_contains(
        $request->url(),
        'botofficial-telegram-token/sendMessage'
    ));
});

test('reply uses the custom telegram bot for a tenant-scoped link', function () {
    $tenant = User::factory()->create();
    $ownerId = $tenant->getOwnerId();
    $dataToken = app(ChannelDataTokenService::class)->tokenForOwner($ownerId);

    ChannelCredential::create([
        'owner_id' => $ownerId,
        'telegram_mode' => 'custom',
        'telegram_bot_token' => 'tenant-telegram-token',
        'telegram_bot_username' => 'tenant_bot',
    ]);
    ChannelLink::create([
        'owner_id' => $ownerId,
        'channel' => 'telegram',
        'bot_scope' => 'tenant:'.$ownerId,
        'external_id' => '123456789',
        'linked_at' => now(),
    ]);
    Http::fake([
        'api.telegram.org/bottenant-telegram-token/sendMessage' => Http::response([
            'ok' => true,
            'result' => ['message_id' => 43],
        ]),
    ]);

    $this->postJson('/api/v1/channels/reply', [
        'channel' => 'telegram',
        'external_id' => '123456789',
        'bot_scope' => 'tenant:'.$ownerId,
        'message' => 'Hello from tenant bot',
    ], [
        'Authorization' => 'Bearer '.$dataToken,
    ])->assertSuccessful()
        ->assertJsonPath('message_id', 43);

    Http::assertSent(fn ($request): bool => str_contains(
        $request->url(),
        'bottenant-telegram-token/sendMessage'
    ));
});

test('reply cannot send to a contact not linked to the authenticated tenant', function () {
    $tenant = User::factory()->create();
    $dataToken = app(ChannelDataTokenService::class)->tokenForOwner($tenant->getOwnerId());

    Http::fake();

    $this->postJson('/api/v1/channels/reply', [
        'channel' => 'whatsapp',
        'external_id' => '56998765432',
        'bot_scope' => 'global',
        'message' => 'Not authorized',
    ], [
        'Authorization' => 'Bearer '.$dataToken,
    ])->assertForbidden();

    Http::assertNothingSent();
});

test('reply sends through the shared official whatsapp number', function () {
    $tenant = User::factory()->create();
    $dataToken = app(ChannelDataTokenService::class)->tokenForOwner($tenant->getOwnerId());
    ChannelLink::create([
        'owner_id' => $tenant->getOwnerId(),
        'channel' => 'whatsapp',
        'bot_scope' => 'global',
        'external_id' => '56912345678',
        'linked_at' => now(),
    ]);
    SystemIntegration::create([
        'provider' => 'whatsapp',
        'whatsapp_phone_number_id' => 'official-phone-id',
        'whatsapp_access_token' => 'official-whatsapp-token',
        'whatsapp_api_version' => 'v22.0',
        'is_active' => true,
    ]);

    Http::fake([
        'graph.facebook.com/v22.0/official-phone-id/messages' => Http::response([
            'messages' => [['id' => 'wamid.123']],
        ]),
    ]);

    $this->postJson('/api/v1/channels/reply', [
        'channel' => 'whatsapp',
        'external_id' => '56912345678',
        'bot_scope' => 'global',
        'message' => 'Hello from the platform',
    ], [
        'Authorization' => 'Bearer '.$dataToken,
    ])->assertSuccessful()
        ->assertJsonPath('message_id', 'wamid.123');

    Http::assertSent(fn ($request): bool => $request->hasHeader(
        'Authorization',
        'Bearer official-whatsapp-token'
    ));
});

test('channel data tokens are stored encrypted and indexed by a one-way hash', function () {
    $tenant = User::factory()->create();
    $token = app(ChannelDataTokenService::class)->tokenForOwner($tenant->getOwnerId());
    $record = ChannelDataToken::where('owner_id', $tenant->getOwnerId())->firstOrFail();
    $storedToken = DB::table('channel_data_tokens')->where('id', $record->id)->value('token');

    expect($record->token)->toBe($token)
        ->and($storedToken)->not->toBe($token)
        ->and($record->token_hash)->toBe(hash('sha256', $token));
});
