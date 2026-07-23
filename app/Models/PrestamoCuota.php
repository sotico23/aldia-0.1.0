<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrestamoCuota extends Model
{
    use BelongsToOwner, HasFactory;

    protected $fillable = [
        'owner_id',
        'prestamo_id',
        'numero_cuota',
        'monto',
        'fecha_vencimiento',
        'fecha_pago',
        'monto_pagado',
        'estado',
        'metodo_pago',
        'referencia_pago',
        'aplicada_en_nomina',
        'nomina_periodo',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'monto_pagado' => 'decimal:2',
            'fecha_vencimiento' => 'date',
            'fecha_pago' => 'date',
        ];
    }

    public function prestamo(): BelongsTo
    {
        return $this->belongsTo(Prestamo::class);
    }

    public function getEstadoColorAttribute(): string
    {
        return match ($this->estado) {
            'pagada' => 'bg-green-500',
            'pendiente' => 'bg-yellow-500',
            'vencida' => 'bg-red-500',
            default => 'bg-gray-500',
        };
    }
}
