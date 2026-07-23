<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GestionCallCenter extends Model
{
    use BelongsToOwner, HasFactory;

    protected $table = 'gestiones_call_center';

    protected $fillable = [
        'llamada_id',
        'user_id',
        'cliente_id',
        'prospecto_id',
        'comentario',
        'resultado',
        'proxima_accion',
        'fecha_seguimiento',
        'owner_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha_seguimiento' => 'datetime',
        ];
    }

    public function llamada(): BelongsTo
    {
        return $this->belongsTo(LlamadaCallCenter::class, 'llamada_id');
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
}
