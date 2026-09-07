<?php
/**
 * Reverb Configuration
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Reverb Server Configuration
    |--------------------------------------------------------------------------
    */
    'server' => [
        'host' => env('REVERB_HOST', '127.0.0.1'),
        'port' => env('REVERB_PORT', 8080),
        'scheme' => env('REVERB_SCHEME', 'http'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */
    'auth' => [
        'enabled' => true,
        'provider' => 'sanctum',
        'middleware' => [
            \App\Http\Middleware\ReverbAuthMiddleware::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Channels Configuration
    |--------------------------------------------------------------------------
    */
    'channels' => [
        'notifications' => [
            'prefix' => 'notifications',
            'middleware' => [
                \App\Http\Middleware\ReverbRateLimitMiddleware::class . ':notifications',
            ],
            'rate_limit' => 'reverb:notifications',
        ],
        'delivery' => [
            'driver' => [
                'prefix' => 'delivery.driver',
                'middleware' => [
                    \App\Http\Middleware\ReverbRateLimitMiddleware::class . ':delivery',
                ],
                'rate_limit' => 'reverb:delivery',
            ],
            'orders' => [
                'prefix' => 'delivery.orders',
                'middleware' => [
                    \App\Http\Middleware\ReverbRateLimitMiddleware::class . ':delivery',
                ],
                'rate_limit' => 'reverb:delivery',
            ],
        ],
        'chat' => [
            'conversation' => [
                'prefix' => 'chat.conversation',
                'middleware' => [
                    \App\Http\Middleware\ReverbRateLimitMiddleware::class . ':chat',
                ],
                'rate_limit' => 'reverb:chat',
            ],
            'presence' => [
                'prefix' => 'presence-conversation',
                'middleware' => [
                    \App\Http\Middleware\ReverbRateLimitMiddleware::class . ':presence',
                ],
                'rate_limit' => 'reverb:presence',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Presence Configuration
    |--------------------------------------------------------------------------
    */
    'presence' => [
        'enabled' => true,
        'tracker' => \App\Services\PresenceTracker::class,
        'ttl' => 300, // 5 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Event Broadcasting
    |--------------------------------------------------------------------------
    */
    'broadcasting' => [
        'driver' => 'reverb',
        'connection' => 'reverb',
    ],

    /*
    |--------------------------------------------------------------------------
    | SSL Configuration
    |--------------------------------------------------------------------------
    */
    'ssl' => [
        'enabled' => env('REVERB_SSL_ENABLED', false),
        'cert_path' => env('REVERB_SSL_CERT_PATH'),
        'key_path' => env('REVERB_SSL_KEY_PATH'),
    ],
];