<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntregaItem extends Model
{
    use BelongsToOwner, HasFactory;

    protected $fillable = [
        'entrega_id',
        'producto_id',
        'cantidad_pedida',
        'cantidad_entregada',
        'unidad_medida',
        'subtotal_metrica',
        'unidades_totales',
        'owner_id',
    ];

    public function casts(): array
    {
        return [
            'cantidad_pedida' => 'decimal:2',
            'cantidad_entregada' => 'decimal:2',
            'subtotal_metrica' => 'decimal:2',
            'unidades_totales' => 'integer',
        ];
    }

    protected static function booted()
    {
        static::saving(function ($item) {
            if ($item->producto) {
                $producto = $item->producto;
                $cantidad = (float) ($item->cantidad_entregada ?? $item->cantidad_pedida);

                $contenido = (float) ($producto->contenido_por_unidad ?? 1.0);
                $pesoBase = (float) ($producto->peso_base ?? 0.0);

                $item->subtotal_metrica = ($cantidad * $contenido) + ($cantidad * $pesoBase);

                $item->unidades_totales = (int) $cantidad;
            }

            if ($item->unidad_medida) {
                $item->unidad_medida = strtolower($item->unidad_medida);
            }
        });
    }

    public function entrega(): BelongsTo
    {
        return $this->belongsTo(Entrega::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
