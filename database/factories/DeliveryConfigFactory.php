<?php

namespace Database\Factories;

use App\Models\DeliveryConfig;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryConfig>
 */
class DeliveryConfigFactory extends Factory
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
            'modo' => 'ambos',
            'pool_timeout_min' => 10,
            'pool_reenvio_min' => 30,
        ];
    }
}
