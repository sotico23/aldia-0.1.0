<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardConfig extends Model
{
    use BelongsToOwner;
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'mode',
        'layout',
        'widgets',
        'is_default',
        'owner_id',
    ];

    public function casts(): array
    {
        return [
            'layout' => 'array',
            'widgets' => 'array',
            'is_default' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function getForUser(int $userId): ?self
    {
        return static::where('user_id', $userId)
            ->where('is_default', true)
            ->first();
    }

    public static function getAllForUser(int $userId)
    {
        return static::where('user_id', $userId)->orderBy('name')->get();
    }
}
