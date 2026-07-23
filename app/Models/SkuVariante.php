<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SkuVariante extends Model
{
    use BelongsToOwner, HasFactory;

    protected $table = 'sku_variantes';

    protected $fillable = [
        'producto_id',
        'sku',
        'precio_venta',
        'precio_compra',
        'stock',
        'stock_minimo',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'precio_venta' => 'decimal:2',
            'precio_compra' => 'decimal:2',
            'stock' => 'decimal:3',
            'stock_minimo' => 'decimal:3',
            'activo' => 'boolean',
        ];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function valores(): HasMany
    {
        return $this->hasMany(SkuVarianteValor::class);
    }

    public function getStockActualAttribute(): float
    {
        return (float) $this->stock;
    }

    public function getEstaDisponibleAttribute(): bool
    {
        return $this->activo && $this->stock > 0;
    }
}
