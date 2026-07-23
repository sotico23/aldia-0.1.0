<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prestamo extends Model
{
    use BelongsToOwner, HasFactory;

    protected $fillable = [
        'owner_id',
        'empleado_id',
        'tipo',
        'monto_total',
        'monto_cuota',
        'numero_cuotas',
        'cuotas_pagadas',
        'saldo_pendiente',
        'fecha_inicio',
        'fecha_fin',
        'frecuencia',
        'estado',
        'motivo',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'monto_total' => 'decimal:2',
            'monto_cuota' => 'decimal:2',
            'saldo_pendiente' => 'decimal:2',
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
        ];
    }

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class);
    }

    public function cuotas(): HasMany
    {
        return $this->hasMany(PrestamoCuota::class);
    }

    public function getProgresoAttribute(): float
    {
        if ($this->numero_cuotas === 0) {
            return 0;
        }

        return round(($this->cuotas_pagadas / $this->numero_cuotas) * 100, 1);
    }

    public function getEstadoColorAttribute(): string
    {
        return match ($this->estado) {
            'activo' => 'bg-blue-500',
            'pagado' => 'bg-green-500',
            'cancelado' => 'bg-red-500',
            default => 'bg-gray-500',
        };
    }
}
