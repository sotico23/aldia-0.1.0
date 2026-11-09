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
                    'config' => [
                        'app.name',
                        'services.frontend.app_name',
                    ],
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
                    'config' => ['services.frontend.google_maps_api_key'],
                    'help' => 'Se entrega al navegador como configuración pública. Restringe esta clave en Google Cloud.',
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
                'MAIL_SCHEME' => [
                    'label' => 'Esquema SMTP',
                    'type' => 'text',
                    'default' => env('MAIL_SCHEME'),
                    'config' => ['mail.mailers.smtp.scheme'],
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
                    'config' => [
                        'broadcasting.connections.reverb.app_id',
                        'reverb.apps.apps.0.app_id',
                    ],
                ],
                'REVERB_APP_KEY' => [
                    'label' => 'Reverb App Key',
                    'type' => 'text',
                    'default' => env('REVERB_APP_KEY'),
                    'config' => [
                        'broadcasting.connections.reverb.key',
                        'reverb.apps.apps.0.key',
                    ],
                    'help' => 'Esta clave se expone al cliente; no es un secreto.',
                ],
                'REVERB_APP_SECRET' => [
                    'label' => 'Reverb App Secret',
                    'type' => 'password',
                    'secret' => true,
                    'config' => [
                        'broadcasting.connections.reverb.secret',
                        'reverb.apps.apps.0.secret',
                    ],
                ],
                'REVERB_HOST' => [
                    'label' => 'Host Reverb',
                    'type' => 'text',
                    'default' => env('REVERB_HOST'),
                    'config' => [
                        'broadcasting.connections.reverb.options.host',
                        'reverb.apps.apps.0.options.host',
                        'reverb.servers.reverb.hostname',
                    ],
                ],
                'REVERB_PORT' => [
                    'label' => 'Puerto Reverb',
                    'type' => 'number',
                    'default' => env('REVERB_PORT', 443),
                    'config' => [
                        'broadcasting.connections.reverb.options.port',
                        'reverb.apps.apps.0.options.port',
                    ],
                ],
                'REVERB_SCHEME' => [
                    'label' => 'Esquema Reverb',
                    'type' => 'select',
                    'options' => [
                        'https' => 'https',
                        'http' => 'http',
                    ],
                    'default' => env('REVERB_SCHEME', 'https'),
                    'config' => [
                        'broadcasting.connections.reverb.options.scheme',
                        'reverb.apps.apps.0.options.scheme',
                    ],
                ],
                'REVERB_SERVER_HOST' => [
                    'label' => 'Host de escucha del servidor',
                    'type' => 'text',
                    'default' => env('REVERB_SERVER_HOST', '0.0.0.0'),
                    'config' => ['reverb.servers.reverb.host'],
                    'help' => 'Requiere reiniciar el proceso de Reverb para aplicar el cambio.',
                ],
                'REVERB_SERVER_PORT' => [
                    'label' => 'Puerto de escucha del servidor',
                    'type' => 'number',
                    'default' => env('REVERB_SERVER_PORT', 8080),
                    'config' => ['reverb.servers.reverb.port'],
                    'help' => 'Requiere reiniciar el proceso de Reverb para aplicar el cambio.',
                ],
                'REVERB_ALLOWED_ORIGINS' => [
                    'label' => 'Orígenes permitidos',
                    'type' => 'text',
                    'default' => env('REVERB_ALLOWED_ORIGINS', '*'),
                    'config' => ['reverb.apps.apps.0.allowed_origins'],
                    'cast' => 'csv',
                    'help' => 'Separa varios dominios con comas. Usa * solo si realmente necesitas permitir cualquier origen.',
                ],
                'REVERB_PUBLIC_HOST' => [
                    'label' => 'Host público de Reverb',
                    'type' => 'text',
                    'default' => env('REVERB_PUBLIC_HOST', env('VITE_REVERB_HOST')),
                    'config' => ['reverb.public.host'],
                ],
                'REVERB_PUBLIC_PORT' => [
                    'label' => 'Puerto público de Reverb',
                    'type' => 'number',
                    'default' => env('REVERB_PUBLIC_PORT', env('VITE_REVERB_PORT', 443)),
                    'config' => ['reverb.public.port'],
                ],
                'REVERB_PUBLIC_SCHEME' => [
                    'label' => 'Esquema público de Reverb',
                    'type' => 'select',
                    'options' => [
                        'https' => 'https',
                        'http' => 'http',
                    ],
                    'default' => env('REVERB_PUBLIC_SCHEME', env('VITE_REVERB_SCHEME', 'https')),
                    'config' => ['reverb.public.scheme'],
                ],
                'VITE_REVERB_APP_KEY' => [
                    'label' => 'Clave Reverb para frontend (Vite)',
                    'type' => 'text',
                    'default' => env('VITE_REVERB_APP_KEY', env('REVERB_APP_KEY')),
                    'config' => ['services.frontend.reverb.key'],
                    'help' => 'Es una clave pública para conectar el navegador con Reverb.',
                ],
                'VITE_REVERB_HOST' => [
                    'label' => 'Host Reverb para frontend (Vite)',
                    'type' => 'text',
                    'default' => env('VITE_REVERB_HOST', env('REVERB_PUBLIC_HOST', env('REVERB_HOST'))),
                    'config' => ['services.frontend.reverb.host'],
                ],
                'VITE_REVERB_PORT' => [
                    'label' => 'Puerto Reverb para frontend (Vite)',
                    'type' => 'number',
                    'default' => env('VITE_REVERB_PORT', env('REVERB_PUBLIC_PORT', env('REVERB_PORT', 443))),
                    'config' => ['services.frontend.reverb.port'],
                ],
                'VITE_REVERB_SCHEME' => [
                    'label' => 'Esquema Reverb para frontend (Vite)',
                    'type' => 'select',
                    'options' => [
                        'https' => 'https',
                        'http' => 'http',
                    ],
                    'default' => env('VITE_REVERB_SCHEME', env('REVERB_PUBLIC_SCHEME', env('REVERB_SCHEME', 'https'))),
                    'config' => ['services.frontend.reverb.scheme'],
                ],
            ],
        ],

        'aws' => [
            'label' => 'AWS / Almacenamiento S3',
            'description' => 'Credenciales usadas por el almacenamiento S3 y Amazon SES.',
            'fields' => [
                'AWS_ACCESS_KEY_ID' => [
                    'label' => 'Access Key ID',
                    'type' => 'password',
                    'secret' => true,
                    'config' => [
                        'filesystems.disks.s3.key',
                        'services.ses.key',
                    ],
                ],
                'AWS_SECRET_ACCESS_KEY' => [
                    'label' => 'Secret Access Key',
                    'type' => 'password',
                    'secret' => true,
                    'config' => [
                        'filesystems.disks.s3.secret',
                        'services.ses.secret',
                    ],
                ],
                'AWS_DEFAULT_REGION' => [
                    'label' => 'Región AWS',
                    'type' => 'text',
                    'default' => env('AWS_DEFAULT_REGION', 'us-east-1'),
                    'config' => [
                        'filesystems.disks.s3.region',
                        'services.ses.region',
                    ],
                ],
                'AWS_BUCKET' => [
                    'label' => 'Bucket S3',
                    'type' => 'text',
                    'default' => env('AWS_BUCKET'),
                    'config' => ['filesystems.disks.s3.bucket'],
                ],
                'AWS_USE_PATH_STYLE_ENDPOINT' => [
                    'label' => 'Usar endpoint estilo path',
                    'type' => 'select',
                    'options' => [
                        'true' => 'Sí',
                        'false' => 'No',
                    ],
                    'default' => env('AWS_USE_PATH_STYLE_ENDPOINT', false) ? 'true' : 'false',
                    'config' => ['filesystems.disks.s3.use_path_style_endpoint'],
                    'cast' => 'boolean',
                ],
            ],
        ],

        'openrouter' => [
            'label' => 'OpenRouter / IA',
            'description' => 'Credencial del proveedor OpenRouter.',
            'fields' => [
                'OPENROUTER_API_KEY' => [
                    'label' => 'API Key de OpenRouter',
                    'type' => 'password',
                    'secret' => true,
                    'config' => ['services.openrouter.api_key'],
                ],
            ],
        ],

        'sii' => [
            'label' => 'SII / Facturación electrónica',
            'description' => 'URLs de los servicios del SII para certificación y producción.',
            'fields' => [
                'SII_SEMILLA_URL' => [
                    'label' => 'Certificación — URL de semilla',
                    'type' => 'url',
                    'default' => env('SII_SEMILLA_URL', 'https://maullin.sii.cl/DTEWS/CrSeed.jws'),
                    'config' => ['sii.urls.certificacion.semilla'],
                ],
                'SII_TOKEN_URL' => [
                    'label' => 'Certificación — URL de token',
                    'type' => 'url',
                    'default' => env('SII_TOKEN_URL', 'https://maullin.sii.cl/DTEWS/GetTokenFromSeed.jws'),
                    'config' => ['sii.urls.certificacion.token'],
                ],
                'SII_ENVIO_DTE_URL' => [
                    'label' => 'Certificación — URL de envío DTE',
                    'type' => 'url',
                    'default' => env('SII_ENVIO_DTE_URL', 'https://maullin.sii.cl/cgi_dte/UPL/DTEUpload'),
                    'config' => ['sii.urls.certificacion.envio_dte'],
                ],
                'SII_PROD_SEMILLA_URL' => [
                    'label' => 'Producción — URL de semilla',
                    'type' => 'url',
                    'default' => env('SII_PROD_SEMILLA_URL', 'https://palena.sii.cl/DTEWS/CrSeed.jws'),
                    'config' => ['sii.urls.produccion.semilla'],
                ],
                'SII_PROD_TOKEN_URL' => [
                    'label' => 'Producción — URL de token',
                    'type' => 'url',
                    'default' => env('SII_PROD_TOKEN_URL', 'https://palena.sii.cl/DTEWS/GetTokenFromSeed.jws'),
                    'config' => ['sii.urls.produccion.token'],
                ],
                'SII_PROD_ENVIO_DTE_URL' => [
                    'label' => 'Producción — URL de envío DTE',
                    'type' => 'url',
                    'default' => env('SII_PROD_ENVIO_DTE_URL', 'https://palena.sii.cl/cgi_dte/UPL/DTEUpload'),
                    'config' => ['sii.urls.produccion.envio_dte'],
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
