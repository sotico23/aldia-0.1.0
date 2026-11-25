<?php

$appUrl = rtrim((string) env('APP_URL', 'http://localhost'), '/');

return [
    'pushy' => [
        'enabled' => env('PUSHY_ENABLED', false),
        'secret_api_key' => env('PUSHY_SECRET_API_KEY'),
        'app_id' => env('PUSHY_APP_ID'),
        'fallback' => [
            'enabled' => env('PUSHY_ENABLED', false),
            'secret_api_key' => env('PUSHY_SECRET_API_KEY'),
            'app_id' => env('PUSHY_APP_ID'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI') ?: $appUrl.'/auth/google/callback',
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI') ?: $appUrl.'/auth/facebook/callback',
    ],

    'mailtrap' => [
        'token' => env('MAILTRAP_API_TOKEN'),
        'domain' => env('MAILTRAP_SENDING_DOMAIN', 'demomailtrap.co'),
    ],

    'telegram' => [
        'default_chat_id' => env('TELEGRAM_DEFAULT_CHAT_ID'),
        'bot' => env('TELEGRAM_LOGIN_BOT_NAME'),
        'client_id' => null,
        'client_secret' => env('TELEGRAM_LOGIN_BOT_TOKEN'),
        'redirect' => env('TELEGRAM_LOGIN_REDIRECT_URI') ?: $appUrl.'/auth/telegram/callback',
        // Max age, en segundos, de un auth_date de Telegram Login Widget para evitar ataques replay.
        'login_max_age' => env('TELEGRAM_LOGIN_MAX_AGE', 300),
        // Secreto para verificar webhooks de Telegram (header X-Telegram-Bot-Api-Secret-Token).
        // Si se configura, los webhooks sin este header o con valor incorrecto se rechazan con 401.
        'webhook_secret_token' => env('TELEGRAM_WEBHOOK_SECRET_TOKEN'),
    ],

    'whatsapp' => [
        'default_to' => env('WHATSAPP_DEFAULT_TO'),
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
    ],

    'http_verify_tls' => env('HTTP_VERIFY_TLS', true),

    'n8n' => [
        'token' => env('N8N_API_TOKEN'),
        'webhook_url' => env('N8N_WEBHOOK_URL'),
        'base_url' => env('N8N_BASE_URL'),
        'telegram_proxy_url' => env('N8N_TELEGRAM_PROXY_URL', 'https://n8n.redcliente.cl/webhook/telegram-proxy'),
    ],

    'llm' => [
        'enabled' => env('LLM_ENABLED', false),
        'endpoint' => env('LLM_ENDPOINT', 'https://api.openai.com/v1/chat/completions'),
        'api_key' => env('LLM_API_KEY'),
        'model' => env('LLM_MODEL', 'gpt-4o-mini'),
        'max_tokens' => env('LLM_MAX_TOKENS', 500),
        'memory_window' => env('LLM_MEMORY_WINDOW', 20),
    ],

    'openrouter' => [
        'api_key' => env('OPENROUTER_API_KEY'),
    ],

    'frontend' => [
        'app_name' => env('VITE_APP_NAME', env('APP_NAME', 'Laravel')),
        'google_maps_api_key' => env('VITE_GOOGLE_MAPS_API_KEY'),
        'reverb' => [
            'key' => env('VITE_REVERB_APP_KEY', env('REVERB_APP_KEY')),
            'host' => env('VITE_REVERB_HOST', env('REVERB_PUBLIC_HOST', env('REVERB_HOST'))),
            'port' => env('VITE_REVERB_PORT', env('REVERB_PUBLIC_PORT', env('REVERB_PORT', 443))),
            'scheme' => env('VITE_REVERB_SCHEME', env('REVERB_PUBLIC_SCHEME', env('REVERB_SCHEME', 'https'))),
        ],
    ],

    'currency' => [
        'clp_to_usd_rate' => env('CLP_TO_USD_RATE', 0.0011), // ~909 CLP per USD
    ],

];
