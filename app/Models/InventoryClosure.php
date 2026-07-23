<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryClosure extends Model
{
    use BelongsToOwner, HasFactory;

    protected $fillable = [
        'owner_id',
        'user_id',
        'almacen_id',
        'closure_date',
        'type',
        'status',
        'total_products',
        'total_stock',
        'expected_stock',
        'opening_stock',
        'closing_stock',
        'difference',
        'observations',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'closure_date' => 'date',
            'closed_at' => 'datetime',
            'total_products' => 'integer',
            'total_stock' => 'decimal:3',
            'expected_stock' => 'decimal:3',
            'opening_stock' => 'decimal:3',
            'closing_stock' => 'decimal:3',
            'difference' => 'decimal:3',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class);
    }

    public function getIsBalancedAttribute(): bool
    {
        return $this->difference == 0;
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'ABIERTO' => 'warning',
            'CERRADO' => 'info',
            'AUDITADO' => 'success',
            default => 'secondary',
        };
    }
}
