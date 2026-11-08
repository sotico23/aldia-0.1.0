<?php

/*
|--------------------------------------------------------------------------
| System Settings Catalog
|--------------------------------------------------------------------------
|
| This catalog drives the "Ajustes del Servidor y Base de Datos" screen and
| the runtime configuration injection performed by SystemSettings.
|
| Each field supports:
| - label: Human readable label.
| - type: text, password, number, email, select.
| - secret: Whether the value must be encrypted at rest and masked in the UI.
| - default: Fallback value used when the database has no stored value.
| - options: Available values for select inputs.
| - config: One or more dot-notation config keys overridden at runtime.
| - help: Extra hint rendered below the input.
|
*/

return [

    'groups' => [

        'app' => [
            'label' => 'Aplicación / Google Maps',
            'description' => 'Nombre de la aplicación y credenciales de Google Maps.',
            'fields' => [
                'VITE_APP_NAME' => [
                    'label' => 'Nombre de la aplicación',
                    'type' => 'text',
                    'default' => env('VITE_APP_NAME', env('APP_NAME', 'Laravel')),
                    'config' => ['app.name'],
                    'help' => 'También se usa como nombre visible en el panel.',
                ],
                'GOOGLE_MAPS_API_KEY' => [
                    'label' => 'Google Maps API Key (servidor)',
                    'type' => 'password',
                    'secret' => true,
                    'config' => ['services.google.maps_api_key'],
                ],
                'VITE_GOOGLE_MAPS_API_KEY' => [
                    'label' => 'Google Maps API Key (frontend)',
                    'type' => 'password',
                    'secret' => true,
                    'help' => 'Vite compila este valor; requiere reconstruir el frontend (npm run build) para propagarse al navegador.',
                ],
            ],
        ],

        'mail' => [
            'label' => 'Correo (Mail)',
            'description' => 'Servidor SMTP usado para notificaciones, facturas y recuperación de contraseña.',
            'fields' => [
                'MAIL_MAILER' => [
                    'label' => 'Driver de correo',
                    'type' => 'select',
                    'options' => [
                        'smtp' => 'SMTP',
                        'log' => 'Log',
                        'array' => 'Array',
                        'sendmail' => 'Sendmail',
                        'ses' => 'Amazon SES',
                        'postmark' => 'Postmark',
                        'resend' => 'Resend',
                        'mailgun' => 'Mailgun',
                        'failover' => 'Failover',
                        'roundrobin' => 'Round Robin',
                    ],
                    'default' => env('MAIL_MAILER', 'log'),
                    'config' => ['mail.default'],
                ],
                'MAIL_HOST' => [
                    'label' => 'Host SMTP',
                    'type' => 'text',
                    'default' => env('MAIL_HOST'),
                    'config' => ['mail.mailers.smtp.host'],
                ],
                'MAIL_PORT' => [
                    'label' => 'Puerto SMTP',
                    'type' => 'number',
                    'default' => env('MAIL_PORT', 2525),
                    'config' => ['mail.mailers.smtp.port'],
                ],
                'MAIL_USERNAME' => [
                    'label' => 'Usuario SMTP',
                    'type' => 'text',
                    'default' => env('MAIL_USERNAME'),
                    'config' => ['mail.mailers.smtp.username'],
                ],
                'MAIL_PASSWORD' => [
                    'label' => 'Contraseña SMTP',
                    'type' => 'password',
                    'secret' => true,
                    'config' => ['mail.mailers.smtp.password'],
                ],
                'MAIL_FROM_ADDRESS' => [
                    'label' => 'Correo remitente',
                    'type' => 'email',
                    'default' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
                    'config' => ['mail.from.address'],
                ],
                'MAIL_FROM_NAME' => [
                    'label' => 'Nombre remitente',
                    'type' => 'text',
                    'default' => env('MAIL_FROM_NAME', env('APP_NAME', 'Laravel')),
                    'config' => ['mail.from.name'],
                ],
            ],
        ],

        'redis' => [
            'label' => 'Redis',
            'description' => 'Conexión usada por caché, colas y locks.',
            'fields' => [
                'REDIS_CLIENT' => [
                    'label' => 'Cliente Redis',
                    'type' => 'select',
                    'options' => [
                        'phpredis' => 'phpredis',
                        'predis' => 'predis',
                    ],
                    'default' => env('REDIS_CLIENT', 'phpredis'),
                    'config' => ['database.redis.client'],
                ],
                'REDIS_HOST' => [
                    'label' => 'Host Redis',
                    'type' => 'text',
                    'default' => env('REDIS_HOST', '127.0.0.1'),
                    'config' => [
                        'database.redis.default.host',
                        'database.redis.cache.host',
                    ],
                ],
                'REDIS_PASSWORD' => [
                    'label' => 'Contraseña Redis',
                    'type' => 'password',
                    'secret' => true,
                    'config' => [
                        'database.redis.default.password',
                        'database.redis.cache.password',
                    ],
                ],
                'REDIS_PORT' => [
                    'label' => 'Puerto Redis',
                    'type' => 'number',
                    'default' => env('REDIS_PORT', 6379),
                    'config' => [
                        'database.redis.default.port',
                        'database.redis.cache.port',
                    ],
                ],
            ],
        ],

        'reverb' => [
            'label' => 'Reverb (WebSockets)',
            'description' => 'Servidor de broadcasting en tiempo real.',
            'fields' => [
                'BROADCAST_CONNECTION' => [
                    'label' => 'Conexión de broadcasting',
                    'type' => 'select',
                    'options' => [
                        'reverb' => 'Reverb',
                        'log' => 'Log',
                        'null' => 'Null',
                        'pusher' => 'Pusher',
                        'redis' => 'Redis',
                        'ably' => 'Ably',
                    ],
                    'default' => env('BROADCAST_CONNECTION', 'null'),
                    'config' => ['broadcasting.default'],
                ],
                'REVERB_APP_ID' => [
                    'label' => 'Reverb App ID',
                    'type' => 'text',
                    'default' => env('REVERB_APP_ID'),
                    'config' => ['broadcasting.connections.reverb.app_id'],
                ],
                'REVERB_APP_KEY' => [
                    'label' => 'Reverb App Key',
                    'type' => 'text',
                    'default' => env('REVERB_APP_KEY'),
                    'config' => ['broadcasting.connections.reverb.key'],
                    'help' => 'Esta clave se expone al cliente; no es un secreto.',
                ],
                'REVERB_APP_SECRET' => [
                    'label' => 'Reverb App Secret',
                    'type' => 'password',
                    'secret' => true,
                    'config' => ['broadcasting.connections.reverb.secret'],
                ],
                'REVERB_HOST' => [
                    'label' => 'Host Reverb',
                    'type' => 'text',
                    'default' => env('REVERB_HOST'),
                    'config' => ['broadcasting.connections.reverb.options.host'],
                ],
                'REVERB_PORT' => [
                    'label' => 'Puerto Reverb',
                    'type' => 'number',
                    'default' => env('REVERB_PORT', 443),
                    'config' => ['broadcasting.connections.reverb.options.port'],
                ],
                'REVERB_SCHEME' => [
                    'label' => 'Esquema Reverb',
                    'type' => 'select',
                    'options' => [
                        'https' => 'https',
                        'http' => 'http',
                    ],
                    'default' => env('REVERB_SCHEME', 'https'),
                    'config' => ['broadcasting.connections.reverb.options.scheme'],
                ],
            ],
        ],

        'database' => [
            'label' => 'Base de Datos',
            'description' => 'Credenciales de la conexión principal. Se guardan cifradas y se inyectan al arrancar la aplicación.',
            'fields' => [
                'DB_CONNECTION' => [
                    'label' => 'Driver de base de datos',
                    'type' => 'select',
                    'options' => [
                        'mysql' => 'MySQL',
                        'mariadb' => 'MariaDB',
                        'pgsql' => 'PostgreSQL',
                        'sqlsrv' => 'SQL Server',
                        'sqlite' => 'SQLite',
                    ],
                    'default' => env('DB_CONNECTION', 'mysql'),
                ],
                'DB_HOST' => [
                    'label' => 'Host',
                    'type' => 'text',
                    'default' => env('DB_HOST', '127.0.0.1'),
                ],
                'DB_PORT' => [
                    'label' => 'Puerto',
                    'type' => 'number',
                    'default' => env('DB_PORT', 3306),
                ],
                'DB_DATABASE' => [
                    'label' => 'Base de datos',
                    'type' => 'text',
                    'default' => env('DB_DATABASE'),
                ],
                'DB_USERNAME' => [
                    'label' => 'Usuario',
                    'type' => 'text',
                    'default' => env('DB_USERNAME'),
                ],
                'DB_PASSWORD' => [
                    'label' => 'Contraseña',
                    'type' => 'password',
                    'secret' => true,
                ],
            ],
        ],

    ],

];
