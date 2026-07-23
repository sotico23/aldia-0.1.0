<?php

namespace Database\Factories;

use App\Models\PublicProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PublicProfileFactory extends Factory
{
    protected $model = PublicProfile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'slug' => fake()->unique()->slug(3).'-'.fake()->unique()->numberBetween(1, 9999),
            'title' => fake()->company(),
            'description' => fake()->paragraph(),
            'phone' => fake()->numerify('+56 9 ########'),
            'email' => fake()->safeEmail(),
            'is_active' => true,
        ];
    }
}
