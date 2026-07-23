<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Currency
    |--------------------------------------------------------------------------
    |
    | The default currency used throughout the application.
    |
    */

    'default' => env('DEFAULT_CURRENCY', 'CLP'),

    /*
    |--------------------------------------------------------------------------
    | Supported Currencies
    |--------------------------------------------------------------------------
    |
    | All supported currencies with their metadata: symbol, decimals, locale.
    |
    */

    'supported' => [
        'CLP' => ['symbol' => '$', 'decimals' => 0, 'locale' => 'es-CL', 'name' => 'Peso Chileno'],
        'COP' => ['symbol' => '$', 'decimals' => 0, 'locale' => 'es-CO', 'name' => 'Peso Colombiano'],
        'PEN' => ['symbol' => 'S/', 'decimals' => 2, 'locale' => 'es-PE', 'name' => 'Sol Peruano'],
        'ARS' => ['symbol' => '$', 'decimals' => 2, 'locale' => 'es-AR', 'name' => 'Peso Argentino'],
        'BOB' => ['symbol' => 'Bs', 'decimals' => 2, 'locale' => 'es-BO', 'name' => 'Boliviano'],
        'USD' => ['symbol' => '$', 'decimals' => 2, 'locale' => 'en-US', 'name' => 'Dólar'],
        'BRL' => ['symbol' => 'R$', 'decimals' => 2, 'locale' => 'pt-BR', 'name' => 'Real Brasileño'],
        'VES' => ['symbol' => 'Bs', 'decimals' => 2, 'locale' => 'es-VE', 'name' => 'Bolívar Venezolano'],
        'UYU' => ['symbol' => '$', 'decimals' => 2, 'locale' => 'es-UY', 'name' => 'Peso Uruguayo'],
        'PYG' => ['symbol' => '₲', 'decimals' => 0, 'locale' => 'es-PY', 'name' => 'Guaraní'],
        'GTQ' => ['symbol' => 'Q', 'decimals' => 2, 'locale' => 'es-GT', 'name' => 'Quetzal'],
    ],

];
