<?php

namespace Database\Factories;

use App\Models\Cobranza;
use Illuminate\Database\Eloquent\Factories\Factory;

class CobranzaFactory extends Factory
{
    protected $model = Cobranza::class;

    public function definition(): array
    {
        return [
            'cliente' => 'Cliente '.fake()->numberBetween(1, 50),
            'factura' => 'FAC-'.fake()->numberBetween(100000, 999999),
            'monto' => fake()->randomFloat(2, 10000, 500000),
            'fecha_pago' => fake()->dateTimeBetween('-6 months', 'now'),
            'metodo' => fake()->randomElement(['efectivo', 'transferencia', 'cheque', 'tarjeta']),
            'estado' => fake()->randomElement(['completado', 'pendiente', 'fallido']),
            'notas' => fake()->optional()->sentence(),
        ];
    }
}
