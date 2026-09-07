<?php
/**
 * Notification Model
 */

namespace App\Models;

use Illuminate\Database\Migration\Migration;

class Notification
{
    /**
     * The notification ID
     */
    protected ?int $id = null;

    /**
     * Type of notification
     */
    protected ?string $tipo = null;

    /**
     * Title
     */
    protected ?string $titulo = null;

    /**
     * Message content
     */
    protected ?string $contenido = null;

    /**
     * Status
     */
    protected ?bool $leido = null;

    /**
     * Created at
     */
    protected ?datetime $created_at = null;

    /**
     * Casts method for Eloquent
     */
    public function casts(): array
    {
        return [
            'tipo' => 'tipo',
            'leido' => 'leido',
        ];
    }

    /**
     * Fill from DB
     */
    public function fillFromDb(): void
    {
        $this->id = $this->attributes->get('id');
        $this->tipo = $this->attributes->get('tipo');
        $this->titulo = $this->attributes->get('titulo');
        $this->contenido = $this->attributes->get('contenido');
        $this->leido = $this->attributes->get('leido');
        $this->created_at = $this->attributes->get('created_at');
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
                'tipo' => $this->tipo,
                'titulo' => $this->titulo,
                'contenido' => $this->contenido,
                'leido' => $this->leido,
            ],
        ];
    }
}
