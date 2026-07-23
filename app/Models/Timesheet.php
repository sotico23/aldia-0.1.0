<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Timesheet extends Model
{
    use BelongsToOwner, HasFactory;

    protected $table = 'timesheets';

    protected $fillable = ['owner_id', 'empleado_id', 'proyecto_id', 'fecha', 'horas', 'descripcion', 'tipo', 'estado'];

    protected function casts(): array
    {
        return ['fecha' => 'date', 'horas' => 'decimal:2'];
    }
}
