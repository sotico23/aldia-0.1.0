<?php

namespace App\Services;

use App\Models\ChannelCredential;
use App\Models\SystemIntegration;
use App\Models\TelegramLinkingToken;
use App\Models\WebSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class N8nService
{
    public function getConfig(): ?SystemIntegration
    {
        return SystemIntegration::forProvider('n8n')->first();
    }

    /**
     * Priority: env config (services.n8n.*) > DB record (SystemIntegration provider='n8n').
     */
    public function getWebhookUrl(): ?string
    {
        return $this->getConfig()?->webhook_url ?: config('services.n8n.webhook_url');
    }

    public function getTelegramProxyUrl(): ?string
    {
        return $this->getConfig()?->telegram_proxy_url ?: config('services.n8n.telegram_proxy_url');
    }

    public function getBaseUrl(): ?string
    {
        return $this->getConfig()?->base_url ?: config('services.n8n.base_url');
    }

    public function isAvailable(): bool
    {
        if ($this->getWebhookUrl() || $this->getTelegramProxyUrl()) {
            return true;
        }

        $config = $this->getConfig();

        return $config !== null && $config->is_active;
    }

    public function testConnection(): array
    {
        $urlsToTry = array_values(array_unique(array_filter([
            $this->getWebhookUrl(),
            $this->getTelegramProxyUrl(),
            $this->getBaseUrl() ? rtrim($this->getBaseUrl(), '/').'/healthz' : null,
        ])));

        if (empty($urlsToTry)) {
            return [
                'success' => false,
                'message' => 'No hay URL de n8n configurada. Ingresa Telegram Proxy URL, Webhook URL o Base URL.',
            ];
        }

        $lastResponse = null;
        $lastError = null;

        foreach ($urlsToTry as $targetUrl) {
            try {
                $isHealthz = str_ends_with($targetUrl, '/healthz');
                $http = Http::timeout(10)->connectTimeout(5)->withOptions(['verify' => false]);

                $response = $isHealthz
                    ? $http->get($targetUrl)
                    : $http->post($targetUrl, $this->buildTestPayload());

                if ($response->successful()) {
                    $config = $this->getConfig();
                    if ($config) {
                        $config->update([
                            'last_check_at' => now(),
                            'last_check_status' => 'connected',
                        ]);
                    }

                    return [
                        'success' => true,
                        'message' => 'Conexión exitosa con n8n (HTTP '.$response->status().' en '.$targetUrl.').',
                    ];
                }

                $lastResponse = $response;
            } catch (\Exception $e) {
                $lastError = $e->getMessage();
                Log::warning('n8n connection test failed for URL', ['url' => $targetUrl, 'error' => $e->getMessage()]);
            }
        }

        $config = $this->getConfig();
        if ($config) {
            $config->update([
                'last_check_at' => now(),
                'last_check_status' => 'error',
            ]);
        }

        if ($lastResponse) {
            return [
                'success' => false,
                'message' => 'n8n respondió con estado: '.$lastResponse->status().' (Verifica si el workflow en n8n está activo o en modo "Listen for test event").',
            ];
        }

        return [
            'success' => false,
            'message' => 'No se pudo conectar con n8n. Error: '.($lastError ?? 'desconocido'),
        ];
    }

    /**
     * Test the n8n "Webhook Entrada Proxy Laravel" node specifically.
     * Targets the Telegram Proxy URL first, falling back to the generic Webhook URL.
     */
    public function testTelegramProxy(): array
    {
        $targetUrl = $this->getTelegramProxyUrl() ?: $this->getWebhookUrl();

        if (! $targetUrl) {
            return [
                'success' => false,
                'message' => 'No hay URL de proxy de Telegram configurada. Ingresa la Telegram Proxy URL o Webhook URL en la sección de n8n.',
            ];
        }

        try {
            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->withOptions(['verify' => false])
                ->post($targetUrl, $this->buildTestPayload());

            if ($response->successful()) {
                $config = $this->getConfig();
                if ($config) {
                    $config->update([
                        'last_check_at' => now(),
                        'last_check_status' => 'connected',
                    ]);
                }

                Log::info('n8n telegram proxy test succeeded', ['url' => $targetUrl, 'status' => $response->status()]);

                return [
                    'success' => true,
                    'message' => 'Conexión exitosa con el proxy de Telegram de n8n (HTTP '.$response->status().').',
                ];
            }

            Log::warning('n8n telegram proxy test failed', [
                'url' => $targetUrl,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'message' => 'n8n respondió con estado '.$response->status().'. Verifica que el workflow "Webhook Entrada Proxy Laravel" esté activo.',
            ];
        } catch (\Exception $e) {
            Log::error('n8n telegram proxy test error', ['url' => $targetUrl, 'error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'No se pudo conectar con el proxy de Telegram de n8n. Error: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Payload that the n8n "Webhook Entrada Proxy Laravel" node can recognize:
     * event=test_connection short-circuits the workflow without side effects.
     */
    private function buildTestPayload(): array
    {
        $ownerId = auth()->user()?->getOwnerId();
        $credential = $ownerId ? ChannelCredential::where('owner_id', $ownerId)->first() : ChannelCredential::first();
        $webSettings = WebSetting::getSettings();
        $botToken = $credential?->telegram_bot_token
            ?: ($webSettings?->global_telegram_bot_token
            ?: config('services.telegram.bot_token'));
        $botUsername = $credential?->telegram_bot_username
            ?: ($webSettings?->global_telegram_bot_username
            ?: config('services.telegram.bot_username'));

        $linkingUrl = null;
        if ($botUsername) {
            $botUsernameClean = ltrim($botUsername, '@');

            // Build the deep link using an EXISTING valid linking token if present.
            // We intentionally do NOT create new tokens here: a connection test to
            // n8n must not generate "phantom" linking rows (used_at/chat_id never
            // filled) that clutter telegram_linking_tokens. Token lifecycle is
            // owned exclusively by the /start deep-link flow.
            $existingToken = TelegramLinkingToken::valid()
                ->where('owner_id', $ownerId)
                ->latest()
                ->value('token');

            if ($existingToken) {
                $linkingUrl = "https://t.me/{$botUsernameClean}?start={$existingToken}";
            }
        }

        return [
            'event' => 'test_connection',
            'message' => 'Prueba de conexión desde la plataforma',
            'tenant_id' => $ownerId ?? 1,
            'bot_token' => $botToken,
            'bot_username' => $botUsername,
            'chat_id' => $credential?->telegram_chat_id,
            'bot_type' => $credential?->bot_type ?? 'oficial',
            'is_linked' => (bool) $credential?->telegram_chat_id,
            'linking_url' => $linkingUrl,
            'is_test' => true,
            'callback_url' => route('api.canales.telegram.webhook'),
            'webhook_url' => route('api.canales.telegram.webhook'),
        ];
    }

    public function triggerWorkflow(int $businessId, string $channel, array $reports, bool $testMode = false): array
    {
        $webhookUrl = $this->getWebhookUrl();

        if (! $webhookUrl) {
            return [
                'success' => false,
                'message' => 'Webhook global de n8n no configurado.',
            ];
        }

        try {
            $response = Http::timeout(30)
                ->connectTimeout(10)
                ->post($webhookUrl, $this->buildPayload($businessId, $channel, $reports, $testMode));

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Workflow ejecutado correctamente.',
                ];
            }

            Log::warning('n8n workflow failed', [
                'business_id' => $businessId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'message' => 'Error al ejecutar el workflow en n8n: '.$response->body(),
            ];
        } catch (\Exception $e) {
            Log::error('n8n connection error', [
                'business_id' => $businessId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Error de conexión con n8n. Intenta nuevamente.',
            ];
        }
    }

    public function buildPayload(int $businessId, string $channel, array $reports, bool $testMode = false): array
    {
        $credential = ChannelCredential::where('owner_id', $businessId)->first();
        $webSettings = WebSetting::getSettings();
        $botToken = $credential?->telegram_bot_token
            ?: ($webSettings?->global_telegram_bot_token
            ?: config('services.telegram.bot_token'));
        $botUsername = $credential?->telegram_bot_username
            ?: ($webSettings?->global_telegram_bot_username
            ?: config('services.telegram.bot_username'));

        $payload = [
            'business_id' => $businessId,
            'channel' => $channel,
            'reports' => $reports,
            'timestamp' => now()->toIso8601String(),
            'bot_token' => $botToken,
            'bot_username' => $botUsername,
        ];

        if ($testMode) {
            $payload['test_mode'] = true;
        }

        return $payload;
    }
}
