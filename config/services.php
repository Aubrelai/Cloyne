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
        // Model that writes outreach emails (better writer than the audit model).
        'draft_model' => env('ANTHROPIC_DRAFT_MODEL', 'claude-sonnet-5-5'),
        // Write a draft automatically when an audit finds a hot lead with an email.
        'auto_draft' => (bool) env('CLOYNE_AUTO_DRAFT', true),
        // Dollars per million tokens [input, output], for Cloyne's cost tracking (Settings and the CRM Profit page).
        // From https://platform.claude.com/docs/en/about-claude/pricing (October 2026). Update if Anthropic changes them.
        'prices' => [
            'claude-haiku-5-5' => [0.10, 0.50],
            'claude-sonnet-5-5' => [2.00, 10.00],
            'default' => [2.00, 10.00],
        ],
    ],

    // Lead Finder: Google Maps scraping through Apify (https://apify.com)
    // Email checks before drafts (https://millionverifier.com). Off while blank.
    'millionverifier' => [
        'key' => env('MILLIONVERIFIER_KEY'),
    ],

    'apify' => [
        'token' => env('APIFY_TOKEN'),
        'maps_actor' => env('APIFY_MAPS_ACTOR', 'compass/crawler-google-places'),
        // Rough price per 1,000 businesses, used for the cost shown before a search.
        // Check Apify's page for the Google Maps scraper and update it in .env if it changes.
        'price_per_1000' => (float) env('APIFY_PRICE_PER_1000', 4),
    ],

];
