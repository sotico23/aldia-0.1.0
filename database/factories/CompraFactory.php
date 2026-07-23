<?php

namespace Database\Factories;

use App\Models\Compra;
use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Factories\Factory;

class CompraFactory extends Factory
{
    protected $model = Compra::class;

    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 5000, 500000);
        $iva = $subtotal * 0.19;

        return [
            'numero' => fake()->unique()->numerify('FC-########'),
            'proveedor_id' => Proveedor::factory(),

            'fecha' => fake()->dateTimeBetween('-1 year', 'now'),
            'subtotal' => $subtotal,
            'iva' => $iva,
            'total' => $subtotal + $iva,
            'estado' => fake()->randomElement(['pendiente', 'recibida', 'cancelada']),
            'notas' => fake()->optional()->sentence(),
        ];
    }
}
