<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CuponUsoDetalle extends Model
{
    protected $table = 'cupon_uso_detalle';

    protected $fillable = [
        'cupon_uso_id',
        'producto_id',
        'cantidad',
        'precio_unitario',
        'descuento_tipo',
        'descuento_valor',
        'monto_descuento',
    ];

    protected function casts(): array
    {
        return [
            'precio_unitario' => 'decimal:2',
            'descuento_valor' => 'decimal:2',
            'monto_descuento' => 'decimal:2',
        ];
    }

    public function cuponUso(): BelongsTo
    {
        return $this->belongsTo(CuponUso::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
