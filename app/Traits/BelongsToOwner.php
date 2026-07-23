<?php

namespace App\Traits;

use App\Models\User;
use App\Scopes\OwnerScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait BelongsToOwner
{
    /**
     * Boot the BelongsToOwner trait for the model.
     */
    protected static function bootBelongsToOwner(): void
    {
        static::creating(function ($model) {
            if (Auth::check() && ! $model->owner_id) {
                $model->owner_id = Auth::user()->getOwnerId();
            }
        });

        static::addGlobalScope(new OwnerScope);
    }

    /**
     * Get the user that owns the record.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get the owner ID for ownership checks.
     */
    public function getOwnerId(): ?int
    {
        return $this->owner_id;
    }
}
