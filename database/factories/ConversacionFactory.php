<?php

namespace Database\Factories;

use App\Models\Conversacion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConversacionFactory extends Factory
{
    protected $model = Conversacion::class;

    public function definition(): array
    {
        $maxUserId = User::max('id') ?: 1;

        return [
            'public_profile_id' => fake()->numberBetween(1, 50),
            'comprador_id' => fake()->numberBetween(1, $maxUserId),
            'vendedor_id' => fake()->numberBetween(1, $maxUserId),
            'titulo' => fake()->sentence(),
            'ultimo_mensaje_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }
}
