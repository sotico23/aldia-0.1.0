<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Illuminate\Console\Command;

class CheckSubscriptions extends Command
{
    protected $signature = 'subscriptions:check
        {--dry-run : Only report what would change without modifying}
        {--notify : Send notifications for expiring subscriptions}';

    protected $description = 'Check and update subscription statuses (expire, warn)';

    public function handle(): int
    {
        $expired = Subscription::whereIn('status', ['trial', 'active'])
            ->where('expires_at', '<', now())
            ->get();

        if ($expired->isEmpty()) {
            $this->info('No expired subscriptions found.');

            return self::SUCCESS;
        }

        $this->info("Found {$expired->count()} expired subscription(s).");

        foreach ($expired as $subscription) {
            if ($this->option('dry-run')) {
                $this->line("  [DRY-RUN] Would expire subscription #{$subscription->id} (business #{$subscription->business_id})");
            } else {
                $subscription->update(['status' => 'expired']);
                $subscription->recordHistory('expired', [
                    'expired_at' => now()->toDateTimeString(),
                    'reason' => 'subscription_end',
                ]);
                $this->line("  Expired subscription #{$subscription->id} (business #{$subscription->business_id})");
            }
        }

        return self::SUCCESS;
    }
}
