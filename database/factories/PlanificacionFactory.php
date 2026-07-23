<?php

namespace Database\Factories;

use App\Models\Planificacion;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlanificacionFactory extends Factory
{
    protected $model = Planificacion::class;

    public function definition(): array
    {
        return [
            'titulo' => 'Planificación '.fake()->monthName().' '.fake()->year(),
            'descripcion' => fake()->paragraph(),
            'objetivo' => fake()->sentence(),
            'fecha_inicio' => fake()->dateTimeBetween('-1 month', 'now'),
            'fecha_fin' => fake()->dateTimeBetween('now', '+3 months'),
            'estado' => fake()->randomElement(['borrador', 'aprobada', 'en_progreso', 'completada']),
            'presupuesto' => fake()->randomFloat(2, 500000, 50000000),
            'categoria' => fake()->randomElement(['mensual', 'trimestral', 'anual', 'semanal']),
        ];
    }
}
