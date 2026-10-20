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

    // AI audit: Claude API (https://console.anthropic.com)
    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'audit_model' => env('ANTHROPIC_AUDIT_MODEL', 'claude-haiku-5-5'),
        // Audit new leads automatically when a Lead Finder search finishes.
        'auto_audit' => (bool) env('CLOYNE_AUTO_AUDIT', true),
    ],

    // Lead Finder: Google Maps scraping through Apify (https://apify.com)
    'apify' => [
        'token' => env('APIFY_TOKEN'),
        'maps_actor' => env('APIFY_MAPS_ACTOR', 'compass/crawler-google-places'),
    ],

];
