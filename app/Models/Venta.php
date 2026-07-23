<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Venta extends Model
{
    use BelongsToOwner, HasFactory;

    protected $appends = ['numero_factura'];

    protected $fillable = [
        'numero',
        'owner_id',
        'cliente_id',
        'almacen_id',
        'user_id',
        'appointment_id',
        'fecha',
        'subtotal',
        'iva',
        'total',
        'metodo_pago',
        'tipo_documento',
        'empleado_id',
        'es_pos',
        'estado',
        'notas',
        'incluye_iva',
        'tipo_descuento',
        'valor_descuento',
        'monto_descuento',
        'folio',
        'tipo_dte',
        'descuento',
        'cupon_id',
        'monto_descuento_cupon',
        'currency',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'subtotal' => 'decimal:2',
            'iva' => 'decimal:2',
            'tipo_dte' => 'string',
            'total' => 'decimal:2',
            'incluye_iva' => 'boolean',
            'valor_descuento' => 'decimal:2',
            'monto_descuento' => 'decimal:2',
            'descuento' => 'decimal:2',
            'monto_descuento_cupon' => 'decimal:2',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function detalleVentas(): HasMany
    {
        return $this->hasMany(DetalleVenta::class);
    }

    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function entrega(): HasOne
    {
        return $this->hasOne(Entrega::class);
    }

    public function cupon(): BelongsTo
    {
        return $this->belongsTo(Cupon::class);
    }

    public function almacenes(): BelongsToMany
    {
        return $this->belongsToMany(Almacen::class, 'venta_almacenes')
            ->withPivot('cantidad_descontada')
            ->withTimestamps();
    }

    /**
     * Get items grouped for display in tickets/invoices
     * Groups recargas with their envases and return info
     */
    public function getItemsForTicket(): array
    {
        $items = $this->detalleVentas()->with('producto')->get();
        $grouped = [];
        $envasesEntregados = $items->filter(fn ($item) => $item->producto && $item->producto->envase_retornable === false && $item->producto->esEnvase ?? false)->keyBy('producto_id');

        foreach ($items as $item) {
            $producto = $item->producto;
            if (! $producto) {
                continue;
            }

            // Check if this is a recarga with returnable container
            if ($producto->envase_retornable && $producto->envase_producto_id) {
                // Find the corresponding envase item (physical cylinder delivered)
                $envaseItem = $items->first(fn ($i) => $i->producto_id === $producto->envase_producto_id);

                $grouped[] = [
                    'tipo' => 'recarga',
                    'item' => $item,
                    'producto' => $producto,
                    'envase_item' => $envaseItem,
                    'cantidad_retornada' => $item->cantidad_retornada ?? 0,
                ];
            } elseif ($producto->esEnvase()) {
                // This is a standalone envase sale (not part of a recarga)
                // Check if it's already shown as part of a recarga
                $yaAgrupado = collect($grouped)->contains(fn ($g) => $g['envase_item'] && $g['envase_item']->id === $item->id);
                if (! $yaAgrupado) {
                    $grouped[] = [
                        'tipo' => 'envase_solo',
                        'item' => $item,
                        'producto' => $producto,
                    ];
                }
            } else {
                // Regular product
                $grouped[] = [
                    'tipo' => 'normal',
                    'item' => $item,
                    'producto' => $producto,
                ];
            }
        }

        return $grouped;
    }

    protected function numeroFactura(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->numero,
        );
    }
}
