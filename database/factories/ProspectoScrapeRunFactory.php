<?php

namespace Database\Factories;

use App\Models\ProspectoScrapeRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProspectoScrapeRun>
 */
class ProspectoScrapeRunFactory extends Factory
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
            'query' => 'Panaderías',
            'location' => 'Santiago, Chile',
            'max_results' => 10,
            'status' => 'queued',
        ];
    }
}
