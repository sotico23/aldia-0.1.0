<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DteEnvio extends Model
{
    use BelongsToOwner, HasFactory;

    protected $fillable = [
        'owner_id',
        'rut_emisor',
        'rut_receptor_sii',
        'xml_sobre',
        'track_id',
        'estado',
        'respuesta_sii',
        'enviado_at',
        'respuesta_at',
        'ambiente',
    ];

    protected function casts(): array
    {
        return [
            'enviado_at' => 'datetime',
            'respuesta_at' => 'datetime',
        ];
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(DteDocumento::class);
    }

    public function scopePendiente($query)
    {
        return $query->where('estado', 'pendiente');
    }
}
