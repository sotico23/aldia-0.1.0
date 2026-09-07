<?php
/**
 * Tienda Resource - Handles store/shop operations
 */

namespace App\Http\Resources\Tienda;

use App\Http\Resources\Base\BaseResource;

class TiendaResource extends BaseResource
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get store data
     */
    public function getStoreData(): array
    {
        return $this->getData();
    }

    /**
     * Get minimal store data (for nested relations)
     */
    public function getMinimalData(): array
    {
        return $this->addMinimalFields();
    }

    /**
     * Minimal store fields for nested relations
     */
    protected function addMinimalFields(): void
    {
        $this->addCommonFields([
            'id' => null,
            'created_at' => null,
            'updated_at' => null,
            'data' => [
                'id' => $this->id,
                'nombre' => $this->nombre,
                'direccion' => $this->direccion,
                'telefono' => $this->telefono,
                'horario_abierto' => $this->horario_abierto,
                'estado_activo' => $this->estado_activo,
            ],
        ]);
    }
}
