<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proveedor extends Model
{
    use BelongsToOwner, HasFactory;

    protected $fillable = [
        'user_id',
        'owner_id',
        'nombre',
        'nit',
        'telefono',
        'email',
        'direccion',
        'categoria_id',
        'activo',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function compras(): HasMany
    {
        return $this->hasMany(Compra::class);
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(ProveedorDocumento::class);
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopeBuscar($query, string $termino)
    {
        return $query->where('nombre', 'like', "%{$termino}%")
            ->orWhere('nit', 'like', "%{$termino}%")
            ->orWhere('email', 'like', "%{$termino}%");
    }
}
