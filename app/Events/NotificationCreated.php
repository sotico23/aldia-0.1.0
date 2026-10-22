<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Notificacion creada para un usuario.
 */
class NotificationCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public mixed $notification = null,
        public ?User $user = null,
    ) {}

    /**
     * Canal concreto: `notifications.{userId}`.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('notifications.'.$this->userId()),
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
        $data = $this->payload();

        return [
            'id' => (string) Str::uuid(),
            'type' => 'notification.created',
            'title' => $data['title'] ?? 'Nueva notificación',
            'message' => $data['message'] ?? $data['body'] ?? '',
            'data' => $data,
            'read_at' => $this->notification?->read_at?->toISOString(),
            'created_at' => $this->notification?->created_at?->toISOString() ?? now()->toISOString(),
        ];
    }

    /**
     * Formato del canal (contrato historico).
     */
    public function getChannel(): string
    {
        return 'notifications.{userId}';
    }

    /**
     * Fabrica de compatibilidad.
     */
    public function create(): NotificationCreated
    {
        return new self;
    }

    protected function userId(): ?int
    {
        $id = $this->user?->id ?? $this->notification?->notifiable_id;

        return $id !== null ? (int) $id : null;
    }

    /**
     * Contenido serializado de la notificacion.
     *
     * @return array<string, mixed>
     */
    protected function payload(): array
    {
        $data = $this->notification?->data ?? [];

        if (is_object($data)) {
            $data = (array) $data;
        }

        return is_array($data) ? $data : [];
    }
}
