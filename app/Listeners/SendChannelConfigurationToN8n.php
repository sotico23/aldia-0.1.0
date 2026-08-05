<?php

namespace App\Listeners;

use App\Events\ChannelConfigurationUpdated;
use App\Models\ChannelCredential;
use App\Models\SystemIntegration;
use App\Models\TelegramLinkingToken;
use App\Models\WebSetting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendChannelConfigurationToN8n implements ShouldQueue
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(ChannelConfigurationUpdated $event): void
    {
        $ownerId = $event->ownerId;
        $userId = $event->userId;
        $botTypeEvent = $event->botType; // 'global' or 'custom'

        $credentials = ChannelCredential::where('owner_id', $ownerId)->first();
        $webSettings = WebSetting::getSettings();

        // Determine if official (global) or personal (custom)
        $botType = 'oficial';
        $botToken = null;
        $botUsername = null;

        if ($botTypeEvent === 'global') {
            $botType = 'oficial';
            $botToken = $webSettings->global_telegram_bot_token ?? null;
            $botUsername = $webSettings->global_telegram_bot_username ?? null;
        } else {
            // botTypeEvent is 'custom'
            $effectiveBotType = $credentials?->bot_type ?? 'global';
            if ($effectiveBotType === 'global') {
                $botType = 'oficial';
                $botToken = $webSettings->global_telegram_bot_token ?? null;
                $botUsername = $webSettings->global_telegram_bot_username ?? null;
            } else {
                $botType = 'personal';
                $botToken = $credentials->telegram_bot_token ?? null;
                $botUsername = $credentials->telegram_bot_username ?? null;
            }
        }

        // If there's no bot token configured, we can't send a valid payload to n8n
        if (! $botToken) {
            Log::warning('SendChannelConfigurationToN8n: Telegram bot token not configured.', [
                'owner_id' => $ownerId,
                'bot_type' => $botType,
            ]);

            return;
        }

        $chatId = $credentials->telegram_chat_id ?? null;
        $isLinked = ! empty($chatId);

        // Generate linking URL
        $linkingUrl = config('app.url').'/canales';
        if ($botUsername) {
            $botUsernameClean = ltrim($botUsername, '@');

            // Try to find a valid linking token, or generate a new one
            $tokenModel = TelegramLinkingToken::whereNull('used_at')
                ->where('expires_at', '>', now())
                ->where('owner_id', $ownerId)
                ->where('bot_type', $botType === 'oficial' ? 'global' : 'custom')
                ->latest()
                ->first();

            if (! $tokenModel) {
                $token = TelegramLinkingToken::generateToken();
                $expiresAt = now()->addMinutes(15);
                TelegramLinkingToken::create([
                    'owner_id' => $ownerId,
                    'user_id' => $userId,
                    'token' => $token,
                    'bot_type' => $botType === 'oficial' ? 'global' : 'custom',
                    'expires_at' => $expiresAt,
                ]);
            } else {
                $token = $tokenModel->token;
            }

            $linkingUrl = "https://t.me/{$botUsernameClean}?start={$token}";
        }

        $payload = [
            'event' => 'send_linking_code',
            'tenant_id' => $ownerId,
            'chat_id' => $chatId ? (string) $chatId : null,
            'bot_token' => $botToken,
            'bot_username' => $botUsername,
            'user_message' => 'Inicialización de canal',
            'is_linked' => $isLinked,
            'linking_url' => $linkingUrl,
            'bot_type' => $botType,
        ];

        // Resolve webhook URL: tenant-specific URL takes priority, then the
        // global n8n integration values, then env config.
        $n8nIntegration = SystemIntegration::forProvider('n8n')->first();
        $webhookUrl = $credentials->n8n_telegram_proxy_webhook_url
            ?: $n8nIntegration?->telegram_proxy_url
            ?: config('services.n8n.telegram_proxy_url');

        if (! $webhookUrl) {
            Log::warning('SendChannelConfigurationToN8n: telegram_proxy_url is not configured.');

            return;
        }

        try {
            $response = Http::timeout(30)
                ->connectTimeout(10)
                ->withOptions(['verify' => false])
                ->post($webhookUrl, $payload);

            if (! $response->successful()) {
                Log::warning('SendChannelConfigurationToN8n: n8n returned error status.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('SendChannelConfigurationToN8n: failed to send webhook.', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
