<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Planificacion extends Model
{
    use BelongsToOwner, HasFactory;

    protected $table = 'planificacion';

    protected $fillable = [
        'owner_id',
        'titulo',
        'descripcion',
        'fecha_inicio',
        'fecha_fin',
        'proyecto_id',
        'responsable_id',
        'estado',
        'prioridad',
        'ubicacion',
        'presupuesto',
        'categoria',
        'objetivo',
        'asistentes_max',
        'fecha_limite',
        'requiere_materiales',
        'materiales',
        'proveedor_id',
        'contacto_emergencia',
        'telefono_emergencia',
        'fecha_inicio_real',
        'fecha_fin_real',
        'presupuesto_real',
        'resultados',
        'lecciones_aprendidas',
        'adjuntos',
        'etiquetas',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'fecha_limite' => 'date',
            'fecha_inicio_real' => 'datetime',
            'fecha_fin_real' => 'datetime',
            'presupuesto' => 'decimal:2',
            'presupuesto_real' => 'decimal:2',
            'requiere_materiales' => 'boolean',
            'adjuntos' => 'array',
            'etiquetas' => 'array',
        ];
    }
}
