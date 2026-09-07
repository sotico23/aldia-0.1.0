<?php

namespace App\Events;

use App\Models\Inventario;
use App\Models\Producto;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LowStock
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Producto $producto;

    public Inventario $inventario;

    public int $stockActual;

    public int $stockMinimo;

    /**
     * Create a new event instance.
     */
    public function __construct(Producto $producto, Inventario $inventario, int $stockActual, int $stockMinimo)
    {
        $this->producto = $producto;
        $this->inventario = $inventario;
        $this->stockActual = $stockActual;
        $this->stockMinimo = $stockMinimo;
    }
}
