<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CargaDiariaRenovacion extends Model
{
    use BelongsToOwner, HasFactory;

    protected $table = 'carga_diaria_renovaciones';

    protected $fillable = [
        'owner_id',
        'carga_diaria_id',
        'fecha',
        'tipo',
        'notas',
        'total_productos_llenos',
        'total_productos_vacios',
        'total_productos_faltantes',
        'total_productos_defectuosos',
        'ventas_totales',
        'devoluciones_totales',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'ventas_totales' => 'decimal:2',
            'devoluciones_totales' => 'decimal:2',
        ];
    }

    public function cargaDiaria(): BelongsTo
    {
        return $this->belongsTo(CargaDiaria::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(CargaDiariaRenovacionDetalle::class, 'renovacion_id');
    }
}
