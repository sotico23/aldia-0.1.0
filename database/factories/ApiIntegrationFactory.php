<?php

namespace Database\Factories;

use App\Models\ApiIntegration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApiIntegration>
 */
class ApiIntegrationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => 'webpay',
            'credentials' => [
                'commerce_code' => $this->faker->numerify('##########'),
                'api_key' => $this->faker->sha256(),
            ],
            'environment' => 'integration',
            'is_active' => true,
            'last_tested_at' => null,
            'last_tested_status' => null,
            'last_tested_message' => null,
        ];
    }
}
