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
        'client_id' => env('GOOGLE_DRIVE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_DRIVE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_DRIVE_REDIRECT_URI'),
    ],

    // Login social com Google. Namespace separado do 'google' acima, que é
    // usado pela integração de Google Drive das clínicas — são apps OAuth
    // do Google Cloud distintos, com escopos e redirect URIs diferentes.
    'google_login' => [
        'client_id' => env('GOOGLE_LOGIN_CLIENT_ID'),
        'client_secret' => env('GOOGLE_LOGIN_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_LOGIN_REDIRECT_URI'),
    ],

    // Sign in with Apple. Requer um Services ID (client_id), Team ID e uma
    // Sign in with Apple key (key_id + chave privada .p8) gerados no Apple
    // Developer Portal — ver .env.example para o que falta configurar.
    'apple_login' => [
        'client_id' => env('APPLE_LOGIN_CLIENT_ID'),
        'client_secret' => env('APPLE_LOGIN_CLIENT_SECRET'),
        'team_id' => env('APPLE_LOGIN_TEAM_ID'),
        'key_id' => env('APPLE_LOGIN_KEY_ID'),
        'private_key' => env('APPLE_LOGIN_PRIVATE_KEY'),
        'redirect' => env('APPLE_LOGIN_REDIRECT_URI'),
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook' => [
            'secret' => env('STRIPE_WEBHOOK_SECRET'),
            'tolerance' => env('STRIPE_WEBHOOK_TOLERANCE', 300),
        ],
    ],

    'analytics' => [
        'ga_measurement_id' => env('GA_MEASUREMENT_ID'),
        'meta_pixel_id' => env('META_PIXEL_ID'),
    ],

];
