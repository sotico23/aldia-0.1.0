<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class GrupoTrabajoAsignacion extends Model
{
    use BelongsToOwner, HasFactory, SoftDeletes;

    protected $table = 'grupo_trabajo_asignaciones';

    protected $fillable = [
        'owner_id',
        'grupo_trabajo_id',
        'user_id',
        'fecha_inicio',
        'fecha_fin',
        'meta_monto',
        'meta_cantidad',
        'meta_kg',
        'meta_l',
        'estado',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'meta_monto' => 'decimal:2',
            'meta_cantidad' => 'integer',
            'meta_kg' => 'decimal:2',
            'meta_l' => 'decimal:2',
        ];
    }

    public function grupoTrabajo(): BelongsTo
    {
        return $this->belongsTo(GrupoTrabajo::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
