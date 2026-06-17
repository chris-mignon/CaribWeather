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

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'caribweather' => [
        'use_live_providers' => env('CARIBWEATHER_USE_LIVE_PROVIDERS', true),
        'cache_ttl_minutes' => (int) env('WEATHER_CACHE_TTL_MINUTES', 10),
        'historical_cache_ttl_minutes' => (int) env('HISTORICAL_CACHE_TTL_MINUTES', 60),
    ],

    'openweather' => [
        'key' => env('OPENWEATHER_API_KEY'),
    ],

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
    ],

    'meteostat' => [
        'key' => env('METEOSTAT_API_KEY'),
        'host' => env('METEOSTAT_API_HOST', 'meteostat.p.rapidapi.com'),
        'base_url' => env('METEOSTAT_API_BASE_URL', 'https://meteostat.p.rapidapi.com'),
    ],

];
