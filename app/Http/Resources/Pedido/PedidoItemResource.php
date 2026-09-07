<?php
/**
 * Pedido Item Resource - Handles individual items within an order
 */

namespace App\Http\Resources\Pedido;

use App\Http\Resources\Base\BaseResource;

class PedidoItemResource extends BaseResource
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get item data
     */
    public function getItemData(): array
    {
        return $this->getData();
    }

    /**
     * Get minimal item data (for nested relations)
     */
    public function getMinimalData(): array
    {
        return $this->addMinimalFields();
    }

    /**
     * Minimal item fields for nested relations
     */
    protected function addMinimalFields(): void
    {
        $this->addCommonFields([
            'id' => null,
            'created_at' => null,
            'updated_at' => null,
            'data' => [
                'item_id' => $this->item_id,
                'producto_id' => $this->producto_id,
                'cantidad' => $this->cantidad,
                'precio_unitario' => $this->precio_unitario,
                'subtotal' => $this->subtotal,
            ],
        ]);
    }
}
