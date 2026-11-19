<?php

namespace Database\Factories;

use App\Models\Wallet;
use App\Models\WalletTransfer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WalletTransfer>
 */
class WalletTransferFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sender_wallet_id' => Wallet::factory(),
            'recipient_wallet_id' => Wallet::factory(),
            'request_key' => fake()->uuid(),
            'amount' => '10.00',
            'currency' => 'CLP',
            'note' => null,
        ];
    }
}
