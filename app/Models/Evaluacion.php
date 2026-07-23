<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Evaluacion extends Model
{
    use BelongsToOwner, HasFactory;

    protected $table = 'evaluaciones';

    protected $fillable = ['owner_id', 'empleado_id', 'evaluador_id', 'fecha', 'periodo', 'puntuacion', 'comentarios', 'tipo', 'estado'];

    protected function casts(): array
    {
        return ['fecha' => 'date', 'puntuacion' => 'decimal:2'];
    }
}
