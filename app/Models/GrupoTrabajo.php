<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrupoTrabajo extends Model
{
    use BelongsToOwner, HasFactory;

    protected $fillable = [
        'owner_id',
        'user_id',
        'nombre',
        'descripcion',
        'color',
        'estado',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function miembros(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'grupo_trabajo_users', 'grupo_trabajo_id', 'user_id')
            ->withPivot('rol')
            ->withTimestamps();
    }

    public function conductores(): BelongsToMany
    {
        return $this->belongsToMany(Conductor::class, 'grupo_trabajo_conductores', 'grupo_trabajo_id', 'conductor_id')
            ->withTimestamps();
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(GrupoTrabajoAsignacion::class);
    }
}
