<?php
/**
 * Zone Resource - Handles delivery zones/regions
 */

namespace App\Http\Resources\Delivery;

use App\Http\Resources\Base\BaseResource;

class ZoneResource extends BaseResource
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get zone data
     */
    public function getZoneData(): array
    {
        return $this->getData();
    }

    /**
     * Get minimal zone data (for nested relations)
     */
    public function getMinimalData(): array
    {
        return $this->addMinimalFields();
    }

    /**
     * Minimal zone fields for nested relations
     */
    protected function addMinimalFields(): void
    {
        $this->addCommonFields([
            'id' => null,
            'created_at' => null,
            'updated_at' => null,
            'data' => [
                'id' => $this->id,
                'nombre_zona' => $this->nombre_zona,
                'direccion' => $this->direccion,
                'coordenadas_centrales' => $this->coordenadas_centrales,
                'alcance_km' => $this->alcance_km,
                'estado_activo' => $this->estado_activo,
            ],
        ]);
    }
}
