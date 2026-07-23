<?php

namespace App\Console\Commands;

use App\Jobs\RunAutomationJob;
use App\Models\AutomationConfig;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DispatchAutomations extends Command
{
    protected $signature = 'automations:dispatch';

    protected $description = 'Dispatch automation jobs that are due for execution';

    public function handle(): int
    {
        $now = now();

        $count = 0;

        AutomationConfig::query()
            ->where('enabled', true)
            ->where(function ($query) use ($now) {
                $query->whereNull('next_run_at')
                    ->orWhere('next_run_at', '<=', $now);
            })
            ->chunk(100, function ($automations) use ($now, &$count) {
                foreach ($automations as $config) {
                    try {
                        RunAutomationJob::dispatch($config->owner_id, $config->id);

                        $config->update([
                            'last_run_at' => $now,
                            'last_run_status' => 'dispatched',
                            'next_run_at' => $this->calculateNextRun($config->frequency, $config->execution_time),
                        ]);

                        $count++;
                    } catch (\Throwable $e) {
                        Log::error('Failed to dispatch automation', [
                            'config_id' => $config->id,
                            'owner_id' => $config->owner_id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        if ($count === 0) {
            $this->info('No automations due for dispatch.');
        } else {
            $this->info("Dispatched {$count} automation(s).");
        }

        return Command::SUCCESS;
    }

    protected function calculateNextRun(string $frequency, string $executionTime): ?Carbon
    {
        $time = Carbon::createFromFormat('H:i', $executionTime);

        return match ($frequency) {
            'daily' => $time->isFuture() ? $time : $time->addDay(),
            'weekly' => $time->isFuture() ? $time : $time->addWeek(),
            'monthly' => $time->isFuture() ? $time : $time->addMonth(),
            default => $time->isFuture() ? $time : $time->addDay(),
        };
    }
}
