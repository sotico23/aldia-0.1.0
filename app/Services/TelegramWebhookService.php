<?php

namespace App\Services;

use App\Models\ChannelCredential;
use App\Models\SystemIntegration;
use App\Models\TelegramLinkingToken;
use App\Models\WebSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramWebhookService
{
    public function processWebhook(array $update): array
    {
        try {
            return $this->processUpdate($update);
        } catch (\Throwable $e) {
            Log::error('TelegramWebhookService: unexpected error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ['status' => 'ok'];
        }
    }

    private function processUpdate(array $update): array
    {
        $update = $this->unwrapUpdate($update);

        $message = $update['message']
            ?? $update['edited_message']
            ?? $update['callback_query']
            ?? $update['channel_post']
            ?? $update['edited_channel_post']
            ?? $update['my_chat_member']
            ?? $update['chat_join_to_channel']
            ?? $update['chat_join_request']
            ?? ($update['body']['message'] ?? $update['data']['message'] ?? null);

        if (! $message) {
            return ['status' => 'ok'];
        }

        $chatId = $this->extractChatId($message, $update);
        $text = trim($this->extractText($message));

        if (! $chatId) {
            return ['status' => 'ok'];
        }

        $chatIdStr = (string) $chatId;
        $token = $this->extractStartToken($text);

        if ($token) {
            return $this->linkAccount($token, $chatIdStr);
        }

        if ($text === '/start') {
            $credential = ChannelCredential::where('telegram_chat_id', $chatIdStr)->first();

            $botToken = $credential?->telegram_bot_token
                ?: (WebSetting::getSettings()?->global_telegram_bot_token
                ?: config('services.telegram.bot_token'));

            if ($botToken) {
                $this->sendTelegramMessage($botToken, $chatIdStr, "👋 ¡Hola! Para vincular tu cuenta con Al Día, por favor ingresa a la plataforma web y haz clic en *'Abrir Chat en Telegram'* o copia tu token e ingrésalo aquí escribiendo:\n\n`/start TU_TOKEN`");
            }

            Log::info('TelegramWebhookService: /start without token intercepted, not forwarded to n8n', [
                'chat_id' => $chatIdStr,
            ]);

            return ['status' => 'missing_token'];
        }

        $credential = ChannelCredential::where('telegram_chat_id', $chatIdStr)->first();
        $isLinked = false;
        $tenantId = null;

        if ($credential && $credential->owner_id) {
            $isLinked = true;
            $tenantId = $credential->owner_id;
        }

        if ($isLinked && config('services.llm.enabled')) {
            $botToken = $credential->telegram_bot_token
                ?: (WebSetting::getSettings()?->global_telegram_bot_token
                ?: config('services.telegram.bot_token'));

            if ($botToken) {
                app(TelegramAssistantService::class)->handleMessage($tenantId, $botToken, $chatIdStr, $text);

                Log::info('TelegramWebhookService: message handled by Laravel assistant', [
                    'chat_id' => $chatIdStr,
                    'tenant_id' => $tenantId,
                ]);

                return ['status' => 'ok'];
            }
        }

        $payload = $this->buildPayload($isLinked, $tenantId, $credential, $chatIdStr, $text, $update);

        Log::info('TelegramWebhookService: forwarding message to n8n as proxy', [
            'chat_id' => $chatIdStr,
            'is_linked' => $isLinked,
        ]);

        $this->forwardToN8n($payload);

        return ['status' => 'ok'];
    }

    /**
     * Normalize the incoming update payload.
     *
     * Handles direct Telegram updates as well as wrappers produced by the n8n
     * proxy and the case where the body/data is delivered as a JSON-encoded
     * string (which would otherwise break array access).
     */
    private function unwrapUpdate(array $update): array
    {
        $update = $update['body'] ?? $update['data'] ?? $update;

        if (is_string($update)) {
            $decoded = json_decode($update, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return $update;
    }

    /**
     * Extract the chat id from a Telegram update message.
     *
     * Supports regular messages, edited messages and callback queries where the
     * chat object is nested under message.message.chat. Falls back to the
     * triggering user id (from.id / chat.id) for membership updates.
     */
    private function extractChatId(array $message, array $update): mixed
    {
        if (isset($message['chat']['id'])) {
            return $message['chat']['id'];
        }

        if (isset($message['message']['chat']['id'])) {
            return $message['message']['chat']['id'];
        }

        if (isset($update['chat']['id'])) {
            return $update['chat']['id'];
        }

        $fromId = $message['from']['id']
            ?? $message['message']['from']['id']
            ?? $update['my_chat_member']['from']['id']
            ?? null;

        if ($fromId !== null) {
            return $fromId;
        }

        return null;
    }

    /**
     * Extract the text payload of a message, supporting callback_query data.
     */
    private function extractText(array $message): string
    {
        return $message['text']
            ?? $message['data']
            ?? $message['message']['text']
            ?? '';
    }

    private function linkAccount(string $token, string $chatId): array
    {
        $linkingToken = TelegramLinkingToken::where('token', $token)->first();

        // Idempotent re-delivery: Telegram may forward the same /start payload
        // multiple times. If the token is already used but with the SAME chat_id,
        // treat it as a successful re-link rather than an invalid token.
        if ($linkingToken && $linkingToken->isUsed() && $linkingToken->telegram_chat_id === $chatId) {
            Log::info('TelegramWebhookService: start token re-delivered for already-linked chat, treated as linked', [
                'token' => $token,
                'chat_id' => $chatId,
            ]);

            return ['status' => 'linked'];
        }

        if (! $linkingToken || ! $linkingToken->owner_id || $linkingToken->isUsed() || $linkingToken->isExpired()) {
            $botToken = WebSetting::getSettings()?->global_telegram_bot_token
                ?: config('services.telegram.bot_token');

            if ($botToken) {
                $this->sendTelegramMessage($botToken, $chatId, '❌ El token de vinculación es inválido o ha expirado. Por favor, genera un nuevo enlace desde la plataforma.');
            }

            return ['status' => 'invalid_token'];
        }

        $tenantId = $linkingToken->owner_id;
        $botType = $linkingToken->bot_type ?? 'custom';

        DB::transaction(function () use ($linkingToken, $chatId, $tenantId, $botType) {
            $linkingToken->update([
                'telegram_chat_id' => $chatId,
                'used_at' => now(),
            ]);

            $credential = ChannelCredential::firstOrCreate(['owner_id' => $tenantId]);
            $credential->update([
                'telegram_chat_id' => $chatId,
                'telegram_linked_at' => now(),
                'bot_type' => $botType,
            ]);
        });

        Log::info('Telegram account linked via start token', [
            'tenant_id' => $tenantId,
            'token' => $token,
            'chat_id' => $chatId,
        ]);

        $credential = ChannelCredential::where('owner_id', $tenantId)->first();
        $botToken = $credential?->telegram_bot_token
            ?: WebSetting::getSettings()?->global_telegram_bot_token;

        if ($botToken) {
            $this->sendTelegramMessage($botToken, $chatId, '🎉 ¡Cuenta vinculada exitosamente! Ya puedes interactuar con tu asistente de Al Día.');
        }

        return ['status' => 'linked'];
    }

    private function extractStartToken(string $text): ?string
    {
        if (preg_match('/^\/start(?:@[A-Za-z0-9_]+)?\s+([^\s]+)/i', $text, $matches)) {
            return trim($matches[1], ".,;:!?\"'") ?: null;
        }

        return null;
    }

    private function buildPayload(
        bool $isLinked,
        ?int $tenantId,
        ?ChannelCredential $credential,
        string $chatId,
        string $text,
        array $update
    ): array {
        $webSettings = WebSetting::getSettings();

        if (! $credential && $tenantId) {
            $credential = ChannelCredential::where('owner_id', $tenantId)->first();
        }

        $botToken = $credential?->telegram_bot_token
            ?: ($webSettings?->global_telegram_bot_token
            ?: config('services.telegram.bot_token'));

        $botUsername = $credential?->telegram_bot_username
            ?: ($webSettings?->global_telegram_bot_username
            ?: config('services.telegram.bot_username'));

        $botType = $credential?->bot_type ?? 'oficial';

        $linkingToken = null;
        if (! $isLinked && $tenantId) {
            $linkingToken = TelegramLinkingToken::generateToken();
            TelegramLinkingToken::create([
                'owner_id' => $tenantId,
                'token' => $linkingToken,
                'expires_at' => now()->addMinutes(15),
                'bot_type' => $botType === 'personal' ? 'custom' : 'global',
            ]);
        }

        $botCleanUsername = ltrim($botUsername ?? '', '@');
        $linkingUrl = $botCleanUsername && $linkingToken
            ? "https://t.me/{$botCleanUsername}?start={$linkingToken}"
            : config('app.url').'/canales';

        return [
            'event' => $isLinked ? 'message' : 'send_linking_code',
            'tenant_id' => $isLinked ? ($credential?->owner_id ?? $tenantId) : null,
            'chat_id' => $chatId,
            'bot_token' => $botToken,
            'bot_username' => $botUsername,
            'user_message' => $text ?: 'Inicialización de canal',
            'is_linked' => $isLinked,
            'linking_url' => $linkingUrl,
            'bot_type' => $botType === 'custom' ? 'personal' : 'oficial',
            'callback_url' => route('api.canales.telegram.webhook'),
            'webhook_url' => route('api.canales.telegram.webhook'),
            'raw_update' => $update,
        ];
    }

    private function forwardToN8n(array $payload): void
    {
        $n8nIntegration = SystemIntegration::forProvider('n8n')->first();
        $webhookUrl = $n8nIntegration?->telegram_proxy_url
            ?: ($n8nIntegration?->webhook_url
            ?: (config('services.n8n.telegram_proxy_url')
            ?: config('services.n8n.webhook_url')));

        if (! $webhookUrl) {
            Log::warning('TelegramWebhookService: n8n webhook URL not configured');

            return;
        }

        try {
            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->withOptions(['verify' => false])
                ->post($webhookUrl, $payload);

            if (! $response->successful()) {
                $status = $response->status();
                $hint = '';
                if ($status === 404) {
                    $hint = ' n8n: activate the "telegram-proxy" webhook workflow in n8n (production URL must be registered).';
                }

                Log::warning('TelegramWebhookService: n8n forwarding failed'.$hint, [
                    'chat_id' => $payload['chat_id'],
                    'status' => $status,
                    'body' => $response->body(),
                ]);
            } else {
                Log::info('TelegramWebhookService: forwarded update to n8n', [
                    'chat_id' => $payload['chat_id'],
                    'is_linked' => $payload['is_linked'],
                ]);
            }
        } catch (\Exception $e) {
            Log::error('TelegramWebhookService: n8n forwarding error', [
                'chat_id' => $payload['chat_id'],
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendTelegramMessage(string $botToken, string $chatId, string $text): void
    {
        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";

        try {
            Http::timeout(10)
                ->connectTimeout(5)
                ->withOptions(['verify' => false])
                ->post($url, [
                    'chat_id' => $chatId,
                    'text' => $text,
                    'parse_mode' => 'Markdown',
                ]);
        } catch (\Exception $e) {
            Log::error('TelegramWebhookService: error sending Telegram message', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
