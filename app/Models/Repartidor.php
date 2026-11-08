<?php

namespace App\Models;

use App\Scopes\OwnerScope;
use App\Traits\BelongsToOwner;
use Database\Factories\RepartidorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Repartidor extends Model
{
    /** @use HasFactory<RepartidorFactory> */
    use BelongsToOwner;

    use HasFactory;

    protected $table = 'repartidores';

    /** Rol Spatie asignado automáticamente a repartidores de empresa. */
    public const DEFAULT_ROLE = 'Repartidor';

    // Tipos de repartidor
    const TYPE_EMPLOYEE = 'employee';

    const TYPE_FREELANCER = 'freelancer';

    // Estados de aprobación
    const APPROVAL_PENDING = 'pending_approval';

    const APPROVAL_APPROVED = 'approved';

    const APPROVAL_REJECTED = 'rejected';

    /**
     * Nombre del rol Spatie que se asigna a los repartidores de empresa.
     */
    public static function defaultRole(): string
    {
        return (string) config('delivery.default_role', self::DEFAULT_ROLE);
    }

    protected $fillable = [
        'owner_id',
        'user_id',
        'estado',
        'driver_type',
        'approval_status',
        'is_online',
        'comision_pct',
        'rejected_reason',
        'documentos',
        'capacidad_max',
        'lat',
        'lng',
        'vehiculo_id',
        'radio_km',
        'telegram_chat_id',
        'last_position_at',
        'last_online_at',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'is_online' => 'boolean',
            'capacidad_max' => 'integer',
            'comision_pct' => 'decimal:2',
            'radio_km' => 'decimal:2',
            'documentos' => 'array',
            'last_position_at' => 'datetime',
            'last_online_at' => 'datetime',
        ];
    }

    // ─── Relaciones ───────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
    }

    public function positions(): HasMany
    {
        return $this->hasMany(DeliveryPosition::class);
    }

    public function orderOffers(): HasMany
    {
        return $this->hasMany(DriverOrderOffer::class);
    }

    // ─── Helpers de estado ────────────────────────────────────────────────────

    public function isDisponible(): bool
    {
        return $this->estado === 'disponible';
    }

    public function isApproved(): bool
    {
        return $this->approval_status === self::APPROVAL_APPROVED;
    }

    public function isFreelancer(): bool
    {
        return $this->driver_type === self::TYPE_FREELANCER;
    }

    public function isEmployee(): bool
    {
        return $this->driver_type === self::TYPE_EMPLOYEE;
    }

    /**
     * El repartidor puede recibir pedidos:
     * debe estar aprobado, online y con capacidad disponible.
     */
    public function puedeRecibirPedidos(): bool
    {
        return $this->isApproved()
            && $this->is_online
            && $this->isDisponible()
            && $this->tieneCapacidad();
    }

    /**
     * Pedidos en curso (preparando/enviado) asignados al repartidor.
     */
    public function pedidosActivos(): int
    {
        return Pedido::query()
            ->withoutGlobalScope(OwnerScope::class)
            ->where('owner_id', $this->owner_id)
            ->where('repartidor_id', $this->user_id)
            ->whereIn('estado', ['preparando', 'enviado'])
            ->count();
    }

    public function tieneCapacidad(int $extra = 0): bool
    {
        return $this->pedidosActivos() + $extra < (int) $this->capacidad_max;
    }

    /**
     * Calcula la ganancia estimada del repartidor para un costo de envío dado.
     */
    public function gananciaEstimada(float $costoEnvio): float
    {
        return round($costoEnvio * ((float) $this->comision_pct / 100), 0);
    }

    /**
     * Fórmula de Haversine: distancia en kilómetros.
     */
    public function distanciaA(float $lat, float $lng): ?float
    {
        if ($this->lat === null || $this->lng === null) {
            return null;
        }

        $radioTierra = 6371.0;
        $dLat = deg2rad($lat - $this->lat);
        $dLng = deg2rad($lng - $this->lng);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($this->lat)) * cos(deg2rad($lat)) * sin($dLng / 2) ** 2;

        return $radioTierra * 2 * asin(sqrt($a));
    }
}
