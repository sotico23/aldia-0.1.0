<?php
/**
 * Producto Resource - Handles product/catalog items
 */

namespace App\Http\Resources\Producto;

use App\Http\Resources\Base\BaseResource;

class ProductoResource extends BaseResource
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get product data
     */
    public function getProductData(): array
    {
        return $this->getData();
    }

    /**
     * Get minimal product data (for nested relations)
     */
    public function getMinimalData(): array
    {
        return $this->addMinimalFields();
    }

    /**
     * Minimal product fields for nested relations
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
                'descripcion' => $this->descripcion,
                'precio_base' => $this->precio_base,
                'stock_unidades' => $this->stock_unidades,
                'categoria_id' => $this->categoria_id,
                'imagen_principal' => $this->imagen_principal,
                'estado_inventario' => $this->estado_inventario,
            ],
        ]);
    }
}
