<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Hito extends Model
{
    use BelongsToOwner, HasFactory;

    protected $table = 'hitos';

    protected $fillable = ['owner_id', 'proyecto_id', 'nombre', 'descripcion', 'fecha_prevista', 'fecha_real', 'estado', 'responsable_id'];

    protected function casts(): array
    {
        return ['fecha_prevista' => 'date', 'fecha_real' => 'date'];
    }
}
