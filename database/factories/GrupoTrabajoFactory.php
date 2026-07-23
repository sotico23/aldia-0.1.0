<?php

namespace Database\Factories;

use App\Models\GrupoTrabajo;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class GrupoTrabajoFactory extends Factory
{
    protected $model = GrupoTrabajo::class;

    public function definition(): array
    {
        return [

            'user_id' => User::factory(),
            'nombre' => 'Grupo '.fake()->numerify('###'),
            'descripcion' => fake()->sentence(),
            'color' => fake()->hexColor(),
            'estado' => 'activo',
        ];
    }
}
