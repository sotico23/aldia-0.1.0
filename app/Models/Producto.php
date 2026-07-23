<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Producto extends Model
{
    use BelongsToOwner, HasFactory;

    protected $fillable = [
        'user_id',
        'owner_id',
        'public_profile_id',
        'codigo',
        'color',
        'unidad_medida',
        'nombre',
        'descripcion',
        'categoria_id',
        'precio_compra',
        'precio_venta',
        'stock_minimo',
        'envase_retornable',
        'envase_producto_id',
        'medida_pesable',
        'tipo_medida',
        'cantidad_medida',
        'tipo_envase',
        'activo',
        'imagen',
        'imagen2',
        'imagen3',
        'imagen4',
        'imagen5',
        'video',
        'mostrar_en_perfil',
        'is_service',
        'duracion',
        'requires_appointment',
        'course_id',
        'peso_por_unidad',
        'contenido_por_unidad',
        'peso_base',
        'parent_id',
        'talla',
        'tiene_variantes',
        'fecha_vencimiento',
    ];

    protected function casts(): array
    {
        return [
            'precio_compra' => 'decimal:2',
            'precio_venta' => 'decimal:2',
            'activo' => 'boolean',
            'envase_retornable' => 'boolean',
            'medida_pesable' => 'boolean',
            'cantidad_medida' => 'decimal:2',
            'mostrar_en_perfil' => 'boolean',
            'is_service' => 'boolean',
            'peso_por_unidad' => 'decimal:2',
            'contenido_por_unidad' => 'decimal:2',
            'peso_base' => 'decimal:2',
            'tiene_variantes' => 'boolean',
            'fecha_vencimiento' => 'date',
        ];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    public function inventarios(): HasMany
    {
        return $this->hasMany(Inventario::class);
    }

    public function inventario(): HasOne
    {
        return $this->hasOne(Inventario::class)->latestOfMany();
    }

    public function skus(): HasMany
    {
        return $this->hasMany(SkuVariante::class);
    }

    public function warehouses(): BelongsToMany
    {
        return $this->belongsToMany(Almacen::class, 'inventarios')
            ->withPivot('cantidad', 'cantidad_minima', 'ubicacion', 'owner_id')
            ->withTimestamps();
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /**
     * Get stock for a specific warehouse
     */
    public function stockEnAlmacen(int $almacenId): float
    {
        $inventario = $this->inventarios()
            ->where(fn ($q) => $q->where('almacen_id', $almacenId))
            ->first();

        return $inventario ? (float) ($inventario->cantidad ?? 0.0) : 0.0;
    }

    public function detalleCompras(): HasMany
    {
        return $this->hasMany(DetalleCompra::class);
    }

    public function detalleVentas(): HasMany
    {
        return $this->hasMany(DetalleVenta::class);
    }

    /**
     * Get the physical container/product associated with this product (for returnable items)
     */
    public function envaseProducto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'envase_producto_id');
    }

    public function publicProfile(): BelongsTo
    {
        return $this->belongsTo(PublicProfile::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopeConStock($query)
    {
        return $query->whereHas('inventario', fn ($q) => $q->whereColumn('cantidad', '>', 'cantidad_minima'));
    }

    public function scopeSinStock($query)
    {
        return $query->where(function ($q) {
            $q->whereDoesntHave('inventario')
                ->orWhereHas('inventario', fn ($q2) => $q2->whereColumn('cantidad', '<=', 'cantidad_minima'));
        });
    }

    public function getMargenGananciaAttribute(): float
    {
        if ($this->precio_compra == 0) {
            return 0;
        }

        return (($this->precio_venta - $this->precio_compra) / $this->precio_compra) * 100;
    }

    public function productoVariantes(): HasMany
    {
        return $this->hasMany(ProductoVariante::class);
    }

    public function padre(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'parent_id');
    }

    public function variantes(): HasMany
    {
        return $this->hasMany(Producto::class, 'parent_id');
    }

    public function getStockTotalAttribute(): float
    {
        if ($this->tiene_variantes) {
            return $this->variantes->sum(function ($variante) {
                return $variante->inventarios()->sum('cantidad') ?? 0;
            });
        }

        return $this->inventarios()->sum('cantidad') ?? 0;
    }

    /**
     * Check if product is a gas cylinder (recarga)
     */
    public function esRecargaGas(): bool
    {
        return $this->envase_retornable
            && $this->envase_producto_id !== null
            && $this->unidad_medida === 'unidad'
            && ! $this->medida_pesable;
    }

    /**
     * Get the empty cylinder (envase) associated with this recarga
     */
    public function getEnvaseVacioAttribute(): ?Producto
    {
        return $this->envaseProducto;
    }

    /**
     * Check if product is an empty cylinder (envase)
     */
    public function esEnvase(): bool
    {
        return $this->envase_retornable === false
            && ($this->tipo_envase ?? false) === 'envase';
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function providers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'service_provider', 'service_id', 'user_id');
    }
}
