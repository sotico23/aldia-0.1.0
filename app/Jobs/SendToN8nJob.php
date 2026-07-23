<?php

namespace App\Jobs;

use App\Models\AutomationConfig;
use App\Models\AutomationExecution;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendToN8nJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 3;

    public int $maxExceptions = 1;

    /** @var int[] Seconds to wait between retry attempts */
    public array $backoff = [5, 30, 120];

    public function __construct(
        public array $payload,
        public int $ownerId,
        public ?string $workflow = null,
        public ?int $automationConfigId = null,
    ) {}

    public function failed(\Throwable $e): void
    {
        Log::error('SendToN8nJob exhausted all retries', [
            'owner_id' => $this->ownerId,
            'workflow' => $this->workflow,
            'error' => $e->getMessage(),
        ]);
    }

    public function handle(): void
    {
        $startTime = microtime(true);

        try {
            $webhookUrl = $this->resolveWebhookUrl();

            if (! $webhookUrl) {
                Log::warning('SendToN8nJob: No webhook URL configured', [
                    'owner_id' => $this->ownerId,
                    'workflow' => $this->workflow,
                ]);

                return;
            }

            $response = Http::timeout(30)
                ->connectTimeout(10)
                ->post($webhookUrl, $this->payload);

            $executionTime = (int) ((microtime(true) - $startTime) * 1000);

            AutomationExecution::create([
                'owner_id' => $this->ownerId,
                'workflow' => $this->workflow ?? 'manual',
                'status' => $response->successful() ? 'success' : 'failed',
                'triggered_by' => 'job',
                'payload' => $this->payload,
                'output' => $response->successful()
                    ? ['body' => $response->body(), 'status' => $response->status()]
                    : null,
                'error_message' => $response->successful() ? null : $response->body(),
                'execution_time_ms' => $executionTime,
                'executed_at' => now(),
            ]);

            if (! $response->successful()) {
                Log::warning('SendToN8nJob: HTTP request failed', [
                    'owner_id' => $this->ownerId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            $executionTime = (int) ((microtime(true) - $startTime) * 1000);

            AutomationExecution::create([
                'owner_id' => $this->ownerId,
                'workflow' => $this->workflow ?? 'manual',
                'status' => 'failed',
                'triggered_by' => 'job',
                'payload' => $this->payload,
                'output' => null,
                'error_message' => $e->getMessage(),
                'execution_time_ms' => $executionTime,
                'executed_at' => now(),
            ]);

            Log::error('SendToN8nJob: Exception', [
                'owner_id' => $this->ownerId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function resolveWebhookUrl(): ?string
    {
        $query = AutomationConfig::where('owner_id', $this->ownerId);

        if ($this->automationConfigId) {
            $query->where('id', $this->automationConfigId);
        }

        $config = $query->first();

        if ($config?->n8n_webhook_url) {
            return $config->n8n_webhook_url;
        }

        return config('services.n8n.webhook_url');
    }
}
