<?php

namespace Database\Factories;

use App\Models\Conductor;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConductorFactory extends Factory
{
    protected $model = Conductor::class;

    public function definition(): array
    {
        return [
            'nombre' => fake()->name(),
            'rut' => fake()->unique()->numerify('##.###.###-#'),
            'telefono' => fake()->numerify('+56 9 ########'),
            'email' => fake()->safeEmail(),
            'licencia' => fake()->randomElement(['A1', 'A2', 'A3', 'A4', 'B']),
            'fecha_vencimiento_licencia' => fake()->dateTimeBetween('now', '+5 years'),
            'estado' => fake()->randomElement(['activo', 'inactivo', 'licencia_vencida']),
        ];
    }
}
