<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrdenProduccion extends Model
{
    use BelongsToOwner, HasFactory;

    protected $table = 'ordenes_produccion';

    protected $fillable = [
        'owner_id',
        'numero',
        'producto_id',
        'producto',
        'cantidad',
        'fecha_inicio',
        'fecha_fin',
        'progreso',
        'estado',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'progreso' => 'integer',
        ];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
