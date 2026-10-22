<?php

namespace Database\Factories;

use App\Models\Conversacion;
use App\Models\PublicProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConversacionFactory extends Factory
{
    protected $model = Conversacion::class;

    public function definition(): array
    {
        return [
            'public_profile_id' => PublicProfile::factory(),
            'comprador_id' => User::factory(),
            'vendedor_id' => User::factory(),
            'titulo' => fake()->sentence(),
            'ultimo_mensaje_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }
}
