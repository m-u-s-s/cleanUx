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

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'connect_webhook_secret' => env('STRIPE_CONNECT_WEBHOOK_SECRET'),
        'connect_country' => env('STRIPE_CONNECT_COUNTRY', 'BE'),
        'connect_refresh_url' => env('STRIPE_CONNECT_REFRESH_URL'),
        'connect_return_url' => env('STRIPE_CONNECT_RETURN_URL'),
        'premium_price_id' => env('STRIPE_PREMIUM_PRICE_ID'),
    ],

    'google' => [
        'maps_api_key' => env('GOOGLE_MAPS_API_KEY'),
        // Used by GeoDistanceService::drivingDistanceKm() (Distance Matrix API)
        'maps_key' => env('GOOGLE_MAPS_KEY', env('GOOGLE_MAPS_API_KEY')),
    ],

    'google_maps' => [
        'key' => env('GOOGLE_MAPS_API_KEY'),
    ],

    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'token' => env('TWILIO_TOKEN'),
        'from' => env('TWILIO_FROM'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Anthropic (Phase 5 — Chatbot LLM)
    |--------------------------------------------------------------------------
    | Configuration pour l'API Claude utilisée par AssistantWidget.
    | Récupère ta clé sur https://console.anthropic.com/settings/keys
    */
    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-20250514'),
        'max_tokens' => (int) env('ANTHROPIC_MAX_TOKENS', 1024),
        'timeout' => (int) env('ANTHROPIC_TIMEOUT', 30),
        'retries' => (int) env('ANTHROPIC_RETRIES', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenAI (optionnel — failover assistant Phase 5.1)
    |--------------------------------------------------------------------------
    */
    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
    ],

    'assistant' => [
        'rate_per_hour' => (int) env('ASSISTANT_RATE_PER_HOUR', 30),
        'rate_per_day' => (int) env('ASSISTANT_RATE_PER_DAY', 200),
        'cost_limit_usd_per_day' => (float) env('ASSISTANT_COST_LIMIT_USD_PER_DAY', 1.0),
    ],

    'webpush' => [
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'subject' => env('VAPID_SUBJECT', 'mailto:contact@cleanux.local'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Onfido (KYC — Phase KYC module)
    |--------------------------------------------------------------------------
    | REST API token from https://dashboard.onfido.com/
    | region: eu | us | ca
    */
    /*
    |--------------------------------------------------------------------------
    | Cloudflare Turnstile (captcha sur l'inscription)
    |--------------------------------------------------------------------------
    | Cette entrée manquait, alors que VerifyTurnstileCaptcha la lit en premier.
    | Le middleware retombait donc sur env(), qui renvoie null dès que la config est
    | mise en cache — ce qui est le cas standard en production (`config:cache`).
    | Résultat : la clé pouvait être présente dans .env et l'inscription renvoyait
    | quand même 503 captcha_misconfigured.
    */
    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
    ],

    'onfido' => [
        'api_token' => env('ONFIDO_API_TOKEN'),
        'base_url' => env('ONFIDO_BASE_URL', 'https://api.eu.onfido.com/v3.6'),
        'webhook_token' => env('ONFIDO_WEBHOOK_TOKEN'),
        'region' => env('ONFIDO_REGION', 'eu'),
    ],
];
