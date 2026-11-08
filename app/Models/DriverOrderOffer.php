<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Oferta de pedido enviada a un repartidor (modelo Uber/Rappi).
 *
 * Cuando un pedido entra al pool de delivery, se generan ofertas para los
 * repartidores online y disponibles en su radio. El primero en aceptar
 * toma el pedido; los demás quedan en 'expired' o 'cancelled'.
 */
class DriverOrderOffer extends Model
{
    use BelongsToOwner;

    protected $table = 'driver_order_offers';

    const STATUS_PENDING   = 'pending';
    const STATUS_ACCEPTED  = 'accepted';
    const STATUS_REJECTED  = 'rejected';
    const STATUS_EXPIRED   = 'expired';
    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'owner_id',
        'pedido_id',
        'repartidor_id',
        'status',
        'distancia_km',
        'ganancia_estimada',
        'expires_at',
        'responded_at',
        'reject_reason',
    ];

    protected function casts(): array
    {
        return [
            'distancia_km'      => 'float',
            'ganancia_estimada' => 'float',
            'expires_at'        => 'datetime',
            'responded_at'      => 'datetime',
        ];
    }

    // ─── Relaciones ───────────────────────────────────────────────────────────

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function repartidor(): BelongsTo
    {
        return $this->belongsTo(Repartidor::class);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED
            || ($this->status === self::STATUS_PENDING && $this->expires_at?->isPast());
    }
}
