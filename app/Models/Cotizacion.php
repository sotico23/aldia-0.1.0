<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cotizacion extends Model
{
    use BelongsToOwner, HasFactory;

    protected $table = 'cotizaciones';

    protected $fillable = [
        'numero',
        'user_id',
        'owner_id',
        'cliente_id',
        'fecha',
        'fecha_validez',
        'subtotal',
        'impuesto',
        'total',
        'estado',
        'notas',
        'detalles',
        'condiciones',
        'iva_personalizado',
        'descuento_tipo',
        'descuento_monto',
        'currency',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'fecha_validez' => 'date',
            'subtotal' => 'decimal:2',
            'impuesto' => 'decimal:2',
            'total' => 'decimal:2',
            'detalles' => 'array',
            'iva_personalizado' => 'decimal:2',
            'descuento_monto' => 'decimal:2',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function emisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
