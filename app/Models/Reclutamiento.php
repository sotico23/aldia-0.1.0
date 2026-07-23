<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reclutamiento extends Model
{
    use BelongsToOwner, HasFactory;

    protected $table = 'reclutamiento';

    protected $fillable = ['owner_id', 'puesto', 'candidato', 'email', 'telefono', 'fecha_postulacion', 'fecha_entrevista', 'estado', 'resultado', 'notas', 'observaciones'];

    protected function casts(): array
    {
        return ['fecha_postulacion' => 'date', 'fecha_entrevista' => 'date'];
    }
}
