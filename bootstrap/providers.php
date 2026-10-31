<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\ReverbAuthProvider;
use Laravel\Sanctum\SanctumServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    ReverbAuthProvider::class,
    SanctumServiceProvider::class,
];
