<?php
/**
 * Zone Model
 */

namespace App\Models;

use Illuminate\Database\Migrations\Migration;

class Zone
{
    /**
     * The zone name
     */
    protected ?string $nombre_zona = null;

    /**
     * Address
     */
    protected ?string $direccion = null;

    /**
     * Central coordinates
     */
    protected ?array $coordenadas_centrales = null;

    /**
     * Maximum coverage area in km
     */
    protected ?int $alcance_km = null;

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
        $this->nombre_zona = $this->attributes->get('nombre_zona');
        $this->direccion = $this->attributes->get('direccion');
        $this->coordenadas_centrales = $this->attributes->get('coordenadas_centrales');
        $this->alcance_km = $this->attributes->get('alcance_km');
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
            'data' => [
                'id' => $this->id,
                'nombre_zona' => $this->nombre_zona,
                'direccion' => $this->direccion,
                'coordenadas_centrales' => $this->coordenadas_centrales,
                'alcance_km' => $this->alcance_km,
                'estado_activo' => $this->estado_activo,
            ],
        ];
    }
}
