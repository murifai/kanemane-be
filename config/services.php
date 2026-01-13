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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
    ],

    'whatsapp' => [
        // WAHA Configuration
        'waha_url' => env('WAHA_URL'),
        'waha_session' => env('WAHA_SESSION'),
        'waha_api_key' => env('WAHA_API_KEY'),
        'webhook_secret' => env('WAHA_WEBHOOK_SECRET'),

        // Legacy Meta API (keep for reference/rollback)
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'app_secret' => env('WHATSAPP_APP_SECRET'),
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
    ],

    'midtrans' => [
        'server_key' => env('MIDTRANS_SERVER_KEY'),
        'client_key' => env('MIDTRANS_CLIENT_KEY'),
        'is_production' => env('MIDTRANS_IS_PRODUCTION', false),
    ],

    'lynk' => [
        'products' => [
            'basic' => [
                'amount' => 24000,
                'plan_id' => 'basic',
            ],
            'pro' => [
                'amount' => 49000,
                'plan_id' => 'pro',
            ],
        ],
        'urls' => [
            'basic' => 'http://lynk.id/kanemane/xov5m9ovy8yy/checkout',
            'pro' => 'http://lynk.id/kanemane/jvd1kz0oxknk/checkout',
        ],
    ],

];
