<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    // Server-side GA4 via the Measurement Protocol. Disabled unless both values are set.
    // Create the API secret in GA: Admin > Data streams > (web stream) > Measurement Protocol API secrets.
    'google_analytics' => [
        'measurement_id' => env('GA_MEASUREMENT_ID'),
        'api_secret' => env('GA_API_SECRET'),
        // Use https://region1.google-analytics.com to keep collection in the EU.
        'endpoint' => env('GA_ENDPOINT', 'https://www.google-analytics.com'),
        // true: also validate each hit against /debug/mp/collect (logged) and show events in GA DebugView.
        'debug' => (bool) env('GA_DEBUG', false),
        // UK/EU: analytics cookies need opt-in. When true, nothing is tracked (browser or server) until the
        // visitor accepts the cookie banner.
        'require_consent' => (bool) env('GA_REQUIRE_CONSENT', true),
    ],

];
