<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PosCierreCaja extends Model
{
    protected $fillable = [
        'owner_id',
        'imported_by',
        'fecha_desde',
        'fecha_hasta',
        'total_efectivo',
        'total_tarjeta',
        'total_transferencia',
        'total_otros',
        'total_general',
        'cantidad_ventas',
        'referencia',
        'archivo_origen',
        'importado_at',
    ];

    protected function casts(): array
    {
        return [
            'fecha_desde' => 'date',
            'fecha_hasta' => 'date',
            'total_efectivo' => 'decimal:2',
            'total_tarjeta' => 'decimal:2',
            'total_transferencia' => 'decimal:2',
            'total_otros' => 'decimal:2',
            'total_general' => 'decimal:2',
            'cantidad_ventas' => 'integer',
            'importado_at' => 'datetime',
        ];
    }
}
