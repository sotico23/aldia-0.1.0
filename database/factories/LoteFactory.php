<?php

namespace Database\Factories;

use App\Models\Lote;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoteFactory extends Factory
{
    protected $model = Lote::class;

    public function definition(): array
    {
        return [
            'numero_lote' => fake()->unique()->bothify('LT-####-????'),
            'producto' => 'Producto '.fake()->numberBetween(1, 50),
            'cantidad' => fake()->numberBetween(10, 500),
            'fecha_vencimiento' => fake()->dateTimeBetween('now', '+2 years'),
            'estado' => fake()->randomElement(['disponible', 'reservado', 'agotado', 'vencido']),
        ];
    }
}
