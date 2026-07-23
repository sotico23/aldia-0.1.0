<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoItem extends Model
{
    use BelongsToOwner;
    use HasFactory;

    protected $table = 'pedido_items';

    protected $fillable = [
        'pedido_id',
        'producto_id',
        'nombre_producto',
        'precio_unitario',
        'cantidad',
        'subtotal',
        'owner_id',
    ];

    protected function casts(): array
    {
        return [
            'precio_unitario' => 'decimal:2',
            'cantidad' => 'decimal:3',
            'subtotal' => 'decimal:2',
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
