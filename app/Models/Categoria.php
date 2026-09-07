<?php
/**
 * Categoria Model
 */

namespace App\Models;

use Illuminate\Database\Migrations\Migration;

class Categoria
{
    /**
     * The category name
     */
    protected ?string $nombre = null;

    /**
     * Description
     */
    protected ?string $descripcion = null;

    /**
     * Casts method for Eloquent
     */
    public function casts(): array
    {
        return [];
    }

    /**
     * Fill from DB
     */
    public function fillFromDb(): void
    {
        $this->nombre = $this->attributes->get('nombre');
        $this->descripcion = $this->attributes->get('descripcion');
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
            ],
        ];
    }
}
