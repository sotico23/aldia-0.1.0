<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ComentarioPublicacion extends Model
{
    use BelongsToOwner, HasFactory;

    protected $fillable = [
        'owner_id',
        'user_id',
        'publicacion_id',
        'parent_id',
        'content',
        'image_path',
        'likes_count',
        'hearts_count',
    ];

    protected $appends = ['image_url'];

    protected function casts(): array
    {
        return [
            'likes_count' => 'integer',
            'hearts_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function publicacion(): BelongsTo
    {
        return $this->belongsTo(Publicacion::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ComentarioPublicacion::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(ComentarioPublicacion::class, 'parent_id');
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? asset('storage/'.$this->image_path) : null;
    }
}
