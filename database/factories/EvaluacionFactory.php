<?php

namespace Database\Factories;

use App\Models\Evaluacion;
use Illuminate\Database\Eloquent\Factories\Factory;

class EvaluacionFactory extends Factory
{
    protected $model = Evaluacion::class;

    public function definition(): array
    {
        return [
            'empleado' => 'Empleado '.fake()->numberBetween(1, 50),
            'evaluador' => fake()->name(),
            'fecha' => fake()->dateTimeBetween('-3 months', 'now'),
            'puntuacion' => fake()->numberBetween(1, 5),
            'comentarios' => fake()->paragraph(),
            'tipo' => fake()->randomElement(['desempeno', 'periodica', 'probatoria', '360']),
        ];
    }
}
