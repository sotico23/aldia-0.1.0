<?php

namespace App\Listeners;

use App\Events\WebhookReceived;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class LogWebhookReceived implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;

    public int $backoff = 60;

    public function failed(\Throwable $e): void
    {
        Log::error('LogWebhookReceived listener failed: '.$e->getMessage(), [
            'event' => WebhookReceived::class,
            'exception' => $e,
        ]);
    }

    public function handle(WebhookReceived $event): void
    {
        Log::info('Webhook received', [
            'gateway' => $event->gateway,
            'event_type' => $event->eventType,
            'business_id' => $event->businessId,
            'payload' => $event->payload,
        ]);
    }
}
