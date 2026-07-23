<?php

namespace Database\Factories;

use App\Models\Cupon;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cupon>
 */
class CuponFactory extends Factory
{
    protected $model = Cupon::class;

    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'user_id' => User::factory(),
            'codigo' => strtoupper(fake()->lexify('????').fake()->randomNumber(3)),
            'tipo' => fake()->randomElement(['porcentaje', 'precio_fijo', 'envio_gratis']),
            'valor' => fake()->randomFloat(2, 1, 100),
            'descripcion' => fake()->sentence(),
            'plantilla_html' => null,
            'variables_ejemplo' => null,
            'max_usos' => fake()->numberBetween(0, 100),
            'usos_actuales' => 0,
            'usos_por_cliente' => 1,
            'compra_minima' => fake()->optional()->randomFloat(2, 10, 1000),
            'fecha_inicio' => fake()->optional()->dateTimeBetween('-1 month', '+1 month'),
            'fecha_fin' => fake()->optional()->dateTimeBetween('+1 month', '+6 months'),
            'activa' => true,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['activa' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['activa' => false]);
    }

    public function porcentaje(): static
    {
        return $this->state(fn () => [
            'tipo' => 'porcentaje',
            'valor' => fake()->randomFloat(2, 5, 50),
        ]);
    }

    public function precioFijo(): static
    {
        return $this->state(fn () => [
            'tipo' => 'precio_fijo',
            'valor' => fake()->randomFloat(2, 1000, 50000),
        ]);
    }

    public function expirado(): static
    {
        return $this->state(fn () => [
            'fecha_fin' => now()->subDay(),
        ]);
    }

    public function sinUsos(): static
    {
        return $this->state(fn () => [
            'max_usos' => 5,
            'usos_actuales' => 5,
        ]);
    }
}
