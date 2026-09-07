<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class DeliverySaturacionNotification extends Notification implements ShouldQueue
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
        public int $ownerId,
        public int $pendientes,
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'titulo' => 'Reparto saturado: hay pedidos sin repartidor',
            'message' => "Tienes {$this->pendientes} pedido(s) en el pool esperando repartidor. Revisa las zonas, la capacidad de tus repartidores y su disponibilidad.",
            'tipo' => 'delivery_saturacion',
            'pendientes' => $this->pendientes,
            'link' => url('/zonas-reparto'),
        ];
    }
}
