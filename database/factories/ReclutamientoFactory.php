<?php

namespace Database\Factories;

use App\Models\Reclutamiento;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReclutamientoFactory extends Factory
{
    protected $model = Reclutamiento::class;

    public function definition(): array
    {
        return [
            'candidato' => fake()->name(),
            'email' => fake()->safeEmail(),
            'telefono' => fake()->numerify('+56 9 ########'),
            'puesto' => fake()->randomElement(['Desarrollador', 'Diseñador', 'Gerente', 'Analista', 'Técnico']),
            'fecha_postulacion' => fake()->dateTimeBetween('-2 months', 'now'),
            'estado' => fake()->randomElement(['nuevo', 'entrevistado', 'seleccionado', 'rechazado']),
            'observaciones' => fake()->optional()->sentence(),
        ];
    }
}
