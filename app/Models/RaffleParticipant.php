<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RaffleParticipant extends Model
{
    use BelongsToOwner;
    use HasFactory;

    protected $fillable = [
        'raffle_id',
        'user_id',
        'name',
        'email',
        'phone',
        'ip_address',
        'entries',
        'is_winner',
        'prize_id',
        'won_at',
        'owner_id',
    ];

    public function casts(): array
    {
        return [
            'entries' => 'integer',
            'is_winner' => 'boolean',
            'won_at' => 'datetime',
        ];
    }

    public function raffle(): BelongsTo
    {
        return $this->belongsTo(Raffle::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function prize(): BelongsTo
    {
        return $this->belongsTo(RafflePrize::class, 'prize_id');
    }
}
