<?php

namespace App\Events;

use App\Models\MensajeConversacion;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MensajeEnviado implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public MensajeConversacion $mensaje,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversacion.'.$this->mensaje->conversacion_id),
        ];
    }

    public function broadcastWith(): array
    {
        $data = [
            'id' => $this->mensaje->id,
            'conversacion_id' => $this->mensaje->conversacion_id,
            'sender_id' => $this->mensaje->sender_id,
            'contenido' => $this->mensaje->contenido,
            'created_at' => $this->mensaje->created_at->toISOString(),
            'sender' => [
                'id' => $this->mensaje->sender->id,
                'name' => $this->mensaje->sender->name,
                'profile_photo_path' => $this->mensaje->sender->profile_photo_path,
            ],
        ];

        if ($this->mensaje->file_path) {
            $data['file_url'] = asset('storage/'.$this->mensaje->file_path);
            $data['file_name'] = basename($this->mensaje->file_path);
            $data['is_image'] = in_array(pathinfo($this->mensaje->file_path, PATHINFO_EXTENSION), ['jpg', 'jpeg', 'png', 'gif', 'webp']);
        }

        return $data;
    }
}
