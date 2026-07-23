<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vehiculo extends Model
{
    use BelongsToOwner, HasFactory;

    protected $table = 'vehiculos';

    protected $fillable = [
        'owner_id',
        'placa', 'imei', 'marca', 'modelo', 'tipo', 'año', 'color',
        'lat', 'lng', 'velocidad', 'ultima_actualizacion',
        'estado', 'kilometraje', 'notas', 'fotos',
    ];

    protected function casts(): array
    {
        return [
            'kilometraje' => 'decimal:2',
            'año' => 'integer',
            'lat' => 'float',
            'lng' => 'float',
            'velocidad' => 'integer',
            'ultima_actualizacion' => 'datetime',
            'fotos' => 'array',
        ];
    }
}
