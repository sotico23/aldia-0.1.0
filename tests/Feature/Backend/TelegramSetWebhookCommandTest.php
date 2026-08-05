<?php

use App\Models\WebSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('command configures the global bot webhook', function () {
    WebSetting::query()->create([
        'global_telegram_bot_token' => '123456:ABC-DEF_global',
        'global_telegram_bot_username' => 'aldia_global_bot',
    ]);

    Http::fake([
        'api.telegram.org/bot*/setWebhook' => Http::response(['ok' => true, 'result' => true], 200),
    ]);

    $this->artisan('telegram:set-webhook', ['--bot' => 'global'])
        ->expectsOutputToContain('Webhook configurado correctamente')
        ->assertExitCode(0);

    Http::assertSentCount(1);
});

test('command reports error when token is rejected by Telegram', function () {
    WebSetting::query()->create([
        'global_telegram_bot_token' => '123456:ABC-DEF_global',
        'global_telegram_bot_username' => 'aldia_global_bot',
    ]);

    Http::fake([
        'api.telegram.org/bot*/setWebhook' => Http::response(['ok' => false, 'description' => 'Unauthorized'], 401),
    ]);

    $this->artisan('telegram:set-webhook', ['--bot' => 'global'])
        ->expectsOutputToContain('Error al configurar el webhook: Unauthorized')
        ->assertExitCode(1);
});

test('command --info prints current webhook info for global bot', function () {
    WebSetting::query()->create([
        'global_telegram_bot_token' => '123456:ABC-DEF_global',
        'global_telegram_bot_username' => 'aldia_global_bot',
    ]);

    Http::fake([
        'api.telegram.org/bot*/getWebhookInfo' => Http::response([
            'ok' => true,
            'result' => [
                'url' => 'https://example.com/webhooks/telegram',
                'pending_update_count' => 0,
                'last_error_message' => '',
            ],
        ], 200),
    ]);

    $this->artisan('telegram:set-webhook', ['--bot' => 'global', '--info' => true])
        ->expectsOutputToContain('Webhook URL: https://example.com/webhooks/telegram')
        ->assertExitCode(0);
});

test('command fails when global token is not configured', function () {
    WebSetting::query()->create([
        'global_telegram_bot_token' => null,
        'global_telegram_bot_username' => 'aldia_global_bot',
    ]);
    WebSetting::clearCache();
    config(['services.telegram.bot_token' => null]);

    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true], 200),
    ]);

    $this->artisan('telegram:set-webhook', ['--bot' => 'global'])
        ->expectsOutputToContain('No hay token de bot global configurado')
        ->assertExitCode(2);

    Http::assertNothingSent();
});
