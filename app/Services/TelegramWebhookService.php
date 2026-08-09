<?php

namespace App\Services;

use App\Models\ChannelCredential;
use App\Models\SystemIntegration;
use App\Models\TelegramLinkingToken;
use App\Models\WebSetting;
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
            ?? null;

        // Accept flat n8n-relayed payloads (chat_id + text/token) that arrive
        // without a Telegram "message" object.
        $chatId = $this->extractChatId($message, $update);
        $text = trim($this->extractText($message, $update));

        if (! $chatId) {
            return ['status' => 'ok'];
        }

        $chatIdStr = (string) $chatId;
        $token = $this->extractFlatToken($update) ?? $this->extractStartToken($text);

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
     * chat object is nested under message.message.chat, flat n8n-relayed
     * payloads (chat_id), and falls back to the triggering user id
     * (from.id / chat.id) for membership updates.
     */
    private function extractChatId(?array $message, array $update): mixed
    {
        if (isset($update['chat_id'])) {
            return $update['chat_id'];
        }

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
     * Extract the text payload of a message, supporting callback_query data,
     * flat n8n-relayed payloads (text / user_message) and message.text.
     */
    private function extractText(?array $message, array $update): string
    {
        return $update['text']
            ?? $message['text']
            ?? $message['data']
            ?? $message['message']['text']
            ?? $update['user_message']
            ?? '';
    }

    /**
     * Extract a linking token sent as a flat field, as produced by n8n-relayed payloads.
     */
    private function extractFlatToken(array $update): ?string
    {
        $token = $update['token'] ?? null;

        if (is_string($token) && trim($token) !== '') {
            return trim($token);
        }

        return null;
    }

    private function linkAccount(string $token, string $chatId): array
    {
        $linkingToken = TelegramLinkingToken::where('token', $token)->first();

        if (! $linkingToken) {
            Log::warning('TelegramWebhookService: token de vinculación no encontrado', [
                'token' => $token,
                'chat_id' => $chatId,
            ]);

            $this->sendInvalidTokenMessage($chatId);

            return [
                'status' => 'invalid',
                'message' => 'El enlace o token de vinculación no es válido.',
            ];
        }

        // Idempotencia: Telegram reenvía el mismo /start varias veces.
        // Si el token ya fue usado con el MISMO chat_id, responder como exitoso.
        if ($linkingToken->isUsed() && $linkingToken->telegram_chat_id === $chatId) {
            Log::info('TelegramWebhookService: token reutilizado por el mismo chat_id (idempotente)', [
                'token' => $token,
                'chat_id' => $chatId,
            ]);

            return [
                'status' => 'linked',
                'message' => 'Tu cuenta ya se encuentra vinculada correctamente.',
            ];
        }

        // Validar si expiró o si fue consumido por otro chat
        if ($linkingToken->isExpired() || $linkingToken->isUsed()) {
            Log::warning('TelegramWebhookService: token expirado o ya consumido por otro usuario', [
                'token' => $token,
                'chat_id' => $chatId,
                'used_at' => $linkingToken->used_at,
                'expires_at' => $linkingToken->expires_at,
            ]);

            $this->sendInvalidTokenMessage($chatId);

            return [
                'status' => 'expired',
                'message' => 'El enlace de vinculación ha expirado o ya fue utilizado.',
            ];
        }

        // --- VINCULACIÓN EXITOSA ---

        // 1. Asignar el chat_id en las credenciales del negocio/owner
        $credential = ChannelCredential::updateOrCreate(
            ['owner_id' => $linkingToken->owner_id],
            [
                'telegram_chat_id' => $chatId,
                'telegram_linked_at' => now(),
                'bot_type' => $linkingToken->bot_type ?? 'custom',
            ]
        );

        // 2. Marcar el token como consumido
        $linkingToken->update([
            'used_at' => now(),
            'telegram_chat_id' => $chatId,
        ]);

        // 3. Enviar confirmación al usuario vía Telegram API
        $botToken = $credential?->telegram_bot_token
            ?: (WebSetting::getSettings()?->global_telegram_bot_token
            ?: config('services.telegram.bot_token'));

        if ($botToken) {
            $this->sendTelegramMessage($botToken, $chatId, '🎉 ¡Cuenta vinculada exitosamente! Ya puedes interactuar con tu asistente de Al Día.');
        }

        Log::info('TelegramWebhookService: vinculación de Telegram completada exitosamente', [
            'owner_id' => $linkingToken->owner_id,
            'user_id' => $linkingToken->user_id,
            'chat_id' => $chatId,
            'token' => $token,
        ]);

        return [
            'status' => 'linked',
            'message' => '¡Cuenta vinculada con éxito en Al Día! 🎉',
        ];
    }

    private function sendInvalidTokenMessage(string $chatId): void
    {
        $botToken = WebSetting::getSettings()?->global_telegram_bot_token
            ?: config('services.telegram.bot_token');

        if ($botToken) {
            $this->sendTelegramMessage($botToken, $chatId, '❌ El token de vinculación es inválido o ha expirado. Por favor, genera un nuevo enlace desde la plataforma.');
        }
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
            'message' => $text ?: 'Inicialización de canal',
            'text' => $text ?: 'Inicialización de canal',
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
