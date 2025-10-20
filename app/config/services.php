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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    'supabase' => [
        'url' => env('SUPABASE_URL'),
        'key' => env('SUPABASE_KEY'),
    ],

    'prodamus' => [
        'base_url' => env('PRODAMUS_BASE_URL', 'https://demivika.payform.ru/'),
        'payform_url' => env('PRODAMUS_PAYFORM_URL', 'https://demivika.payform.ru/'),
        'secret_key' => env('PRODAMUS_SECRET_KEY', '61f2852777240e53c6dfe9afa2fc66719cf9c1fd7e8beec83f15f50f17798b67'),
        'sys_code' => env('PRODAMUS_SYS_CODE', 'tma'),
    ],

    'demivika' => [
        'base_url' => 'https://web.vikademi.ru/api/admin/grant-access',
        'api_key' => 'demivika_admin_secret_key_2025',
    ],
];
