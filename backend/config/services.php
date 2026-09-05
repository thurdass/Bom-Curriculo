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

    'firebase' => [
        'mobile' => [
            'credential' => [
                'projectId' => env('FIREBASE_MOBILE_PROJECT_ID'),
                'clientEmail' => env('FIREBASE_MOBILE_CLIENT_EMAIL'),
                'privateKey' => env('FIREBASE_MOBILE_PRIVATE_KEY'),
            ],
        ],
    ],

    'bot' => [
        'url' => env('BOT_URL', 'http://127.0.0.1:8000'),
        'connect_timeout_seconds' => (float) env('BOT_CONNECT_TIMEOUT_SECONDS', 5),
        'health_timeout_seconds' => (float) env('BOT_HEALTH_TIMEOUT_SECONDS', 5),
        'timeout_seconds' => (float) env('BOT_TIMEOUT_SECONDS', 260),
    ],

];
