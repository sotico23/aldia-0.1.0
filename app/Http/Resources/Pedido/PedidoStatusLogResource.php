<?php
/**
 * Pedido Status Log Resource - Tracks order status changes
 */

namespace App\Http\Resources\Pedido;

use App\Http\Resources\Base\BaseResource;

class PedidoStatusLogResource extends BaseResource
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get status log data
     */
    public function getStatusLogData(): array
    {
        return $this->getData();
    }

    /**
     * Get minimal status log data (for nested relations)
     */
    public function getMinimalData(): array
    {
        return $this->addMinimalFields();
    }

    /**
     * Minimal status log fields for nested relations
     */
    protected function addMinimalFields(): void
    {
        $this->addCommonFields([
            'id' => null,
            'created_at' => null,
            'updated_at' => null,
            'data' => [
                'pedido_id' => $this->pedido_id,
                'estado_original' => $this->estado_original,
                'nuevo_estado' => $this->nuevo_estado,
                'descripcion_cambio' => $this->descripcion_cambio,
                'usuario_realizado' => $this->usuario_realizado,
            ],
        ]);
    }
}
