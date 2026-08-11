<?php

namespace Database\Factories;

use App\Models\Repartidor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Repartidor>
 */
class RepartidorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'user_id' => User::factory(),
            'estado' => 'disponible',
            'lat' => $this->faker->latitude(-34, -33),
            'lng' => $this->faker->longitude(-71, -70),
            'radio_km' => 10,
            'last_position_at' => now(),
        ];
    }
}
