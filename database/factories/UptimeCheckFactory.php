<?php

namespace Database\Factories;

use App\Models\UptimeCheck;
use Illuminate\Database\Eloquent\Factories\Factory;

class UptimeCheckFactory extends Factory
{
    protected $model = UptimeCheck::class;

    public function definition(): array
    {
        return [
            'monitored_site_id' => fake()->numberBetween(1, 50),
            'status' => fake()->randomElement(['up', 'up', 'up', 'down']),
            'response_time_ms' => fake()->numberBetween(20, 3000),
            'http_status_code' => fake()->randomElement([200, 200, 200, 301, 404, 500]),
            'checked_at' => fake()->dateTimeBetween('-7 days', 'now'),
            'error_message' => fake()->optional()->sentence(),
        ];
    }
}
