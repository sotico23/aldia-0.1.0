<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Zone>
 */
class ZoneFactory extends Factory
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
            'name' => $this->faker->city(),
            'lat' => $this->faker->latitude(-34, -32),
            'lng' => $this->faker->longitude(-71, -69),
            'radio_km' => 10,
            'capacidad_max' => 20,
            'activa' => true,
        ];
    }

    public function inactiva(): static
    {
        return $this->state(fn () => ['activa' => false]);
    }
}
