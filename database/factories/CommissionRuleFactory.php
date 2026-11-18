<?php

namespace Database\Factories;

use App\Models\CommissionRule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommissionRule>
 */
class CommissionRuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => User::factory(),
            'user_id' => null,
            'commission_type' => 'percentage',
            'commission_value' => 10,
            'min_sale_amount' => 0,
            'is_active' => true,
        ];
    }

    /**
     * Regla de comisión por monto fijo.
     */
    public function fixed(float $value = 2500): static
    {
        return $this->state(fn (): array => [
            'commission_type' => 'fixed',
            'commission_value' => $value,
        ]);
    }

    /**
     * Regla específica para un cajero.
     */
    public function forCashier(User $cashier): static
    {
        return $this->state(fn (): array => ['user_id' => $cashier->id]);
    }

    /**
     * Regla inactiva.
     */
    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
