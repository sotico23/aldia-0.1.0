<?php

namespace App\Models;

use Database\Factories\UserDashboardWidgetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDashboardWidget extends Model
{
    /** @use HasFactory<UserDashboardWidgetFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'widget_key',
        'order_index',
        'settings',
    ];

    public function casts(): array
    {
        return [
            'settings' => 'array',
            'order_index' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
