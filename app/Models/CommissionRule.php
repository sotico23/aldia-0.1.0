<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'commission_type',
        'commission_value',
        'min_sale_amount',
        'is_active',
    ];

    protected $casts = [
        'commission_value' => 'decimal:2',
        'min_sale_amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Cajero específico al que aplica la comisión. Nulo = regla general del comercio.
     */
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
