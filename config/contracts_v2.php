<?php

return [
    'enabled' => env('CONTRACTS_V2_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Version "terms" courante par défaut (fallback si template absent)
    |--------------------------------------------------------------------------
    */
    'default_terms_version' => env('CONTRACTS_DEFAULT_VERSION', '2026-05-v1'),

    /*
    |--------------------------------------------------------------------------
    | PDF generation
    |--------------------------------------------------------------------------
    | engine : dompdf | disabled (à étendre si on installe mPDF/wkhtmltopdf)
    */
    'pdf_engine' => env('CONTRACTS_PDF_ENGINE', 'dompdf'),
    'pdf_storage_disk' => env('CONTRACTS_PDF_DISK', 'local'),
    'pdf_path_prefix' => env('CONTRACTS_PDF_PATH', 'contracts'),

    /*
    |--------------------------------------------------------------------------
    | Signature
    |--------------------------------------------------------------------------
    */
    'signature_required' => env('CONTRACTS_SIGNATURE_REQUIRED', true),
    'signature_expiry_days' => (int) env('CONTRACTS_SIGNATURE_EXPIRY_DAYS', 0),  // 0 = jamais

    /*
    |--------------------------------------------------------------------------
    | Document expiry par défaut (en jours, après render). 0 = jamais.
    |--------------------------------------------------------------------------
    */
    'document_expiry_days' => (int) env('CONTRACTS_DOC_EXPIRY_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Placeholder pattern dans body_markdown
    |--------------------------------------------------------------------------
    | Default : {{name}}, {{email}}, {{date}}, {{version}}
    | Whitelist via 'allowed_variables' — unknown placeholder = laisser tel quel
    | (pas d'eval, anti-injection).
    */
    'placeholder_pattern' => '/\{\{\s*([a-zA-Z0-9_\.]+)\s*\}\}/',

    'allowed_variables' => [
        'name', 'email', 'date', 'version',
        'company', 'address', 'tax_id', 'iban_masked',
        'app_name', 'support_email',
        'terms_url', 'privacy_url',
    ],
];
