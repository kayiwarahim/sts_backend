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

    'sts' => [
        'base_url' => env('STS_BASE_URL'),
        'user_id' => env('STS_USER_ID'),
        'password' => env('STS_PASSWORD'),
        'meter_type' => (int) env('STS_METER_TYPE', 2),
    ],

    'relworx' => [
        'base_url' => env('RELWORX_BASE_URL'),
        'account_no' => env('RELWORX_ACCOUNT_NO'),
        'bearer_token' => env('RELWORX_BEARER_TOKEN'),
        'timeout' => (int) env('RELWORX_TIMEOUT', 30),
        'webhook_key' => env('RELWORX_WEBHOOK_KEY'),
        'webhook_url' => env('RELWORX_WEBHOOK_URL'),
        'webhook_tolerance' => (int) env('RELWORX_WEBHOOK_TOLERANCE', 300),
    ],
];
