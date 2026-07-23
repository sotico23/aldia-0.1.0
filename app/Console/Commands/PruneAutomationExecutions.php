<?php

namespace App\Console\Commands;

use App\Models\AutomationExecution;
use Illuminate\Console\Command;

class PruneAutomationExecutions extends Command
{
    protected $signature = 'automations:prune {--days=90 : Prune records older than this many days}';

    protected $description = 'Prune old automation execution records to control table growth';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        $cutoff = now()->subDays($days);

        $deleted = 0;

        AutomationExecution::where('executed_at', '<', $cutoff)
            ->chunkById(100, function ($records) use (&$deleted) {
                $count = $records->count();
                AutomationExecution::whereIn('id', $records->pluck('id'))->delete();
                $deleted += $count;
            });

        $this->info("Pruned {$deleted} execution records older than {$days} days.");

        return Command::SUCCESS;
    }
}
