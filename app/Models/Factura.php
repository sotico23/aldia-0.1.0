<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Factura extends Model
{
    use BelongsToOwner, HasFactory;

    protected $fillable = [
        'numero',
        'user_id',
        'owner_id',
        'cliente_id',
        'almacen_id',
        'fecha',
        'fecha_vencimiento',
        'subtotal',
        'impuesto',
        'total',
        'tipo',
        'estado',
        'notas',
        'iva_porcentaje',
        'iva_incluido',
        'descuento_tipo',
        'descuento_valor',
        'total_descuento',
        'dte_documento_id',
        'currency',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'fecha_vencimiento' => 'date',
            'subtotal' => 'decimal:2',
            'impuesto' => 'decimal:2',
            'total' => 'decimal:2',
            'iva_porcentaje' => 'decimal:2',
            'iva_incluido' => 'boolean',
            'descuento_valor' => 'decimal:2',
            'total_descuento' => 'decimal:2',
        ];
    }

    public function dteDocumento(): BelongsTo
    {
        return $this->belongsTo(DteDocumento::class, 'dte_documento_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function emisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleFactura::class);
    }

    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class);
    }
}
