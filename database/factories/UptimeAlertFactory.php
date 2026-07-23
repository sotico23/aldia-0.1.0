<?php

namespace Database\Factories;

use App\Models\UptimeAlert;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class UptimeAlertFactory extends Factory
{
    protected $model = UptimeAlert::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'monitored_site_id' => fake()->numberBetween(1, 50),
            'type' => fake()->randomElement(['down', 'recovery', 'slow_response', 'ssl_expiring']),
            'email_enabled' => true,
            'webhook_enabled' => fake()->boolean(30),
            'webhook_url' => fake()->optional()->url(),
            'is_active' => true,
        ];
    }
}
