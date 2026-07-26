<?php

namespace Database\Factories;

use App\Models\CargaDiaria;
use App\Models\Conductor;
use App\Models\Vehiculo;
use Illuminate\Database\Eloquent\Factories\Factory;

class CargaDiariaFactory extends Factory
{
    protected $model = CargaDiaria::class;

    public function definition(): array
    {
        return [
            'vehiculo_id' => Vehiculo::factory(),
            'conductor_id' => Conductor::factory(),
            'fecha' => fake()->dateTimeBetween('-1 month', 'now'),
            'estado' => fake()->randomElement(['pendiente', 'en_ruta', 'cerrado']),
            'ventas_totales' => fake()->randomFloat(2, 0, 100000),
            'devoluciones_totales' => fake()->randomFloat(2, 0, 10000),
            'notas' => fake()->optional()->sentence(),
        ];
    }

    public function pendiente(): static
    {
        return $this->state(fn () => ['estado' => 'pendiente']);
    }

    public function enRuta(): static
    {
        return $this->state(fn () => ['estado' => 'en_ruta']);
    }

    public function cerrado(): static
    {
        return $this->state(fn () => ['estado' => 'cerrado']);
    }
}
