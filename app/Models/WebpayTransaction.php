<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebpayTransaction extends Model
{
    use BelongsToOwner, HasFactory;

    protected $fillable = [
        'owner_id', 'token', 'amount', 'status', 'buy_order', 'transbank_response',
    ];

    protected function casts(): array
    {
        return [
            'transbank_response' => 'array',
        ];
    }
}
