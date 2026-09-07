<?php

namespace App\Events;

use App\Models\Pedido;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Pedido $pedido;

    public string $estadoAnterior;

    public string $estadoNuevo;

    /**
     * Create a new event instance.
     */
    public function __construct(Pedido $pedido, string $estadoAnterior, string $estadoNuevo)
    {
        $this->pedido = $pedido;
        $this->estadoAnterior = $estadoAnterior;
        $this->estadoNuevo = $estadoNuevo;
    }
}
