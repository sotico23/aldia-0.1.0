<?php

namespace Database\Factories;

use App\Models\Movimiento;
use Illuminate\Database\Eloquent\Factories\Factory;

class MovimientoFactory extends Factory
{
    protected $model = Movimiento::class;

    public function definition(): array
    {
        return [
            'producto' => fn () => 'Producto '.fake()->numberBetween(1, 500),
            'tipo' => fake()->randomElement(['entrada', 'salida', 'transferencia', 'ajuste']),
            'cantidad' => fake()->numberBetween(1, 100),
            'almacen_origen' => fake()->optional()->word(),
            'almacen_destino' => fake()->optional()->word(),
            'referencia' => fake()->numerify('MOV-########'),
            'notas' => fake()->optional()->sentence(),
        ];
    }
}
