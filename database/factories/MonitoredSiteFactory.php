<?php

namespace Database\Factories;

use App\Models\MonitoredSite;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MonitoredSiteFactory extends Factory
{
    protected $model = MonitoredSite::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->domainWord().'.'.fake()->tld(),
            'url' => 'https://www.'.fake()->domainName(),
            'method' => 'GET',
            'expected_status' => '200',
            'check_interval' => fake()->randomElement([1, 5, 10, 15, 30]),
            'is_active' => true,
            'last_check_at' => fake()->dateTimeBetween('-1 hour', 'now'),
            'last_status' => fake()->randomElement(['up', 'up', 'up', 'down']),
            'last_response_time' => fake()->numberBetween(50, 2000),
        ];
    }
}
