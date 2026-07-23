<?php

namespace Database\Factories;

use App\Models\Compra;
use App\Models\DetalleCompra;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;

class DetalleCompraFactory extends Factory
{
    protected $model = DetalleCompra::class;

    public function definition(): array
    {
        $cantidad = fake()->numberBetween(1, 50);
        $precio = fake()->randomFloat(2, 1000, 50000);

        return [
            'compra_id' => Compra::factory(),
            'producto_id' => Producto::factory(),
            'cantidad' => $cantidad,
            'precio_unitario' => $precio,
            'subtotal' => $cantidad * $precio,
        ];
    }
}
