<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramacionCallCenter extends Model
{
    use BelongsToOwner, HasFactory;

    protected $table = 'programaciones_call_center';

    protected $fillable = [
        'user_id',
        'titulo',
        'descripcion',
        'contacto_type',
        'contacto_id',
        'numero_telefono',
        'fecha_programada',
        'recordatorio_minutos',
        'notificado_at',
        'completada',
        'owner_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha_programada' => 'datetime',
            'notificado_at' => 'datetime',
            'completada' => 'boolean',
            'recordatorio_minutos' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
