<?php
/**
 * Categoria Resource - Handles product categories
 */

namespace App\Http\Resources\Producto;

use App\Http\Resources\Base\BaseResource;

class CategoriaResource extends BaseResource
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get category data
     */
    public function getCategoryData(): array
    {
        return $this->getData();
    }

    /**
     * Get minimal category data (for nested relations)
     */
    public function getMinimalData(): array
    {
        return $this->addMinimalFields();
    }

    /**
     * Minimal category fields for nested relations
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
                'categoria_id' => $this->categoria_id,
                'ordene_total' => $this->ordene_total,
                'stock_total' => $this->stock_total,
                'imagen_principal' => $this->imagen_principal,
            ],
        ]);
    }
}
