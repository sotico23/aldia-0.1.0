<?php

namespace App\Services;

use App\Models\ChannelCredential;
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
