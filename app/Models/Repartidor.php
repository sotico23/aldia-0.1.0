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

    protected $fillable = [
        'owner_id',
        'user_id',
        'estado',
        'capacidad_max',
        'lat',
        'lng',
        'vehiculo_id',
        'radio_km',
        'telegram_chat_id',
        'last_position_at',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'capacidad_max' => 'integer',
            'radio_km' => 'decimal:2',
            'last_position_at' => 'datetime',
        ];
    }

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

    public function isDisponible(): bool
    {
        return $this->estado === 'disponible';
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
