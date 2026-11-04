<?php

use App\Models\ChannelCredential;
use App\Models\ChannelLink;
use App\Models\SystemIntegration;
use App\Models\User;
use App\Models\WebSetting;
use App\Services\ChannelConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('global telegram falls back to legacy web settings', function () {
    WebSetting::factory()->create([
        'global_telegram_bot_username' => 'legacy_bot',
        'global_telegram_bot_token' => 'legacy-token',
    ]);

    $config = app(ChannelConfigService::class)->globalTelegram();

    expect($config['bot_username'])->toBe('legacy_bot')
        ->and($config['bot_token'])->toBe('legacy-token')
        ->and($config['is_active'])->toBeTrue();
});

test('global telegram prefers the canonical system integration', function () {
    WebSetting::factory()->create([
        'global_telegram_bot_username' => 'legacy_bot',
        'global_telegram_bot_token' => 'legacy-token',
    ]);

    SystemIntegration::create([
        'provider' => 'telegram',
        'telegram_bot_username' => 'canonical_bot',
        'telegram_bot_token' => 'canonical-token',
        'is_active' => true,
    ]);

    $config = app(ChannelConfigService::class)->globalTelegram();

    expect($config['bot_username'])->toBe('canonical_bot')
        ->and($config['bot_token'])->toBe('canonical-token');
});

test('tenant uses the global telegram channel when mode is global', function () {
    $tenant = User::factory()->create();

    ChannelCredential::create([
        'owner_id' => $tenant->getOwnerId(),
        'telegram_bot_username' => 'tenant_bot',
        'telegram_bot_token' => 'tenant-token',
        'telegram_mode' => ChannelConfigService::MODE_GLOBAL,
    ]);

    SystemIntegration::create([
        'provider' => 'telegram',
        'telegram_bot_username' => 'global_bot',
        'telegram_bot_token' => 'global-token',
        'is_active' => true,
    ]);

    $config = app(ChannelConfigService::class)->tenantTelegram((int) $tenant->getOwnerId());

    expect($config['mode'])->toBe(ChannelConfigService::MODE_GLOBAL)
        ->and($config['bot_username'])->toBe('global_bot')
        ->and($config['bot_token'])->toBe('global-token');
});

test('tenant custom telegram config is resolved by webhook key', function () {
    $tenant = User::factory()->create();

    ChannelCredential::create([
        'owner_id' => $tenant->getOwnerId(),
        'telegram_bot_username' => 'tenant_bot',
        'telegram_bot_token' => 'tenant-token',
        'telegram_mode' => ChannelConfigService::MODE_CUSTOM,
        'telegram_webhook_key' => 'tenant-webhook-key',
    ]);

    $config = app(ChannelConfigService::class)
        ->telegramConfigByWebhookKey('tenant-webhook-key');

    expect($config)->not->toBeNull()
        ->and($config['mode'])->toBe(ChannelConfigService::MODE_CUSTOM)
        ->and($config['bot_username'])->toBe('tenant_bot')
        ->and($config['bot_token'])->toBe('tenant-token');
});

test('active channel links are resolved by channel scope and external id', function () {
    $tenant = User::factory()->create();

    ChannelLink::create([
        'owner_id' => $tenant->getOwnerId(),
        'channel' => ChannelConfigService::CHANNEL_TELEGRAM,
        'bot_scope' => 'tenant:'.$tenant->getOwnerId(),
        'external_id' => '999999',
        'linked_at' => now(),
    ]);

    $link = app(ChannelConfigService::class)
        ->activeTelegramLink('tenant:'.$tenant->getOwnerId(), '999999');

    expect($link)->not->toBeNull()
        ->and($link['owner_id'])->toBe($tenant->getOwnerId());
});

test('consolidate command is idempotent and backfills modes keys and links', function () {
    $tenant = User::factory()->create();

    WebSetting::factory()->create([
        'global_telegram_bot_username' => 'legacy_bot',
        'global_telegram_bot_token' => 'legacy-token',
    ]);

    $credential = ChannelCredential::create([
        'owner_id' => $tenant->getOwnerId(),
        'telegram_bot_token' => 'tenant-token',
        'telegram_bot_username' => 'tenant_bot',
        'telegram_chat_id' => '123456',
        'bot_type' => 'custom',
        'whatsapp_phone_number_id' => 'phone-id',
        'whatsapp_access_token' => 'wa-token',
        'whatsapp_wa_id' => '51999999999',
    ]);

    $this->artisan('channels:consolidate-credentials', ['--dry-run' => true])
        ->assertSuccessful();

    expect($credential->fresh()->telegram_mode)->toBe(ChannelConfigService::MODE_GLOBAL)
        ->and(ChannelLink::count())->toBe(0)
        ->and(SystemIntegration::forProvider('telegram')->exists())->toBeFalse();

    $this->artisan('channels:consolidate-credentials')
        ->assertSuccessful();

    $credential->refresh();

    expect($credential->telegram_mode)->toBe(ChannelConfigService::MODE_CUSTOM)
        ->and($credential->telegram_webhook_key)->not->toBeEmpty()
        ->and($credential->whatsapp_mode)->toBe(ChannelConfigService::MODE_CUSTOM)
        ->and($credential->whatsapp_webhook_key)->not->toBeEmpty();

    expect(ChannelLink::where('channel', 'telegram')->where('external_id', '123456')->exists())->toBeTrue()
        ->and(ChannelLink::where('channel', 'whatsapp')->where('external_id', '51999999999')->exists())->toBeTrue();

    $telegram = SystemIntegration::forProvider('telegram')->first();

    expect($telegram)->not->toBeNull()
        ->and($telegram->telegram_bot_username)->toBe('legacy_bot')
        ->and($telegram->telegram_bot_token)->toBe('legacy-token');

    $this->artisan('channels:consolidate-credentials')
        ->assertSuccessful();

    expect(ChannelLink::count())->toBe(2);
});
