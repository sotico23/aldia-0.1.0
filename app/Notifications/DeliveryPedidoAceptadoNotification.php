<?php

namespace App\Notifications;

use App\Models\Pedido;
use App\Models\Repartidor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class DeliveryPedidoAceptadoNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $backoff = 120;

    public function failed(\Throwable $e): void
    {
        \Log::error('Notification failed: '.static::class.': '.$e->getMessage(), [
            'notification_class' => static::class,
            'exception' => $e,
        ]);
    }

    public function __construct(
        public Pedido $pedido,
        public Repartidor $repartidor,
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'titulo' => 'Un repartidor aceptó el pedido #'.$this->pedido->numero_pedido,
            'message' => $this->repartidor->user->name.' aceptó el reparto del pedido #'.$this->pedido->numero_pedido.'.',
            'pedido_id' => $this->pedido->id,
            'repartidor_id' => $this->repartidor->user_id,
            'tipo' => 'delivery_pedido_aceptado',
            'link' => url('/pedidos/'.$this->pedido->id),
        ];
    }
}
