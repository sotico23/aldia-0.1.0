<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Scopes\OwnerScope;
use App\Traits\BelongsToOwner;
use App\Traits\LogsPaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Pedido extends Model
{
    use BelongsToOwner;
    use HasFactory;
    use LogsPaymentStatus;

    // Modalidades de despacho
    const DISPATCH_INTERNAL = 'internal';
    const DISPATCH_MARKETPLACE = 'marketplace';

    protected $fillable = [
        'owner_id',
        'business_id',
        'user_id',
        'public_profile_id',
        'cliente_id',
        'numero_pedido',
        'estado',
        'dispatch_mode',
        'delivery_type',
        'subtotal',
        'impuesto',
        'total',
        'costo_envio',
        'ganancia_repartidor',
        'notas',
        'nombre_cliente',
        'telefono_cliente',
        'direccion_cliente',
        'destino_lat',
        'destino_lng',
        'distancia_km',
        'pool_reenvios',
        'pool_entrada_at',
        'pool_bloqueado',
        'pool_visible_at',
        'hora_aceptado',
        'hora_recogido',
        'hora_entregado',
        'repartidor_id',
        'metodo_pago',
        'fecha_confirmacion',
        'fecha_entrega',
        'payment_id',
        'payment_status',
        'payment_data',
        'erp_synced_at',
        'currency',
    ];

    protected function casts(): array
    {
        return [
            'estado' => 'string',
            'payment_status' => PaymentStatus::class,
            'subtotal' => 'decimal:2',
            'impuesto' => 'decimal:2',
            'total' => 'decimal:2',
            'costo_envio' => 'decimal:2',
            'ganancia_repartidor' => 'decimal:2',
            'fecha_confirmacion' => 'datetime',
            'fecha_entrega' => 'datetime',
            'erp_synced_at' => 'datetime',
            'payment_data' => 'array',
            'destino_lat' => 'float',
            'destino_lng' => 'float',
            'distancia_km' => 'decimal:2',
            'pool_reenvios' => 'integer',
            'pool_entrada_at' => 'datetime',
            'pool_bloqueado' => 'boolean',
            'pool_visible_at' => 'datetime',
            'hora_aceptado' => 'datetime',
            'hora_recogido' => 'datetime',
            'hora_entregado' => 'datetime',
        ];
    }

    public static function generarNumeroPedido(): string
    {
        do {
            $numero = 'PED-'.date('Ymd').'-'.Str::random(5);
        } while (self::withoutGlobalScope(OwnerScope::class)->where('numero_pedido', $numero)->exists());

        return strtoupper($numero);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function publicProfile(): BelongsTo
    {
        return $this->belongsTo(PublicProfile::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cliente_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PedidoItem::class);
    }

    public function conversacion(): HasOne
    {
        return $this->hasOne(Conversacion::class);
    }

    public function repartidor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'repartidor_id');
    }

    /**
     * Pedidos listos para entrar en el pool de reparto del tenant.
     */
    public function scopeDisponibleEnPool($query): void
    {
        $query->whereIn('estado', ['preparando', 'pendiente'])
            ->whereNull('repartidor_id')
            ->whereNull('hora_aceptado')
            ->where('pool_bloqueado', false)
            ->where(function ($query) {
                $query->whereNull('pool_visible_at')
                    ->orWhere('pool_visible_at', '<=', now());
            });
    }

    public function scopeMarketplace($query)
    {
        return $query->where('dispatch_mode', self::DISPATCH_MARKETPLACE);
    }

    public function scopeInternal($query)
    {
        return $query->where('dispatch_mode', self::DISPATCH_INTERNAL);
    }

    public function isMarketplace(): bool
    {
        return ($this->dispatch_mode ?? self::DISPATCH_INTERNAL) === self::DISPATCH_MARKETPLACE;
    }

    public function isInternal(): bool
    {
        return ($this->dispatch_mode ?? self::DISPATCH_INTERNAL) === self::DISPATCH_INTERNAL;
    }

    /**
     * Permite que los endpoints de delivery (aceptación, reclamo, entrega) puedan
     * resolver pedidos entre distintos tenants (ej. repartidor freelancer tomando
     * un pedido de marketplace de otra empresa).
     */
    public function resolveRouteBinding($value, $field = null)
    {
        return $this->withoutGlobalScope(OwnerScope::class)
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->first();
    }

    /**
     * Costo de envío efectivo del pedido.
     *
     * Si el pedido no trae un costo explícito (flujos antiguos o pedidos sin
     * tarifa), se estima con la configuración de reparto para que el
     * repartidor siempre vea una ganancia y el dashboard no muestre $0.
     */
    public function costoEnvioEfectivo(): float
    {
        $costo = (float) ($this->costo_envio ?? 0);

        if ($costo > 0) {
            return round($costo, 0);
        }

        $distancia = (float) ($this->distancia_km ?? 0);
        $base = (float) config('delivery.costo_envio_default', 2500);
        $porKm = (float) config('delivery.costo_por_km', 350);

        return round($base + ($distancia > 0 ? $distancia * $porKm : 0), 0);
    }

    /**
     * Ganancia que corresponde al repartidor por este pedido.
     */
    public function gananciaPara(Repartidor $repartidor): float
    {
        $ganancia = (float) ($this->ganancia_repartidor ?? 0);

        if ($ganancia > 0) {
            return $ganancia;
        }

        return $repartidor->gananciaEstimada($this->costoEnvioEfectivo());
    }
}
