<?php

namespace Database\Factories;

use App\Models\Campana;
use Illuminate\Database\Eloquent\Factories\Factory;

class CampanaFactory extends Factory
{
    protected $model = Campana::class;

    public function definition(): array
    {
        return [
            'nombre' => fake()->randomElement(['Black Friday', 'Cyber Monday', 'Día de la Madre', 'Navidad', 'Verano', 'Invierno']).' '.fake()->year(),
            'descripcion' => fake()->paragraph(),
            'tipo' => fake()->randomElement(['email', 'redes_sociales', 'sms', 'multi canal']),
            'estado' => fake()->randomElement(['activa', 'pausada', 'completada', 'programada']),
            'fecha_inicio' => fake()->dateTimeBetween('-1 month', 'now'),
            'fecha_fin' => fake()->dateTimeBetween('now', '+1 month'),
            'presupuesto' => fake()->randomFloat(2, 100000, 10000000),
            'presupuesto_real' => fake()->randomFloat(2, 0, 5000000),
            'leads' => fake()->numberBetween(0, 1000),
            'conversiones' => fake()->numberBetween(0, 100),
        ];
    }
}
