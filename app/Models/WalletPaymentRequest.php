<?php

namespace App\Models;

use Database\Factories\WalletPaymentRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WalletPaymentRequest extends Model
{
    /** @use HasFactory<WalletPaymentRequestFactory> */
    use HasFactory;

    protected $fillable = ['requester_wallet_id', 'payer_wallet_id', 'request_key', 'amount', 'currency', 'note', 'status', 'transfer_id', 'resolved_at'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'resolved_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (WalletPaymentRequest $payment): void {
            $payment->share_token = Str::random(64);
        });
    }

    public function requesterWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'requester_wallet_id');
    }

    public function payerWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'payer_wallet_id');
    }
}
