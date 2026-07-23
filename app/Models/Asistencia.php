<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asistencia extends Model
{
    use BelongsToOwner, HasFactory;

    protected $table = 'asistencia';

    protected $fillable = ['empleado_id', 'owner_id', 'fecha', 'hora_entrada', 'hora_salida', 'horas_trabajadas', 'estado', 'notas'];

    protected function casts(): array
    {
        return ['fecha' => 'date', 'horas_trabajadas' => 'decimal:2'];
    }

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class);
    }
}
