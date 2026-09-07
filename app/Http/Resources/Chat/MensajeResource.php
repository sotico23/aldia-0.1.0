<?php
/**
 * Mensaje Resource - Handles chat messages
 */

namespace App\Http\Resources\Chat;

use App\Http\Resources\Base\BaseResource;

class MensajeResource extends BaseResource
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get message data
     */
    public function getMessageData(): array
    {
        return $this->getData();
    }

    /**
     * Get minimal message data (for nested relations)
     */
    public function getMinimalData(): array
    {
        return $this->addMinimalFields();
    }

    /**
     * Minimal message fields for nested relations
     */
    protected function addMinimalFields(): void
    {
        $this->addCommonFields([
            'id' => null,
            'created_at' => null,
            'updated_at' => null,
            'data' => [
                'id' => $this->id,
                'conversacion_id' => $this->conversacion_id,
                'autor' => $this->autor,
                'texto' => $this->texto,
                'tipo_mensaje' => $this->tipo_mensaje,
                'lectura_por_usuario' => $this->lectura_por_usuario,
            ],
        ]);
    }
}
