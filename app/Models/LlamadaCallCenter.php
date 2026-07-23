<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LlamadaCallCenter extends Model
{
    use BelongsToOwner, HasFactory;

    protected $table = 'llamadas_call_center';

    protected $fillable = [
        'user_id',
        'cliente_id',
        'prospecto_id',
        'tipo',
        'numero_emisor',
        'numero_remitente',
        'numero_telefono',
        'estado',
        'duracion',
        'fecha',
        'notas',
        'owner_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'datetime',
            'duracion' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function prospecto(): BelongsTo
    {
        return $this->belongsTo(Prospecto::class);
    }

    public function gestiones(): HasMany
    {
        return $this->hasMany(GestionCallCenter::class, 'llamada_id');
    }
}
