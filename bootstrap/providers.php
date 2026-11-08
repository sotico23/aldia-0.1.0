<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\ReverbAuthProvider;
use App\Providers\SystemSettingsServiceProvider;
use Laravel\Sanctum\SanctumServiceProvider;

return [
    SystemSettingsServiceProvider::class,
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    ReverbAuthProvider::class,
    SanctumServiceProvider::class,
];
