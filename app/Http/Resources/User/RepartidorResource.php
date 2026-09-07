<?php
/**
 * Repartidor Resource - Handles Driver/Delivery personnel operations
 */

namespace App\Http\Resources\User;

use App\Http\Resources\Base\BaseResource;

class RepartidorResource extends BaseResource
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get driver data
     */
    public function getDriverData(): array
    {
        return $this->getData();
    }

    /**
     * Get minimal driver data (for nested relations)
     */
    public function getMinimalData(): array
    {
        return $this->addMinimalFields();
    }

    /**
     * Minimal driver fields for nested relations
     */
    protected function addMinimalFields(): void
    {
        $this->addCommonFields([
            'id' => null,
            'created_at' => null,
            'updated_at' => null,
            'data' => [
                'driver_id' => $this->driver_id,
                'name' => $this->name,
                'phone' => $this->phone,
                'email' => $this->email,
                'license_number' => $this->license_number,
                'vehicle_id' => $this->vehicle_id,
                'status' => $this->status,
                'assigned_count' => $this->assigned_count,
            ],
        ]);
    }
}
