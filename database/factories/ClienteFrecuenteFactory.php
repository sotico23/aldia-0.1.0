<?php

namespace Database\Factories;

use App\Models\ClienteFrecuente;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClienteFrecuente>
 */
class ClienteFrecuenteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'wallet_id' => Wallet::factory(),
            'nombre' => fake()->name(),
            'account_number' => fake()->numerify('################'),
            'nota' => null,
        ];
    }
}
