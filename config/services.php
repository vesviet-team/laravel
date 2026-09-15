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

    'goship' => [
        'base_url' => env('GOSHIP_BASE_URL', 'https://api.goship.io/api/v2'),
        'token' => env('GOSHIP_TOKEN', ''),
        'warehouse_name' => env('GOSHIP_WAREHOUSE_NAME', 'MYSHOP Store'),
        'warehouse_phone' => env('GOSHIP_WAREHOUSE_PHONE', '0901234567'),
        'warehouse_address' => env('GOSHIP_WAREHOUSE_ADDRESS', '123 Nguyen Van Linh'),
        'warehouse_city_id' => env('GOSHIP_WAREHOUSE_CITY_ID', '1'),
        'warehouse_district_id' => env('GOSHIP_WAREHOUSE_DISTRICT_ID', '1'),
    ],

    'banking' => [
        'bank_code' => env('BANK_CODE', 'MB'),
        'account_no' => env('BANK_ACCOUNT_NO', '0123456789'),
        'account_name' => env('BANK_ACCOUNT_NAME', 'MYSTORE COMPANY'),
    ],

];
