<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CuponUso extends Model
{
    protected $table = 'cupon_usos';

    public $timestamps = false;

    protected $fillable = [
        'cupon_id',
        'pedido_id',
        'venta_id',
        'user_id',
        'email',
        'monto_total',
        'monto_descuento',
    ];

    protected function casts(): array
    {
        return [
            'monto_total' => 'decimal:2',
            'monto_descuento' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function cupon(): BelongsTo
    {
        return $this->belongsTo(Cupon::class);
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
