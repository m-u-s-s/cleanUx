<?php

return [
    'enabled' => env('KYB_V2_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Provider de vérification identité légale (registre commerce)
    |--------------------------------------------------------------------------
    | mock             : données canned (CI/dev/fallback)
    | insee            : api.insee.fr (SIRENE FR — gratuit avec inscription)
    | companies_house  : api.companieshouse.gov.uk (UK gratuit)
    | kvk              : api.kvk.nl (Pays-Bas)
    | vies             : ec.europa.eu (validation TVA intracom EU)
    */
    'identity_provider' => env('KYB_IDENTITY_PROVIDER', 'mock'),
    'vat_provider' => env('KYB_VAT_PROVIDER', 'mock'),
    'sanctions_provider' => env('KYB_SANCTIONS_PROVIDER', 'mock'),

    'providers' => [
        'insee' => [
            'api_key' => env('INSEE_API_KEY'),
            'endpoint' => 'https://api.insee.fr/entreprises/sirene/V3',
        ],
        'companies_house' => [
            'api_key' => env('COMPANIES_HOUSE_API_KEY'),
            'endpoint' => 'https://api.company-information.service.gov.uk',
        ],
        'kvk' => [
            'api_key' => env('KVK_API_KEY'),
            'endpoint' => 'https://api.kvk.nl/api/v1',
        ],
        'vies' => [
            'endpoint' => 'https://ec.europa.eu/taxation_customs/vies/rest-api',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Identifier types autorisés par pays (whitelist)
    |--------------------------------------------------------------------------
    */
    'identifier_types_by_country' => [
        'FR' => ['siret', 'siren'],
        'BE' => ['kbo'],
        'NL' => ['kvk'],
        'GB' => ['companies_house'],
        'DE' => ['handelsregister'],
        'IT' => ['rea'],
        'ES' => ['cif'],
        'LU' => ['rcsl'],
    ],
    'default_country_code' => env('KYB_DEFAULT_COUNTRY', 'BE'),

    /*
    |--------------------------------------------------------------------------
    | Cache TTL (jours) sur résultats de vérif provider (anti-spam quota)
    |--------------------------------------------------------------------------
    */
    'verification_cache_days' => (int) env('KYB_VERIFICATION_CACHE_DAYS', 90),
    'sanctions_cache_days' => (int) env('KYB_SANCTIONS_CACHE_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Auto-approve si toutes vérifs OK ET sanctions clear ET score < threshold
    |--------------------------------------------------------------------------
    */
    'auto_approve_enabled' => env('KYB_AUTO_APPROVE', false),
    'auto_approve_score_max' => (float) env('KYB_AUTO_APPROVE_SCORE_MAX', 30.0),

    /*
    |--------------------------------------------------------------------------
    | Risk scoring weights (somme = 100 idéalement)
    |--------------------------------------------------------------------------
    */
    'risk_weights' => [
        'sanctions_match' => 50.0,   // si match sanctions → +50 points (critique)
        'pep_owner' => 25.0,         // si beneficial owner PEP → +25
        'missing_kbis' => 10.0,      // pas de doc kbis fourni → +10
        'recent_incorporation' => 8.0,  // < 1 an → +8
        'unverified_vat' => 5.0,     // VAT intracom non validée → +5
        'high_risk_country' => 15.0, // pays liste OFAC/EU → +15
        'mismatched_address' => 5.0, // address provider ≠ saisie user → +5
    ],
    'high_risk_countries' => ['IR', 'KP', 'SY', 'CU', 'VE', 'RU', 'BY'],

    /*
    |--------------------------------------------------------------------------
    | Thresholds de risk_level (basé sur score 0-100)
    |--------------------------------------------------------------------------
    */
    'risk_thresholds' => [
        'low_max' => 20.0,        // 0-20 = low
        'medium_max' => 50.0,     // 21-50 = medium
        'high_max' => 75.0,       // 51-75 = high
        // > 75 = critical
    ],

    /*
    |--------------------------------------------------------------------------
    | Document types autorisés + retention
    |--------------------------------------------------------------------------
    */
    'document_types' => [
        'kbis', 'certificate_incorp', 'articles', 'bank_statement',
        'id_card_director', 'tax_certificate', 'proof_address', 'other',
    ],
    'document_max_size_kb' => (int) env('KYB_MAX_DOC_KB', 10240),  // 10 MB
    'allowed_mime_types' => [
        'application/pdf', 'image/jpeg', 'image/png', 'image/webp',
    ],
    'document_storage_disk' => env('KYB_DOC_DISK', 'local'),
    'document_path_prefix' => env('KYB_DOC_PATH', 'kyb_documents'),

    /*
    |--------------------------------------------------------------------------
    | Sanctions lists actives
    |--------------------------------------------------------------------------
    */
    'sanctions_lists' => ['eu', 'us_ofac', 'un', 'uk_hmt'],
];
