<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class BusinessScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (Auth::check()) {
            $user = Auth::user();

            if ($user->hasRole('Master') || $user->hasRole('Super Admin')) {
                return;
            }

            $builder->where(fn ($query) => $query->where($model->qualifyColumn('business_id'), $user->getOwnerId()));
        }
    }
}
