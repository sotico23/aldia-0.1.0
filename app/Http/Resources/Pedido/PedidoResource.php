<?php
/**
 * Pedido Resource - Handles Order/Order processing
 */

namespace App\Http\Resources\Pedido;

use App\Http\Resources\Base\BaseResource;

class PedidoResource extends BaseResource
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get order data
     */
    public function getOrderData(): array
    {
        return $this->getData();
    }

    /**
     * Get minimal order data (for nested relations)
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
                'productos' => $this->products,
                'estado' => $this->estado,
                'total_gastado' => $this->total_gastado,
                'moneda' => $this->moneda,
                'fecha_creacion' => $this->fecha_creacion,
            ],
        ]);
    }
}
