<?php

namespace Database\Factories;

use App\Models\Producto;
use App\Models\Vacio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vacio>
 */
class VacioFactory extends Factory
{
    protected $model = Vacio::class;

    public function definition(): array
    {
        return [
            'producto_id' => Producto::factory(),
            'cantidad' => fake()->numberBetween(0, 100),
            'cantidad_minima' => fake()->numberBetween(1, 10),
            'estado' => fake()->randomElement(['disponible', 'entregado', 'retornado', 'perdido']),
            'ubicacion' => fake()->optional()->sentence(3),
            'observaciones' => fake()->optional()->sentence(),
        ];
    }

    public function disponible(): static
    {
        return $this->state(fn () => ['estado' => 'disponible']);
    }

    public function entregado(): static
    {
        return $this->state(fn () => ['estado' => 'entregado']);
    }
}
