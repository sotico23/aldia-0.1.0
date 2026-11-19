<?php

namespace App\Models;

use Database\Factories\WalletTransferFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WalletTransfer extends Model
{
    /** @use HasFactory<WalletTransferFactory> */
    use HasFactory;

    protected $fillable = ['sender_wallet_id', 'recipient_wallet_id', 'request_key', 'amount', 'currency', 'note'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }
}
