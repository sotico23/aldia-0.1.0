<?php

namespace Database\Factories;

use App\Models\Tesoreria;
use Illuminate\Database\Eloquent\Factories\Factory;

class TesoreriaFactory extends Factory
{
    protected $model = Tesoreria::class;

    public function definition(): array
    {
        return [

            'tipo' => fake()->randomElement(['ingreso', 'egreso']),
            'monto' => fake()->randomFloat(2, 10000, 5000000),
            'descripcion' => fake()->sentence(),
            'fecha' => fake()->dateTimeBetween('-6 months', 'now'),
            'referencia' => fake()->unique()->numerify('TS-########'),
            'categoria' => fake()->randomElement(['ventas', 'compras', 'nomina', 'servicios', 'mantenimiento', 'marketing']),
            'estado' => fake()->randomElement(['pendiente', 'confirmado', 'cancelado']),
        ];
    }
}
