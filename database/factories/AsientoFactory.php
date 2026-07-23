<?php

namespace Database\Factories;

use App\Models\Asiento;
use Illuminate\Database\Eloquent\Factories\Factory;

class AsientoFactory extends Factory
{
    protected $model = Asiento::class;

    public function definition(): array
    {
        return [

            'fecha' => fake()->dateTimeBetween('-6 months', 'now'),
            'numero' => fake()->unique()->numerify('ASI-########'),
            'descripcion' => fake()->sentence(),
            'tipo' => fake()->randomElement(['diario', 'mayor', 'cierre', 'apertura']),
            'total_debe' => fake()->randomFloat(2, 10000, 500000),
            'total_haber' => fake()->randomFloat(2, 10000, 500000),
            'estado' => true,
        ];
    }
}
