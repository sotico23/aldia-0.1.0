<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Country
    |--------------------------------------------------------------------------
    |
    | The default country code used when no country is set for a user.
    | Must match a code in the countries table.
    |
    */

    'default' => env('DEFAULT_COUNTRY', 'CL'),

    /*
    |--------------------------------------------------------------------------
    | Geolocation Provider
    |--------------------------------------------------------------------------
    |
    | Configuration for IP-based country detection.
    | Used as fallback when user has no country assigned.
    |
    */

    'geolocation' => [
        'enabled' => env('GEOLOCATION_ENABLED', true),
        'api_url' => env('GEOLOCATION_API_URL', 'http://ip-api.com/json/{ip}?fields=countryCode'),
        'cache_ttl' => env('GEOLOCATION_CACHE_TTL', 86400), // 24 hours
        'timeout' => env('GEOLOCATION_TIMEOUT', 2), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported Countries
    |--------------------------------------------------------------------------
    |
    | Quick-access array for common lookups without hitting the database.
    | The full config lives in the countries table.
    |
    */

    'supported' => [
        'PE' => [
            'currency_code' => 'PEN',
            'currency_symbol' => 'S/',
            'locale' => 'es-PE',
            'timezone' => 'America/Lima',
            'tax_name' => 'IGV',
            'tax_rate' => 18.00,
            'fiscal_id_label' => 'RUC',
        ],
        'CL' => [
            'currency_code' => 'CLP',
            'currency_symbol' => '$',
            'locale' => 'es-CL',
            'timezone' => 'America/Santiago',
            'tax_name' => 'IVA',
            'tax_rate' => 19.00,
            'fiscal_id_label' => 'RUT',
        ],
    ],

];
