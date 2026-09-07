<?php

namespace App\Listeners;

use App\Events\OrderStatusChanged;
use App\Helpers\NotificationHelper;
use App\Models\User;
use App\Notifications\ActualizacionEstadoPedidoNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendOrderStatusChangedNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;

    public int $backoff = 60;

    public function failed(\Throwable $e): void
    {
        \Log::error('OrderStatusChanged notification failed: '.$e->getMessage(), [
            'event' => OrderStatusChanged::class,
            'exception' => $e,
        ]);
    }

    /**
     * Handle the event.
     */
    public function handle(OrderStatusChanged $event): void
    {
        $pedido = $event->pedido;
        $cliente = User::find($pedido->cliente_id);

        if ($cliente) {
            $mensajeAdicional = match ($event->estadoNuevo) {
                'confirmado' => 'Tu pedido ha sido confirmado y está siendo preparado.',
                'preparando' => 'Tu pedido está en preparación. Te avisaremos cuando esté listo para envío.',
                'enviado' => 'Tu pedido ha sido enviado. Podrás rastrear tu entrega pronto.',
                'entregado' => 'Tu pedido ha sido entregado. ¡Gracias por tu compra!',
                'cancelado' => 'Tu pedido ha sido cancelado. Contacta al vendedor para más información.',
                default => null,
            };

            NotificationHelper::send($cliente, new ActualizacionEstadoPedidoNotification(
                $pedido,
                $event->estadoAnterior,
                $event->estadoNuevo,
                $mensajeAdicional
            ));
        }
    }
}
