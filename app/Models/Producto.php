<?php
/**
 * Producto Model
 */

namespace App\Models;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\CryptProtectedColumn;

class Producto
{
    /**
     * The product name
     */
    protected ?string $nombre = null;

    /**
     * The product description
     */
    protected ?string $descripcion = null;

    /**
     * The base price of the product
     */
    protected ?decimal $precio_base = null;

    /**
     * Current stock quantity
     */
    protected ?int $stock_unidades = null;

    /**
     * Category ID
     */
    protected ?int $categoria_id = null;

    /**
     * Image URL
     */
    protected ?string $imagen_principal = null;

    /**
     * Stock status
     */
    protected ?string $estado_inventario = null;

    /**
     * Casts method for Eloquent
     */
    public function casts(): array
    {
        return [
            CryptProtectedColumn::class,
            'precio_base',
            'stock_unidades',
        ];
    }

    /**
     * Fill from DB
     */
    public function fillFromDb(): void
    {
        $this->nombre = $this->attributes->get('nombre');
        $this->descripcion = $this->attributes->get('descripcion');
        $this->precio_base = $this->attributes->get('precio_base');
        $this->stock_unidades = $this->attributes->get('stock_unidades');
        $this->categoria_id = $this->attributes->get('categoria_id');
        $this->imagen_principal = $this->attributes->get('imagen_principal');
        $this->estado_inventario = $this->attributes->get('estado_inventario');
    }

    /**
     * Get common fields for nested relations
     */
    public function getCommonFields(): array
    {
        return [
            'id' => $this->id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
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
        ];
    }
}
