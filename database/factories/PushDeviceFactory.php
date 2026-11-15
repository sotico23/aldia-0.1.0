<?php

namespace Database\Factories;

use App\Models\PushDevice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PushDevice>
 */
class PushDeviceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'personal_access_token_id' => fn (array $attributes): int => User::findOrFail($attributes['user_id'])->createToken('push-device-test')->accessToken->id,
            'token' => fake()->sha256(),
            'token_hash' => fn (array $attributes): string => hash('sha256', $attributes['token']),
        ];
    }
}
