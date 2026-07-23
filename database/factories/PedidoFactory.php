<?php

namespace Database\Factories;

use App\Models\Pedido;
use App\Models\PublicProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PedidoFactory extends Factory
{
    protected $model = Pedido::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'public_profile_id' => PublicProfile::factory(),
            'numero_pedido' => 'PED-'.date('Ymd').'-'.fake()->bothify('?????'),
            'estado' => 'confirmado',
            'subtotal' => fake()->randomFloat(2, 10000, 500000),
            'impuesto' => fake()->randomFloat(2, 1000, 100000),
            'total' => fn (array $attrs) => $attrs['subtotal'] + $attrs['impuesto'],
            'nombre_cliente' => fake()->name(),
            'telefono_cliente' => fake()->numerify('+56 9 ########'),
            'direccion_cliente' => fake()->address(),
            'metodo_pago' => fake()->randomElement(['efectivo', 'tarjeta', 'transferencia']),
            'notas' => fake()->optional()->sentence(),
        ];
    }
}
