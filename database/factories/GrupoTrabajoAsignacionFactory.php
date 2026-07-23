<?php

namespace Database\Factories;

use App\Models\GrupoTrabajo;
use App\Models\GrupoTrabajoAsignacion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class GrupoTrabajoAsignacionFactory extends Factory
{
    protected $model = GrupoTrabajoAsignacion::class;

    public function definition(): array
    {
        $inicio = fake()->dateTimeBetween('-3 months', 'now');

        return [
            'grupo_trabajo_id' => GrupoTrabajo::factory(),
            'user_id' => User::factory(),
            'fecha_inicio' => $inicio->format('Y-m-d'),
            'fecha_fin' => (clone $inicio)->modify('+1 month')->format('Y-m-d'),
            'meta_monto' => fake()->randomFloat(2, 100000, 5000000),
            'meta_cantidad' => fake()->numberBetween(10, 500),
            'meta_kg' => fake()->randomFloat(2, 50, 5000),
            'meta_l' => fake()->randomFloat(2, 20, 3000),
            'estado' => fake()->randomElement(['activa', 'completada', 'cancelada']),
            'notas' => fake()->optional()->sentence(),
        ];
    }

    public function activa(): static
    {
        return $this->state(fn () => ['estado' => 'activa']);
    }
}
