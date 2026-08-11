<?php

namespace Database\Factories;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PushSubscription>
 */
class PushSubscriptionFactory extends Factory
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
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/'.$this->faker->uuid(),
            'keys_json' => [
                'p256dh' => $this->faker->sha256(),
                'auth' => $this->faker->sha1(),
            ],
            'user_agent' => $this->faker->userAgent(),
        ];
    }
}
