<?php

namespace App\Jobs;

use App\Actions\HandleAutomationFailure;
use App\Models\AutomationConfig;
use App\Models\AutomationExecution;
use App\Models\ChannelCredential;
use App\Services\AutomationReportService;
use App\Services\N8nService;
use App\Services\TelegramService;
use App\Services\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class RunAutomationJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 3;

    public int $maxExceptions = 1;

    /** @var int[] Seconds to wait between retry attempts */
    public array $backoff = [5, 30, 120];

    public function __construct(
        public int $ownerId,
        public int $automationConfigId,
    ) {}

    public function failed(\Throwable $e): void
    {
        Log::error('RunAutomationJob exhausted all retries', [
            'owner_id' => $this->ownerId,
            'config_id' => $this->automationConfigId,
            'job_uuid' => $this->job?->uuid(),
            'error' => $e->getMessage(),
        ]);

        app(HandleAutomationFailure::class)(
            workflow: 'automation',
            ownerId: (string) $this->ownerId,
            errorMessage: $e->getMessage(),
        );
    }

    public function handle(
        AutomationReportService $reportService,
        N8nService $n8nService,
    ): void {
        $startTime = microtime(true);

        try {
            $config = AutomationConfig::where('owner_id', $this->ownerId)
                ->where('id', $this->automationConfigId)
                ->first();

            if (! $config || ! $config->enabled) {
                return;
            }

            $reports = $reportService->generate(
                $this->ownerId,
                $config->selected_reports ?? []
            );

            if ($n8nService->isAvailable()) {
                $this->dispatchToN8n($n8nService, $config, $reports, $startTime);
            } else {
                $this->sendDirectly($config, $reports, $startTime);
            }
        } catch (\Throwable $e) {
            $executionTime = (int) ((microtime(true) - $startTime) * 1000);

            AutomationExecution::create([
                'owner_id' => $this->ownerId,
                'workflow' => 'automation',
                'status' => 'error',
                'triggered_by' => 'scheduler',
                'payload' => array_merge(
                    ['automation_config_id' => $this->automationConfigId],
                    ['job_uuid' => $this->job?->uuid()],
                ),
                'output' => null,
                'error_message' => $e->getMessage(),
                'execution_time_ms' => $executionTime,
                'executed_at' => now(),
            ]);

            Log::error('RunAutomationJob failed', [
                'owner_id' => $this->ownerId,
                'config_id' => $this->automationConfigId,
                'job_uuid' => $this->job?->uuid(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function dispatchToN8n(N8nService $n8nService, AutomationConfig $config, array $reports, float $startTime): void
    {
        $execution = AutomationExecution::create([
            'owner_id' => $this->ownerId,
            'workflow' => 'automation',
            'status' => 'processing',
            'triggered_by' => 'scheduler',
            'payload' => [
                'automation_config_id' => $config->id,
                'channel' => $config->channel,
                'reports' => $reports,
            ],
            'executed_at' => now(),
        ]);

        $result = $n8nService->triggerWorkflow(
            $this->ownerId,
            $config->channel,
            $reports,
        );

        $executionTime = (int) ((microtime(true) - $startTime) * 1000);

        if ($result['success']) {
            $execution->update([
                'status' => 'sent_to_n8n',
                'execution_time_ms' => $executionTime,
            ]);

            $config->update([
                'last_run_status' => 'sent_to_n8n',
            ]);
        } else {
            $execution->update([
                'status' => 'error',
                'error_message' => $result['message'] ?? 'n8n trigger failed',
                'execution_time_ms' => $executionTime,
            ]);

            $config->update([
                'last_run_status' => 'error',
            ]);
        }
    }

    protected function sendDirectly(AutomationConfig $config, array $reports, float $startTime): void
    {
        $credentials = ChannelCredential::where('owner_id', $this->ownerId)->first();

        if (! $credentials) {
            AutomationExecution::create([
                'owner_id' => $this->ownerId,
                'workflow' => 'automation',
                'status' => 'error',
                'triggered_by' => 'scheduler',
                'payload' => ['automation_config_id' => $config->id],
                'error_message' => 'No channel credentials configured.',
                'execution_time_ms' => (int) ((microtime(true) - $startTime) * 1000),
                'executed_at' => now(),
            ]);

            return;
        }

        $message = $this->formatReportsAsMessage($reports);
        $errors = [];

        $channels = $config->channel === 'both'
            ? ['telegram', 'whatsapp']
            : [$config->channel];

        $execution = AutomationExecution::create([
            'owner_id' => $this->ownerId,
            'workflow' => 'automation',
            'status' => 'processing',
            'triggered_by' => 'scheduler',
            'payload' => [
                'automation_config_id' => $config->id,
                'channel' => $config->channel,
                'reports' => $reports,
            ],
            'executed_at' => now(),
        ]);

        foreach ($channels as $channel) {
            try {
                $result = match ($channel) {
                    'telegram' => $this->sendTelegram($message, $credentials),
                    'whatsapp' => $this->sendWhatsApp($message, $credentials),
                    default => ['success' => false, 'message' => "Unknown channel: {$channel}"],
                };

                if (! $result['success']) {
                    $errors[] = $result['message'] ?? "Failed to send via {$channel}";
                }
            } catch (\Throwable $e) {
                $errors[] = "{$channel}: {$e->getMessage()}";
            }
        }

        $executionTime = (int) ((microtime(true) - $startTime) * 1000);

        $status = empty($errors) ? 'success' : 'partial_error';

        $execution->update([
            'status' => $status,
            'output' => ['errors' => $errors],
            'error_message' => empty($errors) ? null : implode('; ', $errors),
            'execution_time_ms' => $executionTime,
        ]);

        $config->update([
            'last_run_status' => $status,
        ]);
    }

    protected function sendTelegram(string $message, ?ChannelCredential $credentials = null): array
    {
        $credentials ??= ChannelCredential::where('owner_id', $this->ownerId)->first();
        if (! $credentials || ! $credentials->telegram_bot_token) {
            return ['success' => false, 'message' => 'Telegram not configured.'];
        }

        $chatId = config('services.telegram.default_chat_id');

        if (! $chatId) {
            return ['success' => false, 'message' => 'Telegram chat ID not configured.'];
        }

        $service = (new TelegramService)->forOwner($this->ownerId);

        return $service->sendMessage($chatId, $message);
    }

    protected function sendWhatsApp(string $message, ?ChannelCredential $credentials = null): array
    {
        $credentials ??= ChannelCredential::where('owner_id', $this->ownerId)->first();
        if (! $credentials || ! $credentials->whatsapp_access_token) {
            return ['success' => false, 'message' => 'WhatsApp not configured.'];
        }

        $to = config('services.whatsapp.default_to');

        if (! $to) {
            return ['success' => false, 'message' => 'WhatsApp default recipient not configured.'];
        }

        $service = (new WhatsAppService)->forOwner($this->ownerId);

        return $service->sendMessage($to, $message);
    }

    protected function formatReportsAsMessage(array $reports): string
    {
        $lines = [];
        $lines[] = '📊 *Reporte Automatizado*';
        $lines[] = now()->isoFormat('dddd D [de] MMMM [del] YYYY, h:mm A');
        $lines[] = str_repeat('─', 30);

        foreach ($reports as $key => $data) {
            $lines[] = '';
            $lines[] = match ($key) {
                'resumen_ejecutivo' => $this->formatResumenEjecutivo($data),
                'ventas' => $this->formatVentas($data),
                'inventario' => $this->formatInventario($data),
                'stock_bajo' => $this->formatStockBajo($data),
                'clientes_nuevos' => $this->formatClientesNuevos($data),
                'clientes_inactivos' => $this->formatClientesInactivos($data),
                'agenda_citas' => $this->formatAgendaCitas($data),
                'gastos' => $this->formatGastos($data),
                'flujo_caja' => $this->formatFlujoCaja($data),
                'ctas_cobrar' => $this->formatCtasCobrar($data),
                'ctas_pagar' => $this->formatCtasPagar($data),
                default => null,
            };
        }

        return implode("\n", array_filter($lines));
    }

    protected function formatResumenEjecutivo(array $data): string
    {
        $periodo = $data['periodo'] ?? 'N/A';
        $ventas = number_format($data['ventas_mes'] ?? 0, 0, ',', '.');
        $gastos = number_format($data['gastos_mes'] ?? 0, 0, ',', '.');
        $margen = number_format($data['margen'] ?? 0, 0, ',', '.');

        return "📈 *Resumen Ejecutivo ({$periodo})*\n"
            ."Ventas: \${$ventas}\n"
            ."Gastos: \${$gastos}\n"
            ."Margen: \${$margen}\n"
            ."Clientes: {$data['total_clientes']}\n"
            ."Productos: {$data['total_productos']}";
    }

    protected function formatVentas(array $data): string
    {
        $total = number_format($data['total_monto_mes'] ?? 0, 0, ',', '.');

        return "💰 *Ventas del Mes*\n"
            ."Total: \${$total}\n"
            ."Transacciones: {$data['total_ventas_mes']}\n"
            .'Ticket Promedio: $'.number_format($data['promedio_ticket'] ?? 0, 0, ',', '.');
    }

    protected function formatInventario(array $data): string
    {
        $valor = number_format($data['valor_inventario'] ?? 0, 0, ',', '.');

        return "📦 *Inventario*\n"
            ."Productos activos: {$data['total_productos']}\n"
            ."Unidades en stock: {$data['total_unidades']}\n"
            ."Valor inventario: \${$valor}";
    }

    protected function formatStockBajo(array $data): string
    {
        $productos = $data['productos'] ?? [];

        if (empty($productos)) {
            return "✅ *Stock Bajo*\nNo hay productos con stock bajo.";
        }

        $lines = ["⚠️ *Stock Bajo ({$data['total']} productos)*"];
        foreach ($productos as $p) {
            $lines[] = "• {$p['nombre']}: {$p['stock']}/{$p['minimo']}";
        }

        return implode("\n", $lines);
    }

    protected function formatClientesNuevos(array $data): string
    {
        return "👤 *Clientes Nuevos*\n"
            ."{$data['total']} nuevos en {$data['periodo']}";
    }

    protected function formatClientesInactivos(array $data): string
    {
        return "😴 *Clientes Inactivos*\n"
            ."{$data['total']} {$data['periodo_referencia']}";
    }

    protected function formatAgendaCitas(array $data): string
    {
        return "📅 *Agenda de Citas*\n"
            ."Hoy: {$data['citas_hoy']}\n"
            ."Próximos 7 días: {$data['citas_proximos_7d']}";
    }

    protected function formatGastos(array $data): string
    {
        $total = number_format($data['total_mes'] ?? 0, 0, ',', '.');

        return "💸 *Gastos del Mes*\nTotal: \${$total}";
    }

    protected function formatFlujoCaja(array $data): string
    {
        $ingresos = number_format($data['ingresos'] ?? 0, 0, ',', '.');
        $egresos = number_format($data['egresos'] ?? 0, 0, ',', '.');
        $saldo = number_format($data['saldo'] ?? 0, 0, ',', '.');

        return "🏦 *Flujo de Caja ({$data['periodo']})*\n"
            ."Ingresos: +\${$ingresos}\n"
            ."Egresos: -\${$egresos}\n"
            ."Saldo: \${$saldo}";
    }

    protected function formatCtasCobrar(array $data): string
    {
        $total = number_format($data['total'] ?? 0, 0, ',', '.');

        return "📋 *Cuentas por Cobrar*\n"
            ."Total: \${$total}\n"
            ."Cantidad: {$data['cantidad']}";
    }

    protected function formatCtasPagar(array $data): string
    {
        $total = number_format($data['total'] ?? 0, 0, ',', '.');

        return "📋 *Cuentas por Pagar*\n"
            ."Total: \${$total}\n"
            ."Cantidad: {$data['cantidad']}";
    }
}
