<?php

/**
 * Configuration i18n CleanUx — locales supportées data-driven.
 *
 * Ajouter une langue = ajouter une entrée ici + créer lang/<code>/ (au minimum
 * un fichier app.php + un fichier ui.php même vides, et lang/<code>.json).
 *
 * `priority` est utilisé pour l'ordre d'affichage dans le switcher.
 * `bcp47` est le code RFC standard (utilisé en HTML lang="..").
 * `currency` est la devise par défaut associée (peut être override par country).
 */

return [
    'default' => env('APP_LOCALE', 'fr'),
    'fallback' => env('APP_FALLBACK_LOCALE', 'en'),

    'locales' => [
        'fr' => [
            'name' => 'Français',
            'native_name' => 'Français',
            'bcp47' => 'fr-BE',
            'flag' => '🇫🇷',
            'currency' => 'EUR',
            'priority' => 10,
            'enabled' => true,
        ],
        'nl' => [
            'name' => 'Néerlandais',
            'native_name' => 'Nederlands',
            'bcp47' => 'nl-BE',
            'flag' => '🇳🇱',
            'currency' => 'EUR',
            'priority' => 20,
            'enabled' => true,
        ],
        'en' => [
            'name' => 'Anglais',
            'native_name' => 'English',
            'bcp47' => 'en-US',
            'flag' => '🇬🇧',
            'currency' => 'EUR',
            'priority' => 30,
            'enabled' => true,
        ],
        'es' => [
            'name' => 'Espagnol',
            'native_name' => 'Español',
            'bcp47' => 'es-ES',
            'flag' => '🇪🇸',
            'currency' => 'EUR',
            'priority' => 40,
            'enabled' => env('I18N_ES_ENABLED', true),
        ],
        'it' => [
            'name' => 'Italien',
            'native_name' => 'Italiano',
            'bcp47' => 'it-IT',
            'flag' => '🇮🇹',
            'currency' => 'EUR',
            'priority' => 50,
            'enabled' => env('I18N_IT_ENABLED', true),
        ],
        'de' => [
            'name' => 'Allemand',
            'native_name' => 'Deutsch',
            'bcp47' => 'de-DE',
            'flag' => '🇩🇪',
            'currency' => 'EUR',
            'priority' => 60,
            'enabled' => env('I18N_DE_ENABLED', true),
        ],
        'pt' => [
            'name' => 'Portugais',
            'native_name' => 'Português',
            'bcp47' => 'pt-PT',
            'flag' => '🇵🇹',
            'currency' => 'EUR',
            'priority' => 70,
            'enabled' => env('I18N_PT_ENABLED', false),
        ],
    ],

    'overrides' => [
        'enabled' => env('I18N_DB_OVERRIDES', true),
        'cache_ttl_seconds' => (int) env('I18N_CACHE_TTL', 300),
    ],

    'notifications' => [
        'respect_recipient_locale' => env('I18N_NOTIF_RECIPIENT_LOCALE', true),
    ],
];
