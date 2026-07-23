<?php

namespace App\Enums;

enum FinancialEvent: string
{
    case PaymentReceived = 'payment_received';
    case PaymentRejected = 'payment_rejected';
    case Refund = 'refund';
    case SubscriptionActivated = 'subscription_activated';
    case SubscriptionCancelled = 'subscription_cancelled';
    case CommissionGenerated = 'commission_generated';
    case TransferCompleted = 'transfer_completed';

    public function label(): string
    {
        return match ($this) {
            self::PaymentReceived => 'Pago recibido',
            self::PaymentRejected => 'Pago rechazado',
            self::Refund => 'Reembolso',
            self::SubscriptionActivated => 'Suscripción activada',
            self::SubscriptionCancelled => 'Suscripción cancelada',
            self::CommissionGenerated => 'Comisión generada',
            self::TransferCompleted => 'Transferencia completada',
        };
    }

    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }

    public static function defaults(): array
    {
        return array_map(fn (self $case) => [
            'event' => $case->value,
            'label' => $case->label(),
            'n8n' => false,
            'telegram' => false,
            'whatsapp' => false,
            'email' => false,
        ], self::cases());
    }
}
