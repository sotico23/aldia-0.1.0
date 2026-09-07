<?php

namespace App\Events;

use App\Models\Pedido;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeliveryOrderPoolUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Pedido $pedido,
        public string $motivo,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('delivery.'.$this->pedido->owner_id.'.orders'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'pedido_id' => $this->pedido->id,
            'numero_pedido' => $this->pedido->numero_pedido,
            'estado' => $this->pedido->estado,
            'repartidor_id' => $this->pedido->repartidor_id,
            'motivo' => $this->motivo,
        ];
    }
}
