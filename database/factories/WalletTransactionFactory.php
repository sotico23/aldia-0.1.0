<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WalletTransaction>
 */
class WalletTransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 1000, 50000);

        return [
            'wallet_id' => Wallet::factory(),
            'type' => 'credit',
            'category' => 'adjustment',
            'amount' => $amount,
            'balance_before' => 0,
            'balance_after' => $amount,
            'reference_type' => null,
            'reference_id' => null,
            'description' => fake()->sentence(4),
            'created_by' => User::factory(),
        ];
    }

    /**
     * Débito (salida de dinero) con saldo resultante coherente.
     */
    public function debit(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => 'debit',
            'category' => 'withdrawal',
            'balance_before' => $attributes['amount'],
            'balance_after' => 0,
        ]);
    }
}
