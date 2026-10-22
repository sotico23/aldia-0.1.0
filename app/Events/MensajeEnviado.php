<?php

namespace App\Events;

use App\Models\Conversacion;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Mensaje de chat (pedido) enviado.
 *
 * El payload cubre tanto `App\Models\Mensaje` (tabla `mensajes`) como
 * `App\Models\MensajeConversacion` (tabla `mensajes_conversacion`), por eso
 * el mensaje se tipa como `mixed`.
 */
class MensajeEnviado implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public mixed $mensaje = null,
        public mixed $leader = null,
    ) {}

    /**
     * Canal concreto al que se emite el evento.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.conversation.'.$this->conversacionId()),
        ];
    }

    /**
     * Datos que se difunden por el canal.
     */
    public function broadcastWith(): array
    {
        return $this->getEventData();
    }

    /**
     * Estructura canonica del evento.
     */
    public function getEventData(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'type' => 'chat.message.sent',
            'conversacion_id' => $this->conversacionId(),
            'leader_id' => $this->leaderId(),
            'mensaje_id' => $this->mensaje?->id,
            'texto' => $this->mensaje?->texto ?? $this->mensaje?->contenido,
            'tipo_mensaje' => $this->mensaje?->tipo_mensaje ?? 'texto',
            'leido_por_usuario' => (bool) ($this->mensaje?->leido ?? false),
            'created_at' => $this->mensaje?->created_at?->toISOString() ?? now()->toISOString(),
        ];
    }

    /**
     * Formato del canal (contrato historico).
     */
    public function getChannel(): string
    {
        return 'chat.conversation.{conversationId}';
    }

    /**
     * Fabrica de compatibilidad.
     */
    public function create(): MensajeEnviado
    {
        return new self;
    }

    protected function conversacionId(): ?int
    {
        $id = $this->mensaje?->conversacion_id;

        return $id !== null ? (int) $id : null;
    }

    protected function leaderId(): ?int
    {
        $id = $this->leader?->id
            ?? $this->mensaje?->sender_id
            ?? $this->mensaje?->autor;

        return $id !== null ? (int) $id : null;
    }

    /**
     * Conversacion asociada (solo para la autorizacion del canal).
     */
    public function conversacion(): ?Conversacion
    {
        $id = $this->conversacionId();

        return $id !== null ? Conversacion::find($id) : null;
    }
}
