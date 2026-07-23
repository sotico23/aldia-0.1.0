<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Role extends \Spatie\Permission\Models\Role
{
    use HasFactory;

    protected $fillable = [
        'name',
        'guard_name',
        'owner_id',
        'created_by',
        'level',
    ];

    protected static function booted(): void
    {
        // Global scope removed — breaks Spatie permission loading.
        // Use local scopes ->system() / ->ownedBy() / ->custom() where needed.
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeOwnedBy($query, int $ownerId)
    {
        return $query->where('owner_id', $ownerId);
    }

    public function scopeSystem($query)
    {
        return $query->whereNull('owner_id');
    }

    public function scopeCustom($query)
    {
        return $query->whereNotNull('owner_id');
    }

    public function isSystem(): bool
    {
        return $this->owner_id === null;
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
