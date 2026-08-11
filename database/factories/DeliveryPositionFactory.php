<?php

namespace Database\Factories;

use App\Models\DeliveryPosition;
use App\Models\Repartidor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryPosition>
 */
class DeliveryPositionFactory extends Factory
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
            'repartidor_id' => Repartidor::factory(),
            'lat' => $this->faker->latitude(-34, -33),
            'lng' => $this->faker->longitude(-71, -70),
        ];
    }
}
