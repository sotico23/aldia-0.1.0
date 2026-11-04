<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Database\Factories\ZoneFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Zona de reparto (delivery).
 *
 * @property int $id
 * @property int $owner_id
 * @property string $name
 * @property string $modo
 * @property float|null $lat
 * @property float|null $lng
 * @property float|null $radio_km
 * @property int $capacidad_max
 * @property bool $activa
 */
class Zone extends Model
{
    /** @use HasFactory<ZoneFactory> */
    use BelongsToOwner, HasFactory;

    protected $table = 'zones';

    protected $fillable = [
        'owner_id',
        'name',
        'modo',
        'lat',
        'lng',
        'radio_km',
        'capacidad_max',
        'activa',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'radio_km' => 'float',
            'capacidad_max' => 'integer',
            'activa' => 'boolean',
        ];
    }

    /**
     * Solo las zonas operativas.
     */
    public function scopeActiva(Builder $query): Builder
    {
        return $query->where('activa', true);
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(DeliveryAsignacion::class, 'zona_id');
    }

    public function deliveryRates(): HasMany
    {
        return $this->hasMany(DeliveryRate::class, 'zone_id');
    }

    /**
     * Distancia en kilometros (haversine) entre el centro de la zona y el punto dado.
     */
    public function distanciaA(float $lat, float $lng): float
    {
        if ($this->modo !== 'radio' || $this->lat === null || $this->lng === null) {
            return INF;
        }

        $radioTierra = 6371.0;

        $dLat = deg2rad($lat - (float) $this->lat);
        $dLng = deg2rad($lng - (float) $this->lng);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad((float) $this->lat)) * cos(deg2rad($lat)) * sin($dLng / 2) ** 2;

        return $radioTierra * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Indica si el punto cae dentro del radio de la zona.
     */
    public function contiene(float $lat, float $lng): bool
    {
        return $this->modo === 'radio'
            && $this->radio_km !== null
            && $this->distanciaA($lat, $lng) <= (float) $this->radio_km;
    }
}
