<?php

namespace Database\Factories;

use App\Models\Configuracion;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConfiguracionFactory extends Factory
{
    protected $model = Configuracion::class;

    public function definition(): array
    {
        return [
            'clave' => fake()->unique()->slug(2),
            'valor' => fake()->sentence(),
            'categoria' => fake()->randomElement(['general', 'email', 'pagos', 'notificaciones', 'integraciones']),
            'tipo' => fake()->randomElement(['string', 'boolean', 'integer', 'array']),
            'descripcion' => fake()->optional()->sentence(),
        ];
    }
}
