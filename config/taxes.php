<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tax Rates
    |--------------------------------------------------------------------------
    |
    | Centralized tax rate configuration for the application.
    | Default IVA (Value Added Tax) rate for Chile is 19%.
    |
    */

    'iva_rate' => env('IVA_RATE', 0.19),
];
