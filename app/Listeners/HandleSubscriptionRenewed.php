<?php

namespace App\Listeners;

use App\Events\SubscriptionRenewed;
use App\Helpers\NotificationHelper;
use App\Models\User;
use App\Notifications\SubscriptionRenewedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class HandleSubscriptionRenewed implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;

    public int $backoff = 60;

    public function failed(\Throwable $e): void
    {
        \Log::error('SubscriptionRenewed notification failed: '.$e->getMessage(), [
            'event' => SubscriptionRenewed::class,
            'exception' => $e,
        ]);
    }

    public function handle(SubscriptionRenewed $event): void
    {
        $subscription = $event->subscription;
        $user = User::find($subscription->user_id);

        if ($user) {
            NotificationHelper::send($user, new SubscriptionRenewedNotification(
                $subscription,
                $event->previousEndsAt,
                $event->newEndsAt
            ));
        }
    }
}
