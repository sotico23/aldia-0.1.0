<?php
/**
 * Public Profile Resource - Handles user profiles for display
 */

namespace App\Http\Resources\Tienda;

use App\Http\Resources\Base\BaseResource;

class PublicProfileResource extends BaseResource
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get profile data
     */
    public function getPublicData(): array
    {
        return $this->getData();
    }

    /**
     * Get minimal profile data (for nested relations)
     */
    public function getMinimalData(): array
    {
        return $this->addMinimalFields();
    }

    /**
     * Minimal profile fields for nested relations
     */
    protected function addMinimalFields(): void
    {
        $this->addCommonFields([
            'id' => null,
            'created_at' => null,
            'updated_at' => null,
            'data' => [
                'id' => $this->id,
                'nombre_completo' => $this->nombre_completo,
                'email' => $this->email,
                'foto_perfil' => $this->foto_perfil,
                'bio' => $this->bio,
                'ubicacion' => $this->ubicacion,
                'estado_visibilidad' => $this->estado_visibilidad,
            ],
        ]);
    }
}
