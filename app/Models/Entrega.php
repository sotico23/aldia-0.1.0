<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Entrega extends Model
{
    use BelongsToOwner, HasFactory;

    protected $table = 'entregas';

    protected $fillable = [
        'owner_id',
        'venta_id',
        'grupo_trabajo_id',
        'vehiculo_id',
        'conductor_id',
        'cliente_id',
        'cliente',
        'direccion',
        'fecha_entrega',
        'estado',
        'notas',
        'descripcion',
        'productos_json',
    ];

    protected function casts(): array
    {
        return [
            'fecha_entrega' => 'date',
            'productos_json' => 'array',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(EntregaItem::class);
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function conductor(): BelongsTo
    {
        return $this->belongsTo(Conductor::class);
    }

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
    }

    public function grupoTrabajo(): BelongsTo
    {
        return $this->belongsTo(GrupoTrabajo::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}
