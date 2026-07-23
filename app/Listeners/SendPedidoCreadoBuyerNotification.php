<?php

namespace App\Listeners;

use App\Events\PedidoCreado;
use App\Helpers\NotificationHelper;
use App\Notifications\PedidoCreadoCompradorNotification;

class SendPedidoCreadoBuyerNotification
{
    public function handle(PedidoCreado $event): void
    {
        $pedido = $event->pedido;
        $buyer = $pedido->user;

        if ($buyer) {
            NotificationHelper::send($buyer, new PedidoCreadoCompradorNotification($pedido));
        }
    }
}
