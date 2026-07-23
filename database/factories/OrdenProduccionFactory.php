<?php

namespace Database\Factories;

use App\Models\OrdenProduccion;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrdenProduccionFactory extends Factory
{
    protected $model = OrdenProduccion::class;

    public function definition(): array
    {
        return [
            'numero' => fake()->unique()->numerify('OP-########'),
            'producto' => 'Producto Fabricado '.fake()->numberBetween(1000, 9999),
            'cantidad' => fake()->numberBetween(10, 500),
            'fecha_inicio' => fake()->dateTimeBetween('-1 month', 'now'),
            'fecha_fin' => fake()->dateTimeBetween('now', '+1 month'),
            'progreso' => fake()->numberBetween(0, 100),
            'estado' => fake()->randomElement(['pendiente', 'en_proceso', 'en_proceso', 'completada', 'cancelada']),
            'notas' => fake()->optional()->sentence(),
        ];
    }
}
