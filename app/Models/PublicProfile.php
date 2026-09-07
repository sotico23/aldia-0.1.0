<?php
/**
 * Public Profile Model
 */

namespace App\Models;

use Illuminate\Database\Migrations\Migration;

class PublicProfile
{
    /**
     * Full name
     */
    protected ?string $nombre_completo = null;

    /**
     * Email
     */
    protected ?string $email = null;

    /**
     * Profile picture URL
     */
    protected ?string $foto_perfil = null;

    /**
     * Bio
     */
    protected ?string $bio = null;

    /**
     * Location
     */
    protected ?string $ubicacion = null;

    /**
     * Visibility status
     */
    protected ?string $estado_visibilidad = null;

    /**
     * Casts method for Eloquent
     */
    public function casts(): array
    {
        return [
            'estado_visibilidad' => 'estado_visibilidad',
        ];
    }

    /**
     * Fill from DB
     */
    public function fillFromDb(): void
    {
        $this->nombre_completo = $this->attributes->get('nombre_completo');
        $this->email = $this->attributes->get('email');
        $this->foto_perfil = $this->attributes->get('foto_perfil');
        $this->bio = $this->attributes->get('bio');
        $this->ubicacion = $this->attributes->get('ubicacion');
        $this->estado_visibilidad = $this->attributes->get('estado_visibilidad');
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
                'nombre_completo' => $this->nombre_completo,
                'email' => $this->email,
                'foto_perfil' => $this->foto_perfil,
                'bio' => $this->bio,
                'ubicacion' => $this->ubicacion,
                'estado_visibilidad' => $this->estado_visibilidad,
            ],
        ];
    }
}
