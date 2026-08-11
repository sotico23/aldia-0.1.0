<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Database\Factories\DeliveryConfigFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryConfig extends Model
{
    /** @use HasFactory<DeliveryConfigFactory> */
    use BelongsToOwner;

    use HasFactory;

    protected $fillable = [
        'owner_id',
        'modo',
        'pool_timeout_min',
        'pool_reenvio_min',
    ];

    protected function casts(): array
    {
        return [
            'pool_timeout_min' => 'integer',
            'pool_reenvio_min' => 'integer',
        ];
    }
}
