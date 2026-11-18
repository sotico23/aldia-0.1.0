<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Wallet>
 */
class WalletFactory extends Factory
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
            'balance' => 0,
            'currency' => 'CLP',
            'status' => 'active',
            'allow_overdraft' => false,
        ];
    }

    /**
     * Billetera congelada: no admite movimientos.
     */
    public function frozen(): static
    {
        return $this->state(fn (): array => ['status' => 'frozen']);
    }

    /**
     * Billetera que permite saldo negativo.
     */
    public function withOverdraft(): static
    {
        return $this->state(fn (): array => ['allow_overdraft' => true]);
    }
}
