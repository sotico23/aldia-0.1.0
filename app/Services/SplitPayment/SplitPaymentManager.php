<?php

namespace App\Services\SplitPayment;

use App\Models\Commission;
use App\Models\PaymentConfig;
use App\Models\Transaction;
use App\Models\WebSetting;

class SplitPaymentManager
{
    protected array $drivers = [];

    public function driver(?string $gateway = null): SplitPaymentInterface
    {
        $gateway = $gateway ?? $this->resolveGateway();

        if (! isset($this->drivers[$gateway])) {
            $this->drivers[$gateway] = $this->createDriver($gateway);
        }

        return $this->drivers[$gateway];
    }

    protected function createDriver(string $gateway): SplitPaymentInterface
    {
        return match ($gateway) {
            'mercadopago' => app(MercadoPagoSplitPayment::class),
            'paypal' => app(PayPalSplitPayment::class),
            default => throw new \InvalidArgumentException("Unsupported split payment gateway: {$gateway}"),
        };
    }

    protected function resolveGateway(): string
    {
        $config = PaymentConfig::whereNotNull('mercadopago_access_token')
            ->where('mercadopago_active', true)
            ->first();

        if ($config) {
            return 'mercadopago';
        }

        throw new \RuntimeException('No active payment gateway configured for split payments.');
    }

    /**
     * Calculate and record commission for a transaction.
     */
    public function calculateAndRecordCommission(
        Transaction $transaction,
        float $amount,
        ?PaymentConfig $config = null
    ): Commission {
        $config ??= PaymentConfig::resolveForOwner($transaction->business_id);

        $commissionType = $config?->commission_type ?? 'percentage';
        $commissionRate = $config?->commission_rate ?? 0;

        $fixedComponent = 0;
        if ($commissionType === 'hybrid') {
            $webSetting = WebSetting::first();
            $fixedComponent = (float) ($webSetting->marketplace_fixed_amount ?? 0);
        }

        $driver = $this->driver($transaction->gateway);
        $commissionAmount = $driver->calculateCommission($amount, $commissionType, $commissionRate, $fixedComponent);

        return Commission::create([
            'business_id' => $transaction->business_id,
            'transaction_id' => $transaction->id,
            'commission_type' => $commissionType,
            'commission_rate' => $commissionRate,
            'commission_amount' => $commissionAmount,
            'status' => 'pending',
            'metadata' => [
                'transaction_uuid' => $transaction->uuid,
                'gateway' => $transaction->gateway,
            ],
        ]);
    }
}
