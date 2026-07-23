<?php

namespace App\Events;

use App\Models\Mensaje;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MensajeInternoEnviado implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Mensaje $mensaje,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->mensaje->receiver_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->mensaje->id,
            'sender_id' => $this->mensaje->sender_id,
            'contenido' => $this->mensaje->contenido,
            'created_at' => $this->mensaje->created_at->toISOString(),
            'sender' => [
                'id' => $this->mensaje->sender->id,
                'name' => $this->mensaje->sender->name,
                'profile_photo_path' => $this->mensaje->sender->profile_photo_path,
            ],
        ];
    }
}
