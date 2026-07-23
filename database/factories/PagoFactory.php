<?php

namespace Database\Factories;

use App\Models\Pago;
use Illuminate\Database\Eloquent\Factories\Factory;

class PagoFactory extends Factory
{
    protected $model = Pago::class;

    public function definition(): array
    {
        return [

            'proveedor_id' => 'Proveedor '.fake()->numberBetween(1, 200),
            'monto' => fake()->randomFloat(2, 10000, 1000000),
            'fecha_pago' => fake()->dateTimeBetween('-6 months', 'now'),
            'metodo' => fake()->randomElement(['transferencia', 'cheque', 'efectivo', 'tarjeta']),
            'factura' => fake()->optional()->numerify('FAC-########'),
            'estado' => fake()->randomElement(['completado', 'pendiente', 'fallido']),
            'notas' => fake()->optional()->sentence(),
        ];
    }
}
