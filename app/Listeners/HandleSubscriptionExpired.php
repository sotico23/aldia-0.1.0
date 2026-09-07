<?php

namespace App\Listeners;

use App\Events\SubscriptionExpired;
use App\Helpers\NotificationHelper;
use App\Models\User;
use App\Notifications\SubscriptionExpiredNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class HandleSubscriptionExpired implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;

    public int $backoff = 60;

    public function failed(\Throwable $e): void
    {
        \Log::error('SubscriptionExpired notification failed: '.$e->getMessage(), [
            'event' => SubscriptionExpired::class,
            'exception' => $e,
        ]);
    }

    public function handle(SubscriptionExpired $event): void
    {
        $subscription = $event->subscription;
        $user = User::find($subscription->user_id);

        if ($user) {
            NotificationHelper::send($user, new SubscriptionExpiredNotification(
                $subscription,
                $event->expiredAt
            ));
        }
    }
}
