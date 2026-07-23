<?php

namespace App\Services\SplitPayment;

interface SplitPaymentInterface
{
    /**
     * Create a split payment where the customer pays and the amount
     * is split between the platform and the seller business.
     *
     * @param array{
     *     amount: float,
     *     currency: string,
     *     seller_id: int,
     *     platform_fee: float,
     *     description: string,
     *     external_reference: string,
     *     pedido_id: int,
     *     business_id?: int,
     *     metadata?: array
     * } $paymentData
     * @return array{split_id: string, status: string, redirect_url?: string}
     */
    public function createSplitPayment(array $paymentData): array;

    /**
     * Process the split after a successful payment capture.
     */
    public function processSplit(string $splitId): array;

    /**
     * Release pending funds to the seller.
     */
    public function releaseFunds(string $splitId, float $amount): array;

    /**
     * Get the split payment status and details.
     */
    public function getSplitStatus(string $splitId): array;

    /**
     * Calculate platform commission based on configuration.
     */
    public function calculateCommission(float $amount, string $type, float $rate, float $fixedComponent = 0): float;
}
