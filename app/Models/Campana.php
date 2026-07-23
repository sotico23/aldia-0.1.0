<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Campana extends Model
{
    use BelongsToOwner, HasFactory;

    protected $table = 'campanas';

    protected $fillable = [
        'owner_id',
        'nombre',
        'descripcion',
        'tipo',
        'canal',
        'objetivo',
        'fecha_inicio',
        'fecha_fin',
        'presupuesto',
        'presupuesto_real',
        'visitas',
        'leads',
        'conversiones',
        'roi',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'presupuesto' => 'decimal:2',
            'presupuesto_real' => 'decimal:2',
            'roi' => 'decimal:2',
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
        ];
    }
}
