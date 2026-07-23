<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChannelCredential extends Model
{
    use BelongsToOwner;
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'telegram_bot_token',
        'telegram_bot_username',
        'whatsapp_phone_number_id',
        'whatsapp_access_token',
        'whatsapp_business_id',
        'whatsapp_api_version',
    ];

    protected function casts(): array
    {
        return [
            'telegram_bot_token' => 'encrypted',
            'whatsapp_access_token' => 'encrypted',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
