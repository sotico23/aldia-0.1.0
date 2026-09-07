<?php
/**
 * Inventario Resource - Handles inventory management
 */

namespace App\Http\Resources\Inventario;

use App\Http\Resources\Base\BaseResource;

class InventarioResource extends BaseResource
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get inventory data
     */
    public function getInventoryData(): array
    {
        return $this->getData();
    }

    /**
     * Get minimal inventory data (for nested relations)
     */
    public function getMinimalData(): array
    {
        return $this->addMinimalFields();
    }

    /**
     * Minimal inventory fields for nested relations
     */
    protected function addMinimalFields(): void
    {
        $this->addCommonFields([
            'id' => null,
            'created_at' => null,
            'updated_at' => null,
            'data' => [
                'id' => $this->id,
                'producto_id' => $this->producto_id,
                'cantidad_en_inventario' => $this->cantidad_en_inventario,
                'cantidad_en_venta' => $this->cantidad_en_venta,
                'precio_unitario' => $this->precio_unitario,
                'stock_minimo_alert' => $this->stock_minimo_alert,
                'almacenamiento_utilizado' => $this->almacenamiento_utilizado,
            ],
        ]);
    }
}
