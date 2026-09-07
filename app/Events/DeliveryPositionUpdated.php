<?php
/**
 * Delivery Position Updated Event - Triggered when a delivery position is updated
 */

namespace App\Events;

use App\Http\Resources\Base\BaseResource;

class DeliveryPositionUpdated implements ShouldBroadcast
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Create the event
     */
    public function create(): DeliveryPositionUpdated
    {
        return new self();
    }

    /**
     * Get the event data
     */
    public function getEventData(): array
    {
        return [
            'id' => uniqid(),
            'type' => 'delivery.position.updated',
            'position_id' => $this->position->id,
            'order_id' => $this->order->id,
            'driver_id' => $this->driver->id,
            'latitude' => $this->position->latitude,
            'longitude' => $this->position->longitude,
            'distance_km' => $this->position->distancia_km,
            'estado_actual' => $this->position->estado_actual,
            'created_at' => $this->created_at,
        ];
    }

    /**
     * Get the channel to subscribe to
     */
    public function getChannel(): string
    {
        return 'delivery.orders.{orderId}';
    }
}
