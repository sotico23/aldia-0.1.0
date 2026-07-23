<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Completed = 'completed';
    case Created = 'created';
    case Failed = 'failed';
    case Pending = 'pending';
    case Cancelled = 'cancelled';
    case Local = 'local';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Completed, self::Failed, self::Cancelled => true,
            default => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Completed => 'Completado',
            self::Created => 'Creado',
            self::Failed => 'Fallido',
            self::Pending => 'Pendiente',
            self::Cancelled => 'Cancelado',
            self::Local => 'Pago local',
        };
    }
}
