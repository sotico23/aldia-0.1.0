<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CambioProducto extends Model
{
    use BelongsToOwner;
    use HasFactory;

    protected $table = 'cambios_productos';

    protected $fillable = [
        'owner_id',
        'venta_id',
        'producto_entregado_id',
        'producto_recibido_id',
        'cantidad',
        'diferencia_precio',
        'motivo',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'diferencia_precio' => 'decimal:2',
        ];
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function productoEntregado(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_entregado_id');
    }

    public function productoRecibido(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_recibido_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
