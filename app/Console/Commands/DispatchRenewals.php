<?php

namespace App\Console\Commands;

use App\Jobs\RenewSubscription;
use App\Models\Subscription;
use Illuminate\Console\Command;

class DispatchRenewals extends Command
{
    protected $signature = 'subscriptions:renew
        {--dry-run : Report what would renew without charging}';

    protected $description = 'Dispatch renewal jobs for subscriptions due today';

    public function handle(): int
    {
        $due = Subscription::active()
            ->where('expires_at', '<=', now()->addDay())
            ->get();

        if ($due->isEmpty()) {
            $this->info('No subscriptions due for renewal.');

            return self::SUCCESS;
        }

        $this->info("Found {$due->count()} subscription(s) due for renewal.");

        foreach ($due as $subscription) {
            if ($this->option('dry-run')) {
                $this->line("  [DRY-RUN] Would renew subscription #{$subscription->id} for business #{$subscription->business_id}");
            } else {
                RenewSubscription::dispatch($subscription);
                $this->line("  Dispatched renewal for subscription #{$subscription->id}");
            }
        }

        return self::SUCCESS;
    }
}
