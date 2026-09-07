<?php

namespace App\Events;

use App\Models\Subscription;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionRenewed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Subscription $subscription;

    public string $previousEndsAt;

    public string $newEndsAt;

    /**
     * Create a new event instance.
     */
    public function __construct(Subscription $subscription, string $previousEndsAt, string $newEndsAt)
    {
        $this->subscription = $subscription;
        $this->previousEndsAt = $previousEndsAt;
        $this->newEndsAt = $newEndsAt;
    }
}
