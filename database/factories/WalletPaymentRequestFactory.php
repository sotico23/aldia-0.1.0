<?php

namespace Database\Factories;

use App\Models\Wallet;
use App\Models\WalletPaymentRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WalletPaymentRequest>
 */
class WalletPaymentRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'requester_wallet_id' => Wallet::factory(),
            'payer_wallet_id' => Wallet::factory(),
            'request_key' => fake()->uuid(),
            'amount' => '10.00',
            'currency' => 'CLP',
            'note' => null,
            'status' => 'pending',
        ];
    }
}
