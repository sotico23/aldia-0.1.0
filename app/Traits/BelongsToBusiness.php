<?php

namespace App\Traits;

use App\Scopes\BusinessScope;
use Illuminate\Support\Facades\Auth;

trait BelongsToBusiness
{
    protected static function bootBelongsToBusiness(): void
    {
        static::creating(function ($model) {
            if (Auth::check() && ! $model->business_id) {
                $model->business_id = Auth::user()->getOwnerId();
            }
        });

        static::addGlobalScope(new BusinessScope);
    }
}
