<?php

namespace Database\Factories;

use App\Models\ControlCalidad;
use Illuminate\Database\Eloquent\Factories\Factory;

class ControlCalidadFactory extends Factory
{
    protected $model = ControlCalidad::class;

    public function definition(): array
    {
        return [
            'lote' => fake()->bothify('LT-####-????'),
            'producto' => 'Producto '.fake()->numberBetween(1, 50),
            'tipo' => fake()->randomElement(['inicio', 'proceso', 'final']),
            'resultado' => fake()->randomElement(['aprobado', 'rechazado', 'con_observaciones']),
            'cantidad_muestra' => fake()->numberBetween(10, 500),
            'cantidad_defectuosa' => fake()->numberBetween(0, 20),
            'fecha' => fake()->dateTimeBetween('-1 month', 'now'),
            'observaciones' => fake()->optional()->sentence(),
        ];
    }
}
