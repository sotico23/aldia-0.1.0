<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\ReverbAuthProvider;
use App\Providers\SystemSettingsServiceProvider;
use Laravel\Sanctum\SanctumServiceProvider;
use Livewire\LivewireServiceProvider;

return [
    SystemSettingsServiceProvider::class,
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    ReverbAuthProvider::class,
    SanctumServiceProvider::class,
    LivewireServiceProvider::class,
];
