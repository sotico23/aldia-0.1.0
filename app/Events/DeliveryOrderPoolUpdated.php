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
            new PrivateChannel('delivery.pool.'.$this->pedido->owner_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'delivery.pool.available';
    }

    public function broadcastWith(): array
    {
        return [
            'type' => 'delivery.pool.available',
            'pedido_id' => $this->pedido->id,
            'numero_pedido' => $this->pedido->numero_pedido,
            'estado' => $this->pedido->estado,
            'dispatch_mode' => $this->pedido->dispatch_mode ?? 'internal',
            'cliente' => $this->pedido->nombre_cliente,
            'nombre_cliente' => $this->pedido->nombre_cliente,
            'direccion' => $this->pedido->direccion_cliente,
            'direccion_cliente' => $this->pedido->direccion_cliente,
            'telefono' => $this->pedido->telefono_cliente,
            'total' => (float) $this->pedido->total,
            'costo_envio' => (float) ($this->pedido->costo_envio ?? 0),
            'ganancia_estimada' => (float) ($this->pedido->ganancia_repartidor ?? 0),
            'distancia_km' => $this->pedido->distancia_km ? (float) $this->pedido->distancia_km : null,
            'repartidor_id' => $this->pedido->repartidor_id,
            'motivo' => $this->motivo,
            'created_at' => now()->toISOString(),
        ];
    }
}
