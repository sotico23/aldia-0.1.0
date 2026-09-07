<?php
/**
 * Message Sent Event - Triggered when a chat message is sent
 */

namespace App\Events;

use App\Http\Resources\Base\BaseResource;

class MensajeEnviado implements ShouldBroadcast
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Create the event
     */
    public function create(): MensajeEnviado
    {
        return new self();
    }

    /**
     * Get the event data
     */
    public function getEventData(): array
    {
        return [
            'id' => uniqid(),
            'type' => 'chat.message.sent',
            'conversacion_id' => $this->conversacion->id,
            'leader_id' => $this->leader->id,
            'mensaje_id' => $this->mensaje->id,
            'texto' => $this->mensaje->texto,
            'tipo_mensaje' => $this->mensaje->tipo_mensaje,
            'leido_por_usuario' => $this->leido_por_usuario,
            'created_at' => $this->created_at,
        ];
    }

    /**
     * Get the channel to subscribe to
     */
    public function getChannel(): string
    {
        return 'chat.conversacion.{conversationId}';
    }
}
