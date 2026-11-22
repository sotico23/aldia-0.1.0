<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreReaction extends Model
{
    use HasFactory;

    public const LIKE = 1;

    public const NEUTRAL = 0;

    public const DISLIKE = -1;

    protected $fillable = [
        'user_id',
        'public_profile_id',
        'like',
        'rating',
    ];

    /** @param array{like?: int, rating?: int|null} $vote */
    public static function saveVote(int $profileId, int $userId, array $vote): void
    {
        if ($vote === []) {
            return;
        }

        static::upsert([
            ['public_profile_id' => $profileId, 'user_id' => $userId, ...$vote],
        ], ['user_id', 'public_profile_id'], array_keys($vote));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function publicProfile(): BelongsTo
    {
        return $this->belongsTo(PublicProfile::class);
    }
}
