<?php

namespace Database\Factories;

use App\Models\Mensaje;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MensajeFactory extends Factory
{
    protected $model = Mensaje::class;

    public function definition(): array
    {
        $maxUserId = User::max('id') ?: 1;

        return [
            'sender_id' => fake()->numberBetween(1, $maxUserId),
            'receiver_id' => fake()->numberBetween(1, $maxUserId),
            'contenido' => fake()->paragraph(),
            'leido' => fake()->boolean(80),
        ];
    }
}
