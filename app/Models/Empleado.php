<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Empleado extends Model
{
    use BelongsToOwner, HasFactory;

    protected $fillable = [
        'user_id',
        'owner_id',
        'creator_id',
        'almacen_id',
        'nombre',
        'apellido',
        'foto',
        'rut',
        'fecha_nacimiento',
        'nacionalidad',
        'estado_civil',
        'comuna',
        'email',
        'telefono',
        'cargo',
        'departamento',
        'fecha_contratacion',
        'tipo_contrato',
        'salario',
        'sueldo_liquido_pactado',
        'afp',
        'sistema_salud',
        'isapre_nombre',
        'banco_nombre',
        'banco_tipo_cuenta',
        'banco_numero_cuenta',
        'estado',
        'direccion',
        'notas',
        'hora_entrada',
        'hora_salida',
    ];

    protected function casts(): array
    {
        return [
            'fecha_contratacion' => 'date',
            'fecha_nacimiento' => 'date',
            'salario' => 'decimal:2',
            'sueldo_liquido_pactado' => 'decimal:2',
            'hora_entrada' => 'datetime:H:i',
            'hora_salida' => 'datetime:H:i',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class);
    }

    public function tareas(): HasMany
    {
        return $this->hasMany(Tarea::class, 'empleado_id');
    }

    public function asistencias(): HasMany
    {
        return $this->hasMany(Asistencia::class, 'empleado_id');
    }

    public function prestamos(): HasMany
    {
        return $this->hasMany(Prestamo::class);
    }

    public function scopeActivos($query)
    {
        return $query->where('estado', 'activo');
    }

    public function scopeByDepartamento($query, string $departamento)
    {
        return $query->where('departamento', $departamento);
    }

    public function getNombreCompletoAttribute(): string
    {
        return "{$this->nombre} {$this->apellido}";
    }

    public function getAntiguedadAniosAttribute(): int
    {
        /** @var Carbon|null $fecha */
        $fecha = $this->fecha_contratacion;

        return (int) ($fecha?->diffInYears(now()) ?? 0);
    }
}
