<?php

namespace App\Events;

use App\Models\DeliveryPosition;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Actualizacion de la posicion GPS de un repartidor.
 */
class DeliveryPositionUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ?DeliveryPosition $position = null,
        public ?int $orderId = null,
    ) {}

    /**
     * Canal concreto: `delivery.{ownerId}.positions`.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('delivery.'.$this->position?->owner_id.'.positions'),
        ];
    }

    /**
     * Datos que se difunden por el canal.
     */
    public function broadcastWith(): array
    {
        return [
            'position_id' => $this->position?->id,
            'owner_id' => $this->position?->owner_id,
            'repartidor_id' => $this->position?->repartidor_id,
            'lat' => (float) ($this->position?->lat ?? 0),
            'lng' => (float) ($this->position?->lng ?? 0),
            'created_at' => $this->position?->created_at?->toISOString() ?? now()->toISOString(),
        ];
    }

    /**
     * Estructura canonica del evento.
     */
    public function getEventData(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'type' => 'delivery.position.updated',
            'position_id' => $this->position?->id,
            'order_id' => $this->orderId,
            'driver_id' => $this->position?->repartidor_id,
            'latitude' => (float) ($this->position?->lat ?? 0),
            'longitude' => (float) ($this->position?->lng ?? 0),
            'distance_km' => null,
            'estado_actual' => null,
            'created_at' => $this->position?->created_at?->toISOString() ?? now()->toISOString(),
        ];
    }

    /**
     * Formato del canal (contrato historico).
     */
    public function getChannel(): string
    {
        return 'delivery.orders.{orderId}';
    }

    /**
     * Fabrica de compatibilidad.
     */
    public function create(): DeliveryPositionUpdated
    {
        return new self;
    }
}
