<?php

return [

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
        'redirect' => env('GOOGLE_REDIRECT_URI', env('APP_URL').'/auth/google/callback'),
        'maps_api_key' => env('GOOGLE_MAPS_API_KEY'),
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI', env('APP_URL').'/auth/facebook/callback'),
    ],

    'mailtrap' => [
        'token' => env('MAILTRAP_API_TOKEN'),
        'domain' => env('MAILTRAP_SENDING_DOMAIN', 'demomailtrap.co'),
    ],

    'telegram' => [
        'default_chat_id' => env('TELEGRAM_DEFAULT_CHAT_ID'),
    ],

    'whatsapp' => [
        'default_to' => env('WHATSAPP_DEFAULT_TO'),
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
    ],

    'n8n' => [
        'token' => env('N8N_API_TOKEN'),
        'webhook_url' => env('N8N_WEBHOOK_URL'),
        'base_url' => env('N8N_BASE_URL'),
    ],

];
