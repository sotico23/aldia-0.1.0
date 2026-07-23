<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Promocion extends Model
{
    use BelongsToOwner, HasFactory;

    protected $fillable = [
        'user_id',
        'owner_id',
        'nombre',
        'tipo',
        'valor',
        'descripcion',
        'skus',
        'categoria_id',
        'compra_minima',
        'fecha_inicio',
        'fecha_fin',
        'activa',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'compra_minima' => 'decimal:2',
            'skus' => 'array',
            'fecha_inicio' => 'datetime',
            'fecha_fin' => 'datetime',
            'activa' => 'boolean',
        ];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    public function scopeActive($query)
    {
        return $query->where('activa', true);
    }

    public function scopeVigente($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', now());
        })->where(function ($q) {
            $q->whereNull('fecha_inicio')->orWhere('fecha_inicio', '<=', now());
        });
    }
}
