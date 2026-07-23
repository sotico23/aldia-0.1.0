<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseDiscussion extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'user_id',
        'message',
        'parent_id',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(CourseDiscussion::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(CourseDiscussion::class, 'parent_id');
    }
}
