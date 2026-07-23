<?php

namespace App\Services;

use App\Models\AutomationExecution;
use Illuminate\Support\Facades\DB;

class ObservabilityService
{
    public function getTodayExecutions(): int
    {
        return AutomationExecution::whereDate('created_at', today())->count();
    }

    public function getFailedExecutions(): int
    {
        return AutomationExecution::where('status', 'error')
            ->whereDate('created_at', today())
            ->count();
    }

    public function getAverageExecutionTime(): ?float
    {
        return AutomationExecution::whereNotNull('execution_time_ms')
            ->whereDate('created_at', today())
            ->avg('execution_time_ms');
    }

    public function getPendingJobs(): int
    {
        return DB::table('jobs')->count();
    }

    public function getFailedJobs(): int
    {
        return DB::table('failed_jobs')->count();
    }

    public function getLastExecutionByWorkflow(): array
    {
        return AutomationExecution::query()
            ->select('workflow', 'status', 'executed_at', 'execution_time_ms', 'error_message', 'uuid')
            ->orderBy('executed_at', 'desc')
            ->get()
            ->groupBy('workflow')
            ->map(fn ($items) => $items->first())
            ->toArray();
    }

    public function getSuccessRate(): float
    {
        $total = AutomationExecution::whereDate('created_at', today())->count();
        if ($total === 0) {
            return 100.0;
        }

        $successful = AutomationExecution::whereIn('status', ['success', 'sent_to_n8n'])
            ->whereDate('created_at', today())
            ->count();

        return round(($successful / $total) * 100, 1);
    }

    public function getHealthStatus(): array
    {
        $pendingJobs = $this->getPendingJobs();
        $failedJobs = $this->getFailedJobs();
        $todayExecutions = $this->getTodayExecutions();
        $failedExecutions = $this->getFailedExecutions();
        $avgTime = $this->getAverageExecutionTime();
        $successRate = $this->getSuccessRate();
        $staleReservedJobs = $this->getStaleReservedJobs();

        $issues = [];

        if ($pendingJobs > 100) {
            $issues[] = [
                'severity' => 'warning',
                'message' => "{$pendingJobs} jobs pendientes en la cola.",
            ];
        }

        if ($failedJobs > 0) {
            $issues[] = [
                'severity' => 'error',
                'message' => "{$failedJobs} jobs en la tabla de fallidos.",
            ];
        }

        if ($todayExecutions > 0 && $successRate < 80) {
            $issues[] = [
                'severity' => 'error',
                'message' => "Tasa de éxito de automatizaciones baja: {$successRate}%.",
            ];
        }

        if ($staleReservedJobs > 0) {
            $issues[] = [
                'severity' => 'error',
                'message' => "{$staleReservedJobs} jobs reservados sin procesar — posible worker caído.",
            ];
        }

        if ($todayExecutions === 0 && now()->hour >= 9) {
            $issues[] = [
                'severity' => 'warning',
                'message' => 'Sin ejecuciones hoy — verificar que el scheduler esté activo.',
            ];
        }

        return [
            'status' => empty($issues) ? 'healthy' : (collect($issues)->contains('severity', 'error') ? 'unhealthy' : 'degraded'),
            'timestamp' => now()->toIso8601String(),
            'metrics' => [
                'pending_jobs' => $pendingJobs,
                'failed_jobs' => $failedJobs,
                'today_executions' => $todayExecutions,
                'failed_executions' => $failedExecutions,
                'average_execution_time_ms' => $avgTime,
                'success_rate_percent' => $successRate,
                'stale_reserved_jobs' => $staleReservedJobs,
            ],
            'issues' => $issues,
        ];
    }

    public function getStaleReservedJobs(): int
    {
        return DB::table('jobs')
            ->whereNotNull('reserved_at')
            ->where('reserved_at', '<', now()->subMinutes(5)->timestamp)
            ->count();
    }

    public function checkConsecutiveFailures(int $threshold = 3): array
    {
        $workflows = AutomationExecution::query()
            ->select('workflow', 'status', 'created_at', 'uuid')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('workflow');

        $alerts = [];

        foreach ($workflows as $workflow => $executions) {
            $consecutiveFailures = 0;

            foreach ($executions as $execution) {
                if (in_array($execution->status, ['error', 'failed'], true)) {
                    $consecutiveFailures++;

                    if ($consecutiveFailures >= $threshold) {
                        $alerts[] = [
                            'workflow' => $workflow,
                            'consecutive_failures' => $consecutiveFailures,
                            'last_uuid' => $execution->uuid,
                            'last_error' => $execution->error_message,
                        ];

                        break;
                    }
                } else {
                    break;
                }
            }
        }

        return $alerts;
    }

    public function getQueueWaitTime(): ?float
    {
        $jobs = DB::table('jobs')
            ->where('available_at', '>', 0)
            ->select('available_at')
            ->get();

        if ($jobs->isEmpty()) {
            return null;
        }

        $now = now()->timestamp;
        $totalWait = $jobs->sum(fn ($job) => max(0, $now - $job->available_at));

        return round($totalWait / $jobs->count(), 2);
    }
}
