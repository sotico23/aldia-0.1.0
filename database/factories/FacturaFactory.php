<?php

namespace Database\Factories;

use App\Models\Cliente;
use App\Models\Factura;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FacturaFactory extends Factory
{
    protected $model = Factura::class;

    public function definition(): array
    {
        return [
            'numero' => fake()->unique()->numerify('FAC-########'),
            'cliente_id' => Cliente::factory(),
            'user_id' => User::factory(),
            'owner_id' => User::factory(),
            'fecha' => fake()->dateTimeBetween('-1 year', 'now'),
            'fecha_vencimiento' => fake()->dateTimeBetween('now', '+60 days'),
            'subtotal' => 0,
            'impuesto' => 0,
            'total' => 0,
            'tipo' => fake()->randomElement(['venta', 'compra', 'cotizacion', 'proforma']),
            'estado' => fake()->randomElement(['pendiente', 'pagada', 'anulada']),
            'notas' => fake()->optional()->sentence(),
            'iva_porcentaje' => 19,
            'iva_incluido' => true,
            'descuento_tipo' => 'none',
            'descuento_valor' => 0,
            'total_descuento' => 0,
        ];
    }

    public function configure()
    {
        return $this->afterCreating(function (Factura $factura) {
            $numItems = rand(2, 8);
            $subtotal = 0;

            for ($i = 0; $i < $numItems; $i++) {
                $cantidad = rand(1, 10);
                $precio = rand(1000, 50000);
                $neto = $cantidad * $precio;
                $iva = round($neto * 0.19);

                $factura->detalles()->create([
                    'producto_id' => Producto::inRandomOrder()->first()?->id ?? Producto::factory(),
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precio,
                    'subtotal' => $neto,
                    'impuesto' => $iva,
                    'total' => $neto + $iva,
                ]);

                $subtotal += $neto;
            }

            $impuesto = round($subtotal * 0.19);
            $factura->update([
                'subtotal' => $subtotal,
                'impuesto' => $impuesto,
                'total' => $subtotal + $impuesto,
            ]);
        });
    }
}
