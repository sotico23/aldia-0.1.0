<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Prospecto extends Model
{
    use BelongsToOwner, HasFactory;

    protected $fillable = [
        'owner_id',
        'nombre',
        'rut',
        'email',
        'telefono',
        'empresa',
        'cargo',
        'direccion',
        'comuna',
        'region',
        'descripcion',
        'fuente',
        'estado',
        'prioridad',
        'valor_estimado',
        'fecha_seguimiento',
        'notas',
        'giro',
        'google_maps_key',
        'google_maps_url',
        'sitio_web',
    ];

    protected function casts(): array
    {
        return [
            'valor_estimado' => 'decimal:2',
            'fecha_seguimiento' => 'date',
        ];
    }
}
