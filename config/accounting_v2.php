<?php

return [
    'enabled' => env('ACCOUNTING_V2_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Plan comptable (PCG/PCMN simplifié — extensible)
    | Codes: PCG fr + PCMN be (compatibles pour la plupart des comptes)
    |--------------------------------------------------------------------------
    */
    'chart_of_accounts' => [
        // Classe 4 — Tiers
        '411' => ['name' => 'Clients', 'class' => 4],
        '411000' => ['name' => 'Clients généraux', 'class' => 4],
        '401' => ['name' => 'Fournisseurs', 'class' => 4],
        '401000' => ['name' => 'Fournisseurs prestataires', 'class' => 4],
        '4457' => ['name' => 'TVA collectée', 'class' => 4],
        '4456' => ['name' => 'TVA déductible', 'class' => 4],
        '4458' => ['name' => 'TVA à payer', 'class' => 4],
        '467' => ['name' => 'Stripe wallet provider (en attente payout)', 'class' => 4],

        // Classe 5 — Trésorerie
        '512' => ['name' => 'Banque', 'class' => 5],
        '512100' => ['name' => 'Banque Stripe', 'class' => 5],
        '530' => ['name' => 'Caisse', 'class' => 5],

        // Classe 6 — Charges
        '601' => ['name' => 'Achats de matériel/produits', 'class' => 6],
        '622' => ['name' => 'Commissions plateforme', 'class' => 6],
        '627' => ['name' => 'Services bancaires (frais Stripe)', 'class' => 6],
        '658' => ['name' => 'Charges diverses de gestion', 'class' => 6],
        '658100' => ['name' => 'Refunds clients', 'class' => 6],

        // Classe 7 — Produits
        '701' => ['name' => 'Ventes de services', 'class' => 7],
        '701100' => ['name' => 'Ventes bookings ponctuels', 'class' => 7],
        '701200' => ['name' => 'Ventes abonnements récurrents', 'class' => 7],
        '706' => ['name' => 'Prestations de services', 'class' => 7],
        '708' => ['name' => 'Produits annexes (frais d\'annulation)', 'class' => 7],
        '758' => ['name' => 'Produits divers de gestion', 'class' => 7],
    ],

    /*
    |--------------------------------------------------------------------------
    | Journaux
    |--------------------------------------------------------------------------
    */
    'journals' => [
        'VEN' => ['name' => 'Journal des ventes', 'type' => 'sales'],
        'ACH' => ['name' => 'Journal des achats', 'type' => 'purchases'],
        'BANK' => ['name' => 'Journal de banque', 'type' => 'bank'],
        'OD' => ['name' => 'Opérations diverses', 'type' => 'misc'],
        'INV' => ['name' => 'Journal d\'inventaire', 'type' => 'inventory'],
    ],
    'allowed_journals' => ['VEN', 'ACH', 'BANK', 'OD', 'INV'],

    /*
    |--------------------------------------------------------------------------
    | TVA par défaut selon pays (taux normaux)
    |--------------------------------------------------------------------------
    */
    'vat_rates' => [
        'FR' => 20.00,
        'BE' => 21.00,
        'NL' => 21.00,
        'LU' => 17.00,
        'DE' => 19.00,
        'IT' => 22.00,
        'ES' => 21.00,
    ],
    'default_country_code' => env('ACCOUNTING_DEFAULT_COUNTRY', 'BE'),

    /*
    |--------------------------------------------------------------------------
    | Auto-posting (postage automatique sur events business)
    |--------------------------------------------------------------------------
    | Si true, BookingObserver/StripeWebhook créent auto les entries.
    | Volontairement false par défaut — à activer manuellement en prod
    | une fois la configuration validée.
    */
    'auto_post_enabled' => env('ACCOUNTING_AUTO_POST', false),

    /*
    |--------------------------------------------------------------------------
    | Export storage + retention
    |--------------------------------------------------------------------------
    */
    'export_storage_disk' => env('ACCOUNTING_EXPORT_DISK', 'local'),
    'export_path_prefix' => env('ACCOUNTING_EXPORT_PATH', 'accounting_exports'),
    'export_retention_days' => (int) env('ACCOUNTING_EXPORT_RETENTION_DAYS', 365),

    /*
    |--------------------------------------------------------------------------
    | FEC (Fichier des Écritures Comptables — norme DGFiP fr)
    |--------------------------------------------------------------------------
    */
    'fec' => [
        'country_code' => env('ACCOUNTING_FEC_COUNTRY', 'FR'),
        'siren' => env('ACCOUNTING_FEC_SIREN', '000000000'),
        'delimiter' => '|',
    ],

    /*
    |--------------------------------------------------------------------------
    | Formats supportés
    |--------------------------------------------------------------------------
    */
    'allowed_formats' => ['csv', 'fec', 'sage', 'quickbooks_iif', 'xml'],

    /*
    |--------------------------------------------------------------------------
    | Sécurité : empêche de poster sur période fermée
    |--------------------------------------------------------------------------
    */
    'block_post_on_closed_period' => true,
];
