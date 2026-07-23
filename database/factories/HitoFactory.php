<?php

namespace Database\Factories;

use App\Models\Hito;
use Illuminate\Database\Eloquent\Factories\Factory;

class HitoFactory extends Factory
{
    protected $model = Hito::class;

    public function definition(): array
    {
        return [
            'proyecto_id' => fake()->numberBetween(1, 50),
            'nombre' => 'Hito '.fake()->numberBetween(1, 10),
            'descripcion' => fake()->sentence(),
            'fecha_vencimiento' => fake()->dateTimeBetween('-1 month', '+3 months'),
            'estado' => fake()->randomElement(['pendiente', 'en_progreso', 'completado', 'atrasado']),
            'progreso' => fake()->numberBetween(0, 100),
        ];
    }
}
