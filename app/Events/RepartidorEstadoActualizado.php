<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Notifica a un repartidor cuando su solicitud es aprobada o rechazada.
 */
class RepartidorEstadoActualizado implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public User $usuario,
        public string $estado, // 'aprobado' | 'rechazado'
        public string $mensaje,
    ) {}

    /**
     * Canal privado: notifications.{userId}
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('notifications.'.$this->usuario->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'repartidor.estado.actualizado';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'repartidor.estado.actualizado',
            'user_id' => $this->usuario->id,
            'nombre' => $this->usuario->name,
            'email' => $this->usuario->email,
            'estado' => $this->estado,
            'mensaje' => $this->mensaje,
            'created_at' => now()->toISOString(),
        ];
    }
}
