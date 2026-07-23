<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Nomina extends Model
{
    use BelongsToOwner, HasFactory;

    protected $table = 'nominas';

    protected $fillable = [
        'owner_id',
        'periodo',
        'fecha_inicio',
        'fecha_fin',
        'total_bruto',
        'total_deducciones',
        'total_neto',
        'estado',
        'notas',
        'detalles',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'total_bruto' => 'decimal:2',
            'total_deducciones' => 'decimal:2',
            'total_neto' => 'decimal:2',
            'detalles' => 'array',
        ];
    }
}
