<?php
/**
 * Pedido Minimal Resource - Lightweight representation for nested relations
 */

namespace App\Http\Resources\Pedido;

use App\Http\Resources\Base\BaseResource;

class PedidoMinimalResource extends BaseResource
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get minimal order data
     */
    public function getMinimalData(): array
    {
        return $this->addMinimalFields();
    }

    /**
     * Minimal order fields for nested relations
     */
    protected function addMinimalFields(): void
    {
        $this->addCommonFields([
            'id' => null,
            'created_at' => null,
            'updated_at' => null,
            'data' => [
                'order_id' => $this->order_id,
                'client_id' => $this->client_id,
                'productos' => $this->productos,
                'estado' => $this->estado,
                'total_gastado' => $this->total_gastado,
                'moneda' => $this->moneda,
                'fecha_creacion' => $this->fecha_creacion,
            ],
        ]);
    }
}
