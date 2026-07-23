<?php

namespace Database\Factories;

use App\Models\GastoProyecto;
use Illuminate\Database\Eloquent\Factories\Factory;

class GastoProyectoFactory extends Factory
{
    protected $model = GastoProyecto::class;

    public function definition(): array
    {
        return [
            'proyecto' => 'Proyecto '.fake()->numberBetween(1, 50),
            'descripcion' => fake()->sentence(),
            'categoria' => fake()->randomElement(['materiales', 'mano_obra', 'equipos', 'transporte', 'otro']),
            'monto' => fake()->numberBetween(1000, 1000000), // Standardizing to whole numbers as per project rules if applicable, though migration is decimal.
            'fecha' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'estado' => fake()->randomElement(['pendiente', 'aprobado', 'rechazado']),
        ];
    }
}
