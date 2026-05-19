<?php

/**
 * Configuration KYC / Background checks.
 *
 * Provider-agnostic : on bind le provider via le service container
 * (`KycProviderInterface` → `MockProvider` par défaut, `OnfidoProvider`
 * si KYC_PROVIDER=onfido).
 */

return [
    'enabled' => env('KYC_ENABLED', true),

    'default_provider' => env('KYC_PROVIDER', 'mock'),

    'providers' => [
        'mock' => [
            'driver' => 'mock',
        ],
        'onfido' => [
            'driver' => 'onfido',
            'api_token' => env('ONFIDO_API_TOKEN'),
            'region' => env('ONFIDO_REGION', 'eu'),
            'webhook_token' => env('ONFIDO_WEBHOOK_TOKEN'),
        ],
        'veriff' => [
            'driver' => 'veriff',
            'api_key' => env('VERIFF_API_KEY'),
            'shared_secret' => env('VERIFF_SHARED_SECRET'),
        ],
        'sumsub' => [
            'driver' => 'sumsub',
            'app_token' => env('SUMSUB_APP_TOKEN'),
            'secret_key' => env('SUMSUB_SECRET_KEY'),
        ],
    ],

    /**
     * Documents requis par pays (ISO code). Le pays vient de l'organisation
     * du provider OU de son profil.
     */
    'requirements_by_country' => [
        'default' => ['identity_document', 'address_proof'],
        'BE' => ['identity_document', 'address_proof', 'tax_id'],
        'FR' => ['identity_document', 'address_proof', 'siren'],
        'DE' => ['identity_document', 'address_proof', 'tax_id'],
        'ES' => ['identity_document_nie', 'address_proof'],
        'IT' => ['identity_document', 'codice_fiscale'],
        'NL' => ['identity_document', 'address_proof', 'bsn'],
        'PT' => ['identity_document', 'nif'],
    ],

    /**
     * Checks demandés au provider tiers.
     */
    'standard_checks' => [
        'document',     // ID scan + extraction OCR + authenticité
        'facial_similarity', // selfie vs ID
        'watchlist_aml',    // PEP + sanctions (au moins en standard)
    ],

    'enhanced_checks' => [
        'criminal_record',
        'right_to_work',
    ],

    /**
     * Auto-mark provider as 'verified' quand tous les checks requis passent.
     */
    'auto_approve_on_clear' => env('KYC_AUTO_APPROVE', true),

    /**
     * Score minimum requis (0.0 - 1.0) pour auto-approve (si applicable au
     * provider externe, sinon ignoré).
     */
    'min_score_for_auto_approve' => (float) env('KYC_MIN_SCORE', 0.7),

    /**
     * Délai max d'attente avant escalade admin (heures).
     */
    'max_wait_hours_before_review' => (int) env('KYC_MAX_WAIT_HOURS', 24),
];
