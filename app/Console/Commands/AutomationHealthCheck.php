<?php

namespace App\Console\Commands;

use App\Services\ObservabilityService;
use Illuminate\Console\Command;

class AutomationHealthCheck extends Command
{
    protected $signature = 'automations:health-check {--format=table : Formato de salida (table|json|compact)}';

    protected $description = 'Check the health of the automation system';

    public function handle(ObservabilityService $observability): int
    {
        $health = $observability->getHealthStatus();

        $format = $this->option('format');

        if ($format === 'json') {
            $this->line(json_encode($health, JSON_PRETTY_PRINT));

            return $health['status'] === 'healthy' ? Command::SUCCESS : Command::FAILURE;
        }

        $this->components->twoColumnDetail('Estado del sistema', strtoupper($health['status']));
        $this->components->twoColumnDetail('Ejecuciones hoy', (string) $health['metrics']['today_executions']);
        $this->components->twoColumnDetail('Ejecuciones fallidas hoy', (string) $health['metrics']['failed_executions']);
        $this->components->twoColumnDetail('Tiempo promedio', $health['metrics']['average_execution_time_ms'] !== null ? round($health['metrics']['average_execution_time_ms'], 1).' ms' : 'N/A');
        $this->components->twoColumnDetail('Tasa de éxito', $health['metrics']['success_rate_percent'].'%');
        $this->components->twoColumnDetail('Jobs pendientes', (string) $health['metrics']['pending_jobs']);
        $this->components->twoColumnDetail('Jobs fallidos', (string) $health['metrics']['failed_jobs']);

        if (! empty($health['issues'])) {
            $this->newLine();
            $this->components->error('Problemas detectados:');

            foreach ($health['issues'] as $issue) {
                $icon = $issue['severity'] === 'error' ? '🔴' : '⚠️';
                $this->line("  {$icon} {$issue['message']}");
            }

            return Command::FAILURE;
        }

        $this->newLine();
        $this->components->info('Todos los sistemas operativos.');

        return Command::SUCCESS;
    }
}
