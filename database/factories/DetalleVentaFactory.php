<?php

namespace Database\Factories;

use App\Models\DetalleVenta;
use App\Models\Producto;
use App\Models\Venta;
use Illuminate\Database\Eloquent\Factories\Factory;

class DetalleVentaFactory extends Factory
{
    protected $model = DetalleVenta::class;

    public function definition(): array
    {
        $cantidad = fake()->randomFloat(3, 0.100, 20);
        $precio = fake()->randomFloat(2, 5000, 100000);

        return [
            'venta_id' => Venta::factory(),
            'producto_id' => Producto::factory(),
            'cantidad' => $cantidad,
            'precio_unitario' => $precio,
            'subtotal' => $cantidad * $precio,
        ];
    }
}
