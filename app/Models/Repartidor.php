<?php

namespace App\Models;

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
}
