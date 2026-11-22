<?php

namespace App\Models;

use Database\Factories\WalletTransferFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransfer extends Model
{
    /** @use HasFactory<WalletTransferFactory> */
    use HasFactory;

    protected $fillable = ['sender_wallet_id', 'recipient_wallet_id', 'request_key', 'amount', 'currency', 'note'];

    public function senderWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'sender_wallet_id');
    }

    public function recipientWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'recipient_wallet_id');
    }

    public function canEscalateRefund(): bool
    {
        return $this->refund_status === 'rejected'
            || ($this->refund_status === 'pending' && $this->refund_requested_at?->lte(now()->subDays(3)));
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'refund_requested_at' => 'datetime', 'refund_resolved_at' => 'datetime'];
    }
}
