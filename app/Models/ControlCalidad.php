<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ControlCalidad extends Model
{
    use BelongsToOwner, HasFactory;

    protected $table = 'calidad';

    protected $fillable = [
        'owner_id',
        'lote_id',
        'producto_id',
        'tipo',
        'resultado',
        'cantidad_muestra',
        'cantidad_defectuosa',
        'observaciones',
        'fecha',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'cantidad_muestra' => 'integer',
            'cantidad_defectuosa' => 'integer',
        ];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class);
    }
}
