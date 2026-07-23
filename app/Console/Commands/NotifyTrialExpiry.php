<?php

namespace App\Console\Commands;

use App\Helpers\NotificationHelper;
use App\Models\User;
use App\Notifications\TrialExpiryNotification;
use Illuminate\Console\Command;

class NotifyTrialExpiry extends Command
{
    protected $signature = 'trial:notify';

    protected $description = 'Send trial expiry notifications to users (7, 3, and 0 days)';

    public function handle(): int
    {
        $notifyDays = [7, 3, 0];
        $notified = 0;

        foreach ($notifyDays as $days) {
            $targetDate = now()->addDays($days)->toDateString();

            $users = User::whereHas('roles', function ($q) {
                $q->where('name', 'Usuario');
            })
                ->whereNotNull('trial_ends_at')
                ->whereDate('trial_ends_at', $targetDate)
                ->get();

            foreach ($users as $user) {
                if ($this->alreadyNotifiedToday($user, $days)) {
                    continue;
                }

                NotificationHelper::send($user, new TrialExpiryNotification($days));
                $notified++;
            }
        }

        $this->info("Trial notifications sent: {$notified}");

        return self::SUCCESS;
    }

    private function alreadyNotifiedToday(User $user, int $daysRemaining): bool
    {
        $type = $daysRemaining <= 0 ? 'trial_expiry' : 'trial_warning';

        return $user->notifications()
            ->where('type', TrialExpiryNotification::class)
            ->whereDate('created_at', today())
            ->whereJsonContains('data->tipo', $type)
            ->exists();
    }
}
