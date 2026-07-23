<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CargaDiariaProducto extends Model
{
    use BelongsToOwner, HasFactory;

    protected $fillable = [
        'owner_id',
        'carga_diaria_id',
        'producto_id',
        'cantidad_bordo',
        'cantidad_vendida',
        'cantidad_devuelta',
        'cantidad_llena',
        'cantidad_vacia',
        'cantidad_faltante',
        'cantidad_defectuosa',
    ];

    public function cargaDiaria(): BelongsTo
    {
        return $this->belongsTo(CargaDiaria::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
