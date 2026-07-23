<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Oportunidad extends Model
{
    use BelongsToOwner, HasFactory;

    protected $table = 'oportunidades';

    protected $fillable = [
        'nombre',
        'owner_id',
        'cliente_id',
        'valor',
        'etapa',
        'probabilidad',
        'fecha_cierre_estimada',
        'descripcion',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'fecha_cierre_estimada' => 'date',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}
