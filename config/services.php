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
        'premium_price_id' => env('STRIPE_PREMIUM_PRICE_ID'),
    ],

    'google' => [
        'maps_api_key' => env('GOOGLE_MAPS_API_KEY'),
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
        'key'        => env('ANTHROPIC_API_KEY'),
        'model'      => env('ANTHROPIC_MODEL', 'claude-sonnet-4-20250514'),
        'max_tokens' => (int) env('ANTHROPIC_MAX_TOKENS', 1024),
        'timeout'    => (int) env('ANTHROPIC_TIMEOUT', 30),
        'retries'    => (int) env('ANTHROPIC_RETRIES', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenAI (optionnel — failover assistant Phase 5.1)
    |--------------------------------------------------------------------------
    */
    'openai' => [
        'key'   => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
    ],

];
