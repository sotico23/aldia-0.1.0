<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Configuracion extends Model
{
    use BelongsToOwner, HasFactory;

    protected $table = 'configuracion';

    protected $fillable = ['owner_id', 'clave', 'valor', 'tipo', 'descripcion', 'categoria', 'editable'];

    protected function casts(): array
    {
        return ['editable' => 'boolean'];
    }
}
