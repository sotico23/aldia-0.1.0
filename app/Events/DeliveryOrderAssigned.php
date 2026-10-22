<?php

namespace App\Events;

use App\Models\Pedido;
use App\Models\Repartidor;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Asignacion de un pedido a un repartidor.
 */
class DeliveryOrderAssigned implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ?Pedido $pedido = null,
        public ?Repartidor $repartidor = null,
        public string $assignmentType = 'auto',
        public string $status = 'asignado',
    ) {}

    /**
     * Canal concreto: `delivery.driver.{driverId}`.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('delivery.driver.'.$this->driverId()),
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
            'type' => 'delivery.order.assigned',
            'order_id' => $this->pedido?->id,
            'driver_id' => $this->driverId(),
            'assignment_type' => $this->assignmentType,
            'status' => $this->status,
            'created_at' => $this->pedido?->created_at?->toISOString() ?? now()->toISOString(),
        ];
    }

    /**
     * Formato del canal (contrato historico).
     */
    public function getChannel(): string
    {
        return 'delivery.driver.{driverId}';
    }

    /**
     * Fabrica de compatibilidad.
     */
    public function create(): DeliveryOrderAssigned
    {
        return new self;
    }

    protected function driverId(): ?int
    {
        $id = $this->repartidor?->id ?? $this->pedido?->repartidor_id;

        return $id !== null ? (int) $id : null;
    }
}
