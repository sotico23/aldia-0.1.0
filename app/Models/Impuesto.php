<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Impuesto extends Model
{
    use BelongsToOwner, HasFactory;

    protected $table = 'impuestos';

    protected $fillable = ['nombre', 'codigo', 'tasa', 'tipo', 'descripcion', 'fecha_inicio', 'fecha_fin', 'estado'];

    protected function casts(): array
    {
        return ['tasa' => 'decimal:2', 'fecha_inicio' => 'date', 'fecha_fin' => 'date'];
    }
}
