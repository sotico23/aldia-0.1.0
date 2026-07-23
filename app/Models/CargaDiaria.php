<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CargaDiaria extends Model
{
    use BelongsToOwner, HasFactory;

    protected $fillable = [
        'owner_id',
        'vehiculo_id',
        'conductor_id',
        'fecha',
        'estado',
        'ventas_totales',
        'devoluciones_totales',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'ventas_totales' => 'decimal:2',
            'devoluciones_totales' => 'decimal:2',
        ];
    }

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
    }

    public function conductor(): BelongsTo
    {
        return $this->belongsTo(Conductor::class);
    }

    public function productos(): HasMany
    {
        return $this->hasMany(CargaDiariaProducto::class);
    }

    public function renovaciones(): HasMany
    {
        return $this->hasMany(CargaDiariaRenovacion::class);
    }
}
