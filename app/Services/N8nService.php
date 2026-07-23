<?php

namespace App\Services;

use App\Models\SystemIntegration;
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
        return config('services.n8n.webhook_url') ?? $this->getConfig()?->webhook_url;
    }

    public function getBaseUrl(): ?string
    {
        return config('services.n8n.base_url') ?? $this->getConfig()?->base_url;
    }

    public function isAvailable(): bool
    {
        if (! empty(config('services.n8n.webhook_url'))) {
            return true;
        }

        $config = $this->getConfig();

        return $config !== null && $config->is_active && $config->webhook_url;
    }

    public function testConnection(): array
    {
        $baseUrl = $this->getBaseUrl();

        if (! $baseUrl) {
            return [
                'success' => false,
                'message' => 'No hay configuración de n8n. Configura la URL Base primero.',
            ];
        }

        try {
            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->withOptions(['verify' => false])
                ->get(rtrim($baseUrl, '/').'/healthz');

            $config = $this->getConfig();
            if ($config) {
                $config->update([
                    'last_check_at' => now(),
                    'last_check_status' => $response->successful() ? 'connected' : 'error',
                ]);
            }

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Conexión exitosa con n8n.',
                ];
            }

            return [
                'success' => false,
                'message' => 'n8n respondió con estado: '.$response->status(),
            ];
        } catch (\Exception $e) {
            Log::warning('n8n health check failed', ['error' => $e->getMessage()]);

            $config = $this->getConfig();
            if ($config) {
                $config->update([
                    'last_check_at' => now(),
                    'last_check_status' => 'error',
                ]);
            }

            return [
                'success' => false,
                'message' => 'No se pudo conectar con n8n. Verifica la URL e intenta nuevamente.',
            ];
        }
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
        $payload = [
            'business_id' => $businessId,
            'channel' => $channel,
            'reports' => $reports,
            'timestamp' => now()->toIso8601String(),
        ];

        if ($testMode) {
            $payload['test_mode'] = true;
        }

        return $payload;
    }
}
