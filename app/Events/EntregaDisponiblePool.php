<?php

namespace App\Events;

use App\Models\Entrega;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Entrega liberada a la bolsa de ofertas (pool) para que cualquier repartidor
 * conectado pueda aceptarla desde la app móvil.
 */
class EntregaDisponiblePool implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Entrega $entrega,
        public string $motivo = 'nuevo',
    ) {}

    /**
     * Canal privado: `delivery.pool.{ownerId}`.
     * Cualquier repartidor del mismo tenant puede suscribirse.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('delivery.pool.' . $this->entrega->owner_id),
        ];
    }

    /**
     * Nombre del evento para clientes Pusher / Reverb.
     */
    public function broadcastAs(): string
    {
        return 'delivery.pool.available';
    }

    /**
     * Datos que se difunden por el canal.
     */
    public function broadcastWith(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'type' => 'delivery.pool.available',
            'entrega_id' => $this->entrega->id,
            'venta_id' => $this->entrega->venta_id,
            'cliente' => $this->entrega->cliente,
            'direccion' => $this->entrega->direccion,
            'comuna' => $this->entrega->comuna,
            'fecha_entrega' => $this->entrega->fecha_entrega?->toISOString(),
            'estado' => $this->entrega->estado,
            'estado_asignacion' => $this->entrega->estado_asignacion,
            'repartidor_id' => $this->entrega->repartidor_id,
            'conductor_id' => $this->entrega->conductor_id,
            'vehiculo_id' => $this->entrega->vehiculo_id,
            'motivo' => $this->motivo,
            'created_at' => $this->entrega->created_at?->toISOString() ?? now()->toISOString(),
        ];
    }

    /**
     * Nombre del canal para referencia en el cliente móvil.
     */
    public function getChannel(): string
    {
        return 'delivery.pool.' . $this->entrega->owner_id;
    }
}
