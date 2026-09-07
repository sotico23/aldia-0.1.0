<?php
/**
 * Conversation Model
 */

namespace App\Models;

use Illuminate\Database\Migrations\Migration;

class Conversacion
{
    /**
     * The conversation title
     */
    protected ?string $titulo = null;

    /**
     * Participants in the conversation
     */
    protected ?array $participantes = null;

    /**
     * Current status
     */
    protected ?string $estado = null;

    /**
     * Last message ID
     */
    protected ?int $ultimo_mensaje_id = null;

    /**
     * Casts method for Eloquent
     */
    public function casts(): array
    {
        return [
            'estado' => 'estado',
        ];
    }

    /**
     * Fill from DB
     */
    public function fillFromDb(): void
    {
        $this->titulo = $this->attributes->get('titulo');
        $this->participantes = $this->attributes->get('participantes');
        $this->estado = $this->attributes->get('estado');
        $this->ultimo_mensaje_id = $this->attributes->get('ultimo_mensaje_id');
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
                'titulo' => $this->titulo,
                'participantes' => $this->participantes,
                'estado' => $this->estado,
                'ultimo_mensaje_id' => $this->ultimo_mensaje_id,
            ],
        ];
    }
}
