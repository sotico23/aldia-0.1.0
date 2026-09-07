<?php
/**
 * Delivery Position Resource - Handles real-time delivery positions
 */

namespace App\Http\Resources\Delivery;

use App\Http\Resources\Base\BaseResource;

class DeliveryPositionResource extends BaseResource
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get delivery position data
     */
    public function getDeliveryPositionData(): array
    {
        return $this->getData();
    }

    /**
     * Get minimal delivery position data (for nested relations)
     */
    public function getMinimalData(): array
    {
        return $this->addMinimalFields();
    }

    /**
     * Minimal delivery position fields for nested relations
     */
    protected function addMinimalFields(): void
    {
        $this->addCommonFields([
            'id' => null,
            'created_at' => null,
            'updated_at' => null,
            'data' => [
                'id' => $this->id,
                'orden' => $this->orden,
                'estado_actual' => $this->estado_actual,
                'cliente_id' => $this->cliente_id,
                'repartidor_id' => $this->repartidor_id,
                'posicion_actual' => $this->posicion_actual,
                'latitud' => $this->latitud,
                'longitud' => $this->longitud,
            ],
        ]);
    }
}
