<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Database\Factories\DeliveryPositionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryPosition extends Model
{
    /** @use HasFactory<DeliveryPositionFactory> */
    use BelongsToOwner;

    use HasFactory;

    protected $fillable = [
        'owner_id',
        'repartidor_id',
        'lat',
        'lng',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
        ];
    }

    public function repartidor(): BelongsTo
    {
        return $this->belongsTo(Repartidor::class);
    }
}
