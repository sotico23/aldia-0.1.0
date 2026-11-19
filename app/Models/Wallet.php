<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class Wallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'balance',
        'currency',
        'status',
        'allow_overdraft',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'currency' => 'string',
        'status' => 'string',
        'allow_overdraft' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Wallet $wallet): void {
            do {
                $number = (string) random_int(1000000000000000, 9999999999999999);
            } while (static::query()->where('account_number', $number)->exists());

            $wallet->account_number = $number;
        });

        static::updating(function (Wallet $wallet): void {
            if ($wallet->isDirty(['account_number', 'user_id'])) {
                throw new LogicException('El número y el titular de una billetera son inmutables.');
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }
}
