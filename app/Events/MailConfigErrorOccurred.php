<?php

namespace App\Events;

use App\Models\MailConfig;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MailConfigErrorOccurred
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public MailConfig $config,
        public string $error,
        public ?string $destinatario = null
    ) {}

    public function broadcastOn(): array
    {
        return [];
    }
}
