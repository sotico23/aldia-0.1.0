<?php

namespace App\Services;

use App\Models\ChannelCredential;
use App\Models\TelegramLinkingToken;
use App\Models\WebSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    protected ?ChannelCredential $credentials = null;

    public function __construct(?int $ownerId = null)
    {
        if ($ownerId) {
            $this->credentials = ChannelCredential::where('owner_id', $ownerId)->first();
        }
    }

    public function forOwner(int $ownerId): static
    {
        $this->credentials = ChannelCredential::where('owner_id', $ownerId)->first();

        return $this;
    }

    public function getToken(): ?string
    {
        return $this->credentials?->telegram_bot_token;
    }

    protected function getBaseUrl(): string
    {
        $token = $this->getToken();

        if (! $token) {
            throw new \RuntimeException('Telegram bot token not configured.');
        }

        return "https://api.telegram.org/bot{$token}";
    }

    public function validateCredentials(?string $token = null): array
    {
        $botToken = $token ?? $this->getToken();

        if (! $botToken) {
            return [
                'success' => false,
                'message' => 'No hay token de bot configurado.',
            ];
        }

        try {
            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->withOptions(['verify' => false])
                ->get("https://api.telegram.org/bot{$botToken}/getMe");

            $result = $response->json();

            if ($response->successful() && ($result['ok'] ?? false)) {
                $botInfo = $result['result'];

                return [
                    'success' => true,
                    'message' => "Conexión exitosa! Bot @{$botInfo['username']}",
                    'bot_username' => $botInfo['username'],
                    'bot_id' => $botInfo['id'],
                    'bot_name' => $botInfo['first_name'] ?? '',
                ];
            }

            return [
                'success' => false,
                'message' => $result['description'] ?? 'Error al validar el token de Telegram.',
            ];
        } catch (\Exception $e) {
            Log::error('Telegram validation error', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'No se pudo conectar con la API de Telegram.',
            ];
        }
    }

    public function getBotInfo(): array
    {
        try {
            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->withOptions(['verify' => false])
                ->get($this->getBaseUrl().'/getMe');

            $result = $response->json();

            if ($response->successful() && ($result['ok'] ?? false)) {
                return $result['result'];
            }

            return [];
        } catch (\Exception $e) {
            Log::error('Telegram getBotInfo error', ['error' => $e->getMessage()]);

            return [];
        }
    }

    public function setWebhook(string $webhookUrl): array
    {
        $token = $this->getToken();

        if (! $token) {
            return [
                'success' => false,
                'message' => 'No hay token de bot configurado.',
            ];
        }

        try {
            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->withOptions(['verify' => false])
                ->post("https://api.telegram.org/bot{$token}/setWebhook", [
                    'url' => $webhookUrl,
                    'drop_pending_updates' => true,
                ]);

            $result = $response->json();

            if ($response->successful() && ($result['ok'] ?? false)) {
                Log::info('Telegram webhook set successfully', ['url' => $webhookUrl]);

                return [
                    'success' => true,
                    'message' => 'Webhook configurado correctamente.',
                ];
            }

            return [
                'success' => false,
                'message' => $result['description'] ?? 'Error al configurar el webhook.',
            ];
        } catch (\Exception $e) {
            Log::error('Telegram setWebhook error', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'Error de conexión al configurar el webhook.',
            ];
        }
    }

    /**
     * Genera un token de vinculación de 15 minutos para el owner, invalidando
     * cualquier token previo activo (no usado y no expirado) del mismo owner.
     *
     * Resuelve el bot según el tipo: 'global' usa el bot oficial configurado en
     * Configuración Web; cualquier otro valor usa el bot personalizado del
     * tenant (con fallback al bot oficial si no tiene bot propio).
     *
     * @return array{success: bool, message?: string, telegram_url?: string, token?: string, bot_username?: string, bot_type?: string, expires_at?: Carbon}
     */
    public function generateLinkingToken(int $ownerId, string $botType = 'custom', ?int $userId = null): array
    {
        // 1. Invalidar tokens anteriores sin usar para este owner (expiran en
        //    el pasado para preservar auditoría y que la búsqueda del webhook
        //    no los considere válidos nunca más).
        $invalidated = TelegramLinkingToken::query()
            ->where('owner_id', $ownerId)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->update(['expires_at' => now()->subSecond()]);

        if ($invalidated > 0) {
            Log::info('Telegram: tokens previos invalidados', [
                'owner_id' => $ownerId,
                'count' => $invalidated,
            ]);
        }

        // 2. Crear el nuevo token con vigencia de 15 minutos.
        $token = TelegramLinkingToken::generateToken();
        $expiresAt = now()->addMinutes(15);

        TelegramLinkingToken::create([
            'owner_id' => $ownerId,
            'user_id' => $userId,
            'token' => $token,
            'bot_type' => $botType,
            'expires_at' => $expiresAt,
        ]);

        // 3. Resolver el username del bot para construir el deep link.
        if ($botType === 'global' || $botType === 'oficial') {
            $botUsername = WebSetting::getSettings()?->global_telegram_bot_username
                ?? config('services.telegram.default_bot_username');

            if (! $botUsername) {
                Log::warning('Telegram: no global bot configured for linking', [
                    'owner_id' => $ownerId,
                ]);

                return [
                    'success' => false,
                    'message' => 'No hay un bot oficial configurado en la plataforma. Por favor configúralo en Configuración Web.',
                ];
            }
        } else {
            $credentials = ChannelCredential::where('owner_id', $ownerId)->first();
            $botUsername = $credentials?->telegram_bot_username;

            // Fallback al bot oficial si el tenant no tiene bot personalizado.
            if (! $botUsername) {
                $botUsername = WebSetting::getSettings()?->global_telegram_bot_username
                    ?? config('services.telegram.default_bot_username');

                if (! $botUsername) {
                    Log::warning('Telegram: no bot configured for linking', [
                        'owner_id' => $ownerId,
                    ]);

                    return [
                        'success' => false,
                        'message' => 'No hay un bot de Telegram configurado. Guarda las credenciales del bot primero.',
                    ];
                }

                $botType = 'global';
            }
        }

        $telegramUrl = 'https://t.me/'.ltrim($botUsername, '@')."?start={$token}";

        Log::info('Telegram linking token generated', [
            'owner_id' => $ownerId,
            'user_id' => $userId,
            'token' => $token,
            'bot_type' => $botType,
            'bot_username' => $botUsername,
            'expires_at' => $expiresAt->toIso8601String(),
        ]);

        return [
            'success' => true,
            'telegram_url' => $telegramUrl,
            'token' => $token,
            'bot_username' => $botUsername,
            'bot_type' => $botType,
            'expires_at' => $expiresAt,
        ];
    }

    public function sendMessage(string $chatId, string $message, array $options = []): array
    {
        try {
            $payload = array_merge([
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'Markdown',
                'disable_web_page_preview' => true,
            ], $options);

            $response = Http::timeout(15)
                ->connectTimeout(5)
                ->withOptions(['verify' => false])
                ->post($this->getBaseUrl().'/sendMessage', $payload);

            $result = $response->json();

            if ($response->successful() && ($result['ok'] ?? false)) {
                $messageId = $result['result']['message_id'] ?? null;

                Log::info('Telegram sendMessage success', [
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                ]);

                return [
                    'success' => true,
                    'message_id' => $messageId,
                    'chat_id' => $chatId,
                ];
            }

            Log::warning('Telegram sendMessage failed', [
                'chat_id' => $chatId,
                'error' => $result['description'] ?? 'Unknown error',
            ]);

            return [
                'success' => false,
                'message' => $result['description'] ?? 'Error al enviar mensaje.',
            ];
        } catch (\Exception $e) {
            Log::error('Telegram sendMessage error', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Error de conexión con Telegram.',
            ];
        }
    }
}
