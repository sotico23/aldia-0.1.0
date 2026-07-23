<?php

namespace Database\Factories;

use App\Models\Cliente;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Database\Eloquent\Factories\Factory;

class VentaFactory extends Factory
{
    protected $model = Venta::class;

    public function definition(): array
    {
        $subtotal = fake()->numberBetween(10000, 500000);
        $iva = round($subtotal * 0.19);

        return [
            'numero' => fake()->unique()->numerify('FV-########'),
            'cliente_id' => Cliente::factory(),
            'user_id' => User::factory(),
            'owner_id' => User::factory(),
            'fecha' => fake()->dateTimeBetween('-1 year', 'now'),
            'subtotal' => (int) $subtotal,
            'iva' => (int) $iva,
            'total' => (int) ($subtotal + $iva),
            'metodo_pago' => fake()->randomElement(['efectivo', 'tarjeta', 'transferencia', 'otro']),
            'tipo_documento' => fake()->randomElement(['factura', 'boleta', 'nota_credito', 'cotizacion']),
            'es_pos' => fake()->boolean(60),
            'estado' => fake()->randomElement(['pendiente', 'pagada', 'cancelada']),
            'notas' => fake()->optional()->sentence(),
        ];
    }
}
