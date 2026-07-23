<?php

namespace Database\Factories;

use App\Models\Promocion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PromocionFactory extends Factory
{
    protected $model = Promocion::class;

    public function definition(): array
    {
        $tipos = ['porcentaje', 'precio_fijo', 'combo_2x1'];
        $tipo = fake()->randomElement($tipos);

        $valor = match ($tipo) {
            'porcentaje' => fake()->randomFloat(2, 5, 50),
            'precio_fijo' => fake()->randomFloat(2, 1000, 50000),
            'combo_2x1' => 0,
        };

        return [
            'user_id' => User::factory(),

            'nombre' => fake()->words(3, true),
            'tipo' => $tipo,
            'valor' => $valor,
            'descripcion' => fake()->sentence(),
            'skus' => null,
            'categoria_id' => null,
            'compra_minima' => fake()->optional()->randomFloat(2, 5000, 50000),
            'fecha_inicio' => fake()->optional()->dateTimeBetween('-1 week', 'now'),
            'fecha_fin' => fake()->optional()->dateTimeBetween('now', '+1 month'),
            'activa' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['activa' => false]);
    }

    public function porcentaje(): static
    {
        return $this->state(fn (array $attributes) => ['tipo' => 'porcentaje', 'valor' => fake()->randomFloat(2, 5, 50)]);
    }

    public function precioFijo(): static
    {
        return $this->state(fn (array $attributes) => ['tipo' => 'precio_fijo', 'valor' => fake()->randomFloat(2, 1000, 50000)]);
    }

    public function combo2x1(): static
    {
        return $this->state(fn (array $attributes) => ['tipo' => 'combo_2x1', 'valor' => 0]);
    }
}
