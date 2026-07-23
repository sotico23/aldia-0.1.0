<?php

namespace Database\Factories;

use App\Models\Entrega;
use Illuminate\Database\Eloquent\Factories\Factory;

class EntregaFactory extends Factory
{
    protected $model = Entrega::class;

    public function definition(): array
    {
        return [
            'cliente' => fake()->name(),
            'direccion' => fake()->streetAddress().', '.fake()->city(),
            'vehiculo' => fake()->randomElement(['Toyota', 'Hyundai', 'Kia']).' '.fake()->word(),
            'conductor' => fake()->name(),
            'fecha_entrega' => fake()->dateTimeBetween('-1 week', '+1 week'),
            'estado' => fake()->randomElement(['pendiente', 'en transito', 'completada', 'fallida']),
            'descripcion' => fake()->sentence(),
        ];
    }
}
