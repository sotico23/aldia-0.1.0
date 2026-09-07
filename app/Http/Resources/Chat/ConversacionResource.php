<?php
/**
 * Conversacion Resource - Handles chat conversations
 */

namespace App\Http\Resources\Chat;

use App\Http\Resources\Base\BaseResource;

class ConversacionResource extends BaseResource
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get conversation data
     */
    public function getConversationData(): array
    {
        return $this->getData();
    }

    /**
     * Get minimal conversation data (for nested relations)
     */
    public function getMinimalData(): array
    {
        return $this->addMinimalFields();
    }

    /**
     * Minimal conversation fields for nested relations
     */
    protected function addMinimalFields(): void
    {
        $this->addCommonFields([
            'id' => null,
            'created_at' => null,
            'updated_at' => null,
            'data' => [
                'id' => $this->id,
                'titulo' => $this->titulo,
                'participantes' => $this->participantes,
                'estado' => $this->estado,
                'ultimo_mensaje_id' => $this->ultimo_mensaje_id,
            ],
        ]);
    }
}
