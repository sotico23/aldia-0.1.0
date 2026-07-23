<?php

namespace Database\Factories;

use App\Models\Nomina;
use Illuminate\Database\Eloquent\Factories\Factory;

class NominaFactory extends Factory
{
    protected $model = Nomina::class;

    public function definition(): array
    {
        return [
            'periodo' => fake()->month().'/'.fake()->year(),
            'total_bruto' => fake()->randomFloat(2, 350000, 2500000),
            'total_deducciones' => fake()->randomFloat(2, 0, 150000),
            'total_neto' => fake()->randomFloat(2, 300000, 3000000),
            'estado' => fake()->randomElement(['borrador', 'procesado', 'pagado']),
        ];
    }
}
