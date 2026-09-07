<?php
/**
 * Delivery Order Assigned Event - Triggered when a driver is assigned to an order
 */

namespace App\Events;

use App\Http\Resources\Base\BaseResource;

class DeliveryOrderAssigned implements ShouldBroadcast
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
    public function create(): DeliveryOrderAssigned
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
            'type' => 'delivery.order.assigned',
            'order_id' => $this->order->id,
            'driver_id' => $this->driver->id,
            'assignment_type' => $this->assignment_type,
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }

    /**
     * Get the channel to subscribe to
     */
    public function getChannel(): string
    {
        return 'delivery.driver.{driverId}';
    }
}
