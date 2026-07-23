<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asiento extends Model
{
    use BelongsToOwner, HasFactory;

    protected $fillable = [
        'owner_id',
        'fecha',
        'numero',
        'descripcion',
        'tipo',
        'total_debe',
        'total_haber',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'total_debe' => 'decimal:2',
            'total_haber' => 'decimal:2',
            'estado' => 'boolean',
        ];
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleAsiento::class);
    }
}
