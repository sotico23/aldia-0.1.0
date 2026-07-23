<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CargaDiariaRenovacionDetalle extends Model
{
    use BelongsToOwner, HasFactory;

    protected $fillable = [
        'owner_id',
        'renovacion_id',
        'producto_id',
        'cantidad_bordo',
        'cantidad_llena',
        'cantidad_vacia',
        'cantidad_faltante',
        'cantidad_defectuosa',
        'cantidad_vendida',
        'cantidad_devuelta',
    ];

    public function renovacion(): BelongsTo
    {
        return $this->belongsTo(CargaDiariaRenovacion::class, 'renovacion_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
