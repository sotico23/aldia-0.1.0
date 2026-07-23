<?php

namespace App\Events;

use App\Models\Pedido;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PedidoCreado
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Pedido $pedido;

    /**
     * Create a new event instance.
     */
    public function __construct(Pedido $pedido)
    {
        $this->pedido = $pedido;
    }
}
