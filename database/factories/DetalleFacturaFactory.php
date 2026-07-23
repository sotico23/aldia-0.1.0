<?php

namespace Database\Factories;

use App\Models\DetalleFactura;
use App\Models\Factura;
use Illuminate\Database\Eloquent\Factories\Factory;

class DetalleFacturaFactory extends Factory
{
    protected $model = DetalleFactura::class;

    public function definition(): array
    {
        $cantidad = fake()->numberBetween(1, 10);
        $precio = fake()->randomFloat(2, 5000, 200000);

        return [
            'factura_id' => Factura::factory(),
            'descripcion' => 'Producto/Servicio '.fake()->numberBetween(1, 100),
            'cantidad' => $cantidad,
            'precio_unitario' => $precio,
            'subtotal' => $cantidad * $precio,
        ];
    }
}
