<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MensajesLeidos implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $conversacionId,
        public int $userId,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversacion.'.$this->conversacionId),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'conversacion_id' => $this->conversacionId,
            'leido_por' => $this->userId,
        ];
    }
}
