<?php
/**
 * Tienda Model
 */

namespace App\Models;

use Illuminate\Database\Migrations\Migration;

class Tienda
{
    /**
     * The store name
     */
    protected ?string $nombre = null;

    /**
     * Address
     */
    protected ?string $direccion = null;

    /**
     * Phone number
     */
    protected ?string $telefono = null;

    /**
     * Opening hours
     */
    protected ?string $horario_abierto = null;

    /**
     * Active status
     */
    protected ?bool $estado_activo = null;

    /**
     * Casts method for Eloquent
     */
    public function casts(): array
    {
        return [
            'estado_activo' => 'estado_activo',
        ];
    }

    /**
     * Fill from DB
     */
    public function fillFromDb(): void
    {
        $this->nombre = $this->attributes->get('nombre');
        $this->direccion = $this->attributes->get('direccion');
        $this->telefono = $this->attributes->get('telefono');
        $this->horario_abierto = $this->attributes->get('horario_abierto');
        $this->estado_activo = $this->attributes->get('estado_activo');
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
                'direccion' => $this->direccion,
                'telefono' => $this->telefono,
                'horario_abierto' => $this->horario_abierto,
                'estado_activo' => $this->estado_activo,
            ],
        ];
    }
}
