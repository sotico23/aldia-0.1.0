<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CommunicationReadReceipt implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $type,
        public int $threadId,
        public int $userId,
    ) {}

    public function broadcastOn(): array
    {
        $channelName = match ($this->type) {
            'order' => 'conversacion.'.$this->threadId,
            'marketplace' => 'conversation.'.$this->threadId,
            default => 'communication.'.$this->threadId,
        };

        return [new PrivateChannel($channelName)];
    }

    public function broadcastAs(): string
    {
        return 'CommunicationReadReceipt';
    }
}
