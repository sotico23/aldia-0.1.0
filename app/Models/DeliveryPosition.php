<?php
/**
 * Delivery Position Model
 */

namespace App\Models;

use Illuminate\Database\Migration\Migration;

class DeliveryPosition
{
    /**
     * The position ID
     */
    protected ?int $id = null;

    /**
     * Associated order ID
     */
    protected ?int $order_id = null;

    /**
     * Driver ID
     */
    protected ?int $driver_id = null;

    /**
     * Current status
     */
    protected ?string $estado_actual = null;

    /**
     * Latitude
     */
    protected ?float $latitud = null;

    /**
     * Longitude
     */
    protected ?float $longitud = null;

    /**
     * Casts method for Eloquent
     */
    public function casts(): array
    {
        return [
            'estado_actual' => 'estado_actual',
            'latitud' => 'latitud',
            'longitud' => 'longitud',
        ];
    }

    /**
     * Fill from DB
     */
    public function fillFromDb(): void
    {
        $this->id = $this->attributes->get('id');
        $this->order_id = $this->attributes->get('order_id');
        $this->driver_id = $this->attributes->get('driver_id');
        $this->estado_actual = $this->attributes->get('estado_actual');
        $this->latitud = $this->attributes->get('latitud');
        $this->longitud = $this->attributes->get('longitud');
    }

    /**
     * Get common fields for nested relations
     */
    public function getCommonFields(): array
    {
        return [
            'id' => $this->id,
            'created_at' => $this->created_at,
            'data' => [
                'id' => $this->id,
                'order_id' => $this->order_id,
                'driver_id' => $this->driver_id,
                'estado_actual' => $this->estado_actual,
                'latitud' => $this->latitud,
                'longitud' => $this->longitud,
            ],
        ];
    }
}
