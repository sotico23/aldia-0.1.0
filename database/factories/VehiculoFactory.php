<?php

namespace Database\Factories;

use App\Models\Vehiculo;
use Illuminate\Database\Eloquent\Factories\Factory;

class VehiculoFactory extends Factory
{
    protected $model = Vehiculo::class;

    public function definition(): array
    {
        return [
            'placa' => fake()->unique()->bothify('??-####'),
            'imei' => fake()->unique()->numerify('##############'),
            'marca' => fake()->randomElement(['Toyota', 'Hyundai', 'Kia', 'Chevrolet', 'Nissan', 'Volkswagen', 'Ford']),
            'modelo' => fake()->word(),
            'tipo' => fake()->randomElement(['sedan', 'suv', 'camioneta', 'furgon', 'camion']),
            'año' => fake()->numberBetween(2015, 2024),
            'color' => fake()->colorName(),
            'estado' => fake()->randomElement(['disponible', 'en_servicio', 'asignado', 'mantenimiento']),
            'kilometraje' => fake()->randomFloat(2, 5000, 200000),
        ];
    }
}
