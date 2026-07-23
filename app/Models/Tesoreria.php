<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tesoreria extends Model
{
    use BelongsToOwner, HasFactory;

    protected $table = 'tesoreria';

    protected $fillable = ['owner_id', 'tipo', 'monto', 'descripcion', 'cuenta_bancaria_id', 'fecha', 'referencia', 'categoria', 'estado'];

    protected function casts(): array
    {
        return ['monto' => 'decimal:2', 'fecha' => 'date'];
    }
}
