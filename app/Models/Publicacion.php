<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Publicacion extends Model
{
    use BelongsToOwner, HasFactory;

    protected $fillable = [
        'user_id',
        'owner_id',
        'content',
        'image_path',
        'likes_count',
        'hearts_count',
        'comments_count',
    ];

    protected $appends = ['image_url'];

    protected function casts(): array
    {
        return [
            'likes_count' => 'integer',
            'hearts_count' => 'integer',
            'comments_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comentarios(): HasMany
    {
        return $this->hasMany(ComentarioPublicacion::class);
    }

    public function resolveRouteBinding($value, $field = null)
    {
        return $this->withoutGlobalScopes()->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? asset('storage/'.$this->image_path) : null;
    }
}
