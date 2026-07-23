<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use App\Traits\Scopes\WarehouseScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Almacen extends Model
{
    use BelongsToOwner, HasFactory, WarehouseScope;

    protected $table = 'almacenes';

    protected $fillable = [
        'user_id',
        'owner_id',
        'nombre',
        'codigo',
        'direccion',
        'telefono',
        'responsable',
        'capacidad',
        'tipo',
        'activo',
        'notas',
        'imagenes',
        'video',
    ];

    protected function casts(): array
    {
        return [
            'capacidad' => 'integer',
            'activo' => 'boolean',
            'imagenes' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function empleados(): HasMany
    {
        return $this->hasMany(Empleado::class, 'almacen_id');
    }

    public function productos(): BelongsToMany
    {
        return $this->belongsToMany(Producto::class, 'inventarios')
            ->withPivot('cantidad', 'cantidad_minima', 'ubicacion')
            ->withTimestamps();
    }

    public function movimientosOrigen(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'source_warehouse_id');
    }

    public function movimientosDestino(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'destination_warehouse_id');
    }

    public function movimientos()
    {
        return InventoryMovement::where('source_warehouse_id', $this->id)
            ->orWhere('destination_warehouse_id', $this->id);
    }
}
