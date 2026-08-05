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
                    : $http->post($targetUrl, $this->buildTestPayload(false));

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
     * Resolve the Telegram proxy URL for a tenant: tenant-specific value wins,
     * falling back to the global n8n integration values.
     */
    public function resolveTenantTelegramProxyUrl(?string $tenantProxyUrl): ?string
    {
        return $tenantProxyUrl ?: $this->getTelegramProxyUrl() ?: $this->getWebhookUrl();
    }

    /**
     * Test the tenant's n8n Telegram proxy webhook URL. If the tenant has no
     * URL configured, the global n8n integration values are used as fallback.
     * When only a Base URL is available, its /healthz endpoint is checked.
     * The tenant API key (when provided) is sent as X-N8N-TOKEN; the global
     * n8n API key is sent as X-API-Key so protected workflows accept the test.
     *
     * With $isTest = false the payload mirrors a real inbound message
     * (is_test=false) so the workflow runs through its validation nodes.
     */
    public function testTenantConnection(?string $tenantProxyUrl = null, ?string $tenantBaseUrl = null, ?string $tenantApiKey = null, bool $isTest = true): array
    {
        $targetUrl = $this->resolveTenantTelegramProxyUrl($tenantProxyUrl);

        if (! $targetUrl && $tenantBaseUrl) {
            $targetUrl = rtrim($tenantBaseUrl, '/').'/healthz';
        }

        if (! $targetUrl) {
            return [
                'success' => false,
                'message' => 'No hay URL de proxy de Telegram configurada. Ingresa tu URL personalizada o configura la URL global en Configuración Web > n8n.',
            ];
        }

        try {
            $isHealthz = str_ends_with($targetUrl, '/healthz');
            $http = Http::timeout(10)
                ->connectTimeout(5)
                ->withOptions(['verify' => false]);

            $apiKey = $tenantApiKey ?: config('services.n8n.token');
            if ($apiKey) {
                $http = $http->withHeaders(['X-N8N-TOKEN' => $apiKey]);
            }

            $globalApiKey = $this->getConfig()?->api_key;
            if ($globalApiKey) {
                $http = $http->withHeaders(['X-API-Key' => $globalApiKey]);
            }

            $response = $isHealthz
                ? $http->get($targetUrl)
                : $http->post($targetUrl, $this->buildTestPayload($isTest));

            if ($response->successful()) {
                Log::info('n8n tenant telegram proxy test succeeded', ['url' => $targetUrl, 'status' => $response->status()]);

                return [
                    'success' => true,
                    'message' => 'Conexión exitosa con tu proxy de Telegram de n8n (HTTP '.$response->status().').',
                    'url' => $targetUrl,
                ];
            }

            Log::warning('n8n tenant telegram proxy test failed', [
                'url' => $targetUrl,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'message' => 'n8n respondió con estado '.$response->status().'. Verifica que el workflow "Webhook Entrada Proxy Laravel" esté activo.',
            ];
        } catch (\Exception $e) {
            Log::error('n8n tenant telegram proxy test error', ['url' => $targetUrl, 'error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'No se pudo conectar con tu proxy de Telegram de n8n. Error: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Payload that the n8n "Webhook Entrada Proxy Laravel" node can recognize.
     *
     * With $isTest = true the payload short-circuits the workflow (is_test=true,
     * event=test_connection) without side effects.
     *
     * With $isTest = false the payload mirrors a real inbound message so the
     * workflow can proceed through its validation nodes end to end. This only
     * happens when the tenant has a linked telegram_chat_id: without one the
     * payload is downgraded to the short-circuit connection test, because a
     * simulated message without chat_id would break the workflow.
     */
    private function buildTestPayload(bool $isTest = true): array
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
            $existingToken = TelegramLinkingToken::whereNull('used_at')
                ->where('expires_at', '>', now())
                ->where('owner_id', $ownerId)
                ->latest()
                ->value('token');

            if ($existingToken) {
                $linkingUrl = "https://t.me/{$botUsernameClean}?start={$existingToken}";
            }
        }

        $chatId = $credential?->telegram_chat_id;

        // Sin un chat_id vinculado nunca se debe simular un mensaje real en
        // n8n: se degrada a la prueba corta de conexión (is_test=true). Los
        // tokens de vinculación solo se generan explícitamente desde la UI
        // (telegram.generate-link), nunca en pruebas automáticas o de conexión.
        if (! $isTest && ! $chatId) {
            $isTest = true;
        }

        $message = $isTest
            ? 'Prueba de conexión desde la plataforma'
            : 'Inicio de prueba de flujo';

        return [
            'type' => $isTest ? 'test_connection' : 'message',
            'event' => $isTest ? 'test_connection' : 'message',
            'message' => $isTest ? $message : ['text' => $message],
            'tenant_id' => $ownerId ?? 1,
            'owner_id' => $ownerId ?? 1,
            'bot_token' => $botToken,
            'bot_username' => $botUsername,
            'chat_id' => $chatId,
            'message.chat.id' => $chatId,
            'bot_type' => $credential?->bot_type ?? 'oficial',
            'is_linked' => (bool) $chatId,
            'linking_url' => $linkingUrl,
            'is_test' => $isTest,
            'text' => $message,
            'user_message' => $message,
            'timestamp' => now()->toIso8601String(),
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
