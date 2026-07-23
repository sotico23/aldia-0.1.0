<?php

namespace Database\Factories;

use App\Models\Incident;
use Illuminate\Database\Eloquent\Factories\Factory;

class IncidentFactory extends Factory
{
    protected $model = Incident::class;

    public function definition(): array
    {
        return [
            'monitored_site_id' => fake()->numberBetween(1, 50),
            'started_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'resolved_at' => fake()->optional()->dateTimeBetween('-1 month', 'now'),
            'status' => fake()->randomElement(['down', 'up']),
            'cause' => fake()->sentence(),
            'details' => fake()->paragraph(),
            'notified' => true,
        ];
    }
}
