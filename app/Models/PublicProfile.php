<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
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
}
