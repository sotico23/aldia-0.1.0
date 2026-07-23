<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    use BelongsToOwner, HasFactory;

    protected $fillable = ['instructor_id', 'owner_id', 'category_id', 'title', 'slug', 'description', 'price', 'cover_image', 'promo_video_url', 'is_published', 'features', 'certificado_html'];

    protected function casts(): array
    {
        return ['features' => 'array', 'is_published' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'category_id');
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function modules(): HasMany
    {
        return $this->hasMany(Module::class)->orderBy('order');
    }

    public function lessons(): HasMany
    {
        return $this->hasManyThrough(Lesson::class, Module::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }
}
