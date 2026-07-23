<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Movimiento extends Model
{
    use BelongsToOwner, HasFactory;

    protected $table = 'movimientos';

    protected $fillable = [
        'owner_id',
        'producto_id',
        'producto',
        'tipo',
        'cantidad',
        'almacen_origen_id',
        'almacen_destino_id',
        'almacen_origen',
        'almacen_destino',
        'referencia',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
        ];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function almacenOrigen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class, 'almacen_origen_id');
    }

    public function almacenDestino(): BelongsTo
    {
        return $this->belongsTo(Almacen::class, 'almacen_destino_id');
    }
}
