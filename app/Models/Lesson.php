<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Lesson extends Model
{
    use BelongsToOwner, HasFactory;

    protected $fillable = ['module_id', 'owner_id', 'title', 'slug', 'description', 'content_text', 'video_url', 'code_sandbox', 'duration_minutes', 'order'];

    protected $casts = ['code_sandbox' => 'array'];

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function quiz(): HasOne
    {
        return $this->hasOne(Quiz::class);
    }
}
