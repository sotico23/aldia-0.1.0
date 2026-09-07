<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WebhookReceived
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $gateway;

    public string $eventType;

    public array $payload;

    public ?int $businessId;

    /**
     * Create a new event instance.
     */
    public function __construct(string $gateway, string $eventType, array $payload, ?int $businessId = null)
    {
        $this->gateway = $gateway;
        $this->eventType = $eventType;
        $this->payload = $payload;
        $this->businessId = $businessId;
    }
}
