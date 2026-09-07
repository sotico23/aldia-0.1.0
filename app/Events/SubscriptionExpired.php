<?php

namespace App\Events;

use App\Models\Subscription;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionExpired
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Subscription $subscription;

    public string $expiredAt;

    /**
     * Create a new event instance.
     */
    public function __construct(Subscription $subscription, string $expiredAt)
    {
        $this->subscription = $subscription;
        $this->expiredAt = $expiredAt;
    }
}
