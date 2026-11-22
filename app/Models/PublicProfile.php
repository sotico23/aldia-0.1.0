<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PublicProfile extends Model
{
    use BelongsToOwner, HasFactory;

    protected $fillable = [
        'user_id',
        'owner_id',
        'slug',
        'title',
        'description',
        'phone',
        'email',
        'is_active',
        'is_official',
        'is_verified',
        'likes_count',
        'rating_total',
        'rating_count',
        'pickup_address',
        'pickup_lat',
        'pickup_lng',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_official' => 'boolean',
            'is_verified' => 'boolean',
            'likes_count' => 'integer',
            'rating_total' => 'integer',
            'rating_count' => 'integer',
            'pickup_lat' => 'float',
            'pickup_lng' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function categorias(): HasMany
    {
        return $this->hasMany(Categoria::class, 'public_profile_id');
    }

    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class, 'public_profile_id');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(StoreReaction::class);
    }

    public function scopeWithReactionStats(Builder $query): Builder
    {
        $ratings = fn (Builder $query) => $query->whereBetween('rating', [1, 5]);

        return $query->withCount([
            'reactions as likes_count' => fn (Builder $query) => $query->where('like', StoreReaction::LIKE),
            'reactions as dislikes_count' => fn (Builder $query) => $query->where('like', StoreReaction::DISLIKE),
            'reactions as rating_count' => $ratings,
        ])->withSum(['reactions as rating_total' => $ratings], 'rating');
    }
}
