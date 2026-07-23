<?php

namespace Database\Factories;

use App\Models\Impuesto;
use Illuminate\Database\Eloquent\Factories\Factory;

class ImpuestoFactory extends Factory
{
    protected $model = Impuesto::class;

    public function definition(): array
    {
        return [
            'nombre' => fake()->randomElement(['IVA 19%', 'IVA 19% Reducido', 'Impuesto Específico', 'Retención']).' '.fake()->numberBetween(1, 99),
            'tasa' => fake()->randomFloat(2, 0, 40),
            'tipo' => fake()->randomElement(['porcentaje', 'fijo']),
            'codigo' => fake()->numerify('IMP-####'),
            'activo' => true,
            'notas' => fake()->optional()->sentence(),
        ];
    }
}
