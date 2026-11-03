<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryRate extends Model
{
    use BelongsToOwner, HasFactory;

    protected $fillable = [
        'owner_id',
        'calculation_type',
        'commune',
        'km_from',
        'km_to',
        'cost',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'km_from' => 'decimal:2',
            'km_to' => 'decimal:2',
            'cost' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
