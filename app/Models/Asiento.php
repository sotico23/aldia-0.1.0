<?php

namespace App\Models;

use App\Scopes\OwnerScope;
use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asiento extends Model
{
    use BelongsToOwner, HasFactory;

    protected $fillable = [
        'owner_id',
        'fecha',
        'numero',
        'descripcion',
        'tipo',
        'total_debe',
        'total_haber',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'total_debe' => 'decimal:2',
            'total_haber' => 'decimal:2',
            'estado' => 'boolean',
        ];
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleAsiento::class);
    }

    /**
     * Número a usar al contabilizar una venta, o null si ya existe un asiento
     * para esa venta (evita el error 23000 al revertir/cambiar estados).
     */
    public static function numeroParaVenta(Venta $venta): ?string
    {
        $base = 'AS-VNT-'.str_pad((string) $venta->id, 6, '0', STR_PAD_LEFT);
        $query = static::withoutGlobalScope(OwnerScope::class);

        $yaContabilizada = (clone $query)
            ->where(function ($query) use ($venta): void {
                $query->where('descripcion', 'LIKE', "%Venta #{$venta->numero}%")
                    ->orWhere('descripcion', 'LIKE', "%Venta POS #{$venta->numero}%");
            })
            ->exists();

        if ($yaContabilizada) {
            return null;
        }

        if (! (clone $query)->where('numero', $base)->exists()) {
            return $base;
        }

        $sufijo = 2;
        while ((clone $query)->where('numero', "{$base}-{$sufijo}")->exists()) {
            $sufijo++;
        }

        return "{$base}-{$sufijo}";
    }
}
