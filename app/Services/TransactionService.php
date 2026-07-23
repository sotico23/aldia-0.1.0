<?php

namespace App\Services;

use App\Events\PaymentSuccessful;
use App\Models\Pedido;
use App\Models\Transaction;
use Illuminate\Support\Str;

class TransactionService
{
    public function recordPayment(
        string $gateway,
        string $gatewayTransactionId,
        Pedido $pedido,
        float $amount,
        string $currency,
        float $fee = 0,
        ?float $netAmount = null,
        array $extraMetadata = [],
    ): Transaction {
        $netAmount ??= $amount - $fee;

        $transaction = Transaction::firstOrCreate(
            [
                'gateway' => $gateway,
                'gateway_transaction_id' => $gatewayTransactionId,
            ],
            [
                'uuid' => (string) Str::uuid(),
                'business_id' => $pedido->owner_id ?? $pedido->business_id,
                'pedido_id' => $pedido->id,
                'user_id' => $pedido->cliente_id,
                'type' => 'customer_payment',
                'status' => 'approved',
                'currency' => $currency,
                'amount' => $amount,
                'fee' => $fee,
                'net_amount' => $netAmount,
                'metadata' => array_merge([
                    'numero_pedido' => $pedido->numero_pedido,
                ], $extraMetadata),
                'processed_at' => now(),
            ]
        );

        event(new PaymentSuccessful($transaction));

        return $transaction;
    }

    public function recordGatewayOnly(
        string $gateway,
        string $gatewayTransactionId,
        float $amount,
        string $currency,
        string $status = 'approved',
        string $type = 'customer_payment',
        array $metadata = [],
    ): ?Transaction {
        if (! $gatewayTransactionId) {
            return null;
        }

        return Transaction::firstOrCreate(
            [
                'gateway' => $gateway,
                'gateway_transaction_id' => $gatewayTransactionId,
            ],
            [
                'uuid' => (string) Str::uuid(),
                'type' => $type,
                'status' => $status,
                'currency' => $currency,
                'amount' => $amount,
                'fee' => 0,
                'net_amount' => $amount,
                'metadata' => $metadata,
                'processed_at' => now(),
            ]
        );
    }
}
