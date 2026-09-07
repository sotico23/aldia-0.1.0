<?php
/**
 * Message Model
 */

namespace App\Models;

use Illuminate\Database\Migrations\Migration;

class Mensaje
{
    /**
     * The message text
     */
    protected ?string $texto = null;

    /**
     * Type of message
     */
    protected ?string $tipo_mensaje = null;

    /**
     * Related conversation
     */
    protected ?int $conversacion_id = null;

    /**
     * Author of the message
     */
    protected ?string $autor = null;

    /**
     * Read status
     */
    protected ?bool $leido = null;

    /**
     * Casts method for Eloquent
     */
    public function casts(): array
    {
        return [
            'tipo_mensaje' => 'tipo_mensaje',
            'leido' => 'leido',
        ];
    }

    /**
     * Fill from DB
     */
    public function fillFromDb(): void
    {
        $this->texto = $this->attributes->get('texto');
        $this->tipo_mensaje = $this->attributes->get('tipo_mensaje');
        $this->conversacion_id = $this->attributes->get('conversacion_id');
        $this->autor = $this->attributes->get('autor');
        $this->leido = $this->attributes->get('leido');
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
                'conversacion_id' => $this->conversacion_id,
                'autor' => $this->autor,
                'texto' => $this->texto,
                'tipo_mensaje' => $this->tipo_mensaje,
                'leido' => $this->leido,
            ],
        ];
    }
}
