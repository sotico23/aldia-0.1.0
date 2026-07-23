<?php

namespace App\Traits;

use App\Models\PedidoStatusLog;
use Illuminate\Support\Facades\Auth;

trait LogsPaymentStatus
{
    public static function bootLogsPaymentStatus(): void
    {
        static::created(function ($model) {
            $value = $model->payment_status;
            if ($value !== null) {
                PedidoStatusLog::create([
                    'pedido_id' => $model->id,
                    'field' => 'payment_status',
                    'from' => null,
                    'to' => $value instanceof \BackedEnum ? $value->value : $value,
                    'changed_by' => Auth::id(),
                    'gateway' => $model->metodo_pago,
                ]);
            }
        });

        static::updated(function ($model) {
            $dirty = $model->getDirty();

            if (array_key_exists('payment_status', $dirty)) {
                $from = $model->getOriginal('payment_status');
                $to = $dirty['payment_status'];

                if ($from !== $to) {
                    PedidoStatusLog::create([
                        'pedido_id' => $model->id,
                        'field' => 'payment_status',
                        'from' => $from instanceof \BackedEnum ? $from->value : $from,
                        'to' => $to instanceof \BackedEnum ? $to->value : $to,
                        'changed_by' => Auth::id(),
                        'gateway' => $model->metodo_pago,
                    ]);
                }
            }
        });
    }
}
