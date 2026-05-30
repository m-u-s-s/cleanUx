<?php

/**
 * Channel parity registry — the single source of truth for which surface
 * delivers each module, and (on mobile) whether it is a native screen or an
 * embedded web view.
 *
 * Per-module shape:
 *   key                 string  stable identifier
 *   title               string  display label
 *   icon                string  ionicons name used by the mobile nav
 *   path                string  internal web path (used for webview modules)
 *   web                 string  'native' (always, for now)
 *   mobile              string  'native' | 'webview'
 *   roles               array   roles that may see it ([] = everyone authenticated)
 *   responsive_verified bool    embed view checked on a narrow viewport
 *
 * Progressive native migration = flip a module's `mobile` from 'webview' to
 * 'native' here. No other code changes.
 */
return [
    'modules' => [
        // Note: company/entreprise clients are matched by the 'client' role here
        // (isClient() is true for company clients via isClientCompany()), so they
        // inherit all 'client' modules. Add explicit 'entreprise' role entries only
        // if/when company-specific modules diverge from individual-client modules.

        // Native today (hot operational paths already built in Expo)
        ['key' => 'booking', 'title' => 'Réserver', 'icon' => 'calendar-outline', 'path' => '/client/bookings/new', 'web' => 'native', 'mobile' => 'native', 'roles' => ['client'], 'responsive_verified' => true],
        ['key' => 'tracking', 'title' => 'Suivi', 'icon' => 'navigate-outline', 'path' => '/client/tracking', 'web' => 'native', 'mobile' => 'native', 'roles' => ['client'], 'responsive_verified' => true],
        ['key' => 'chat', 'title' => 'Messages', 'icon' => 'chatbubble-outline', 'path' => '/chat', 'web' => 'native', 'mobile' => 'native', 'roles' => [], 'responsive_verified' => true],
        ['key' => 'missions', 'title' => 'Missions', 'icon' => 'briefcase-outline', 'path' => '/employe/missions', 'web' => 'native', 'mobile' => 'native', 'roles' => ['provider'], 'responsive_verified' => true],
        ['key' => 'earnings', 'title' => 'Revenus', 'icon' => 'cash-outline', 'path' => '/employe/earnings', 'web' => 'native', 'mobile' => 'native', 'roles' => ['provider'], 'responsive_verified' => true],

        // Long-tail served via embedded web (migrate to native later)
        ['key' => 'accounting', 'title' => 'Comptabilité', 'icon' => 'document-text-outline', 'path' => '/admin/accounting', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => false],
        ['key' => 'audit', 'title' => 'Audit', 'icon' => 'shield-checkmark-outline', 'path' => '/admin/audit', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => false],
        ['key' => 'kyb', 'title' => 'KYB', 'icon' => 'business-outline', 'path' => '/admin/kyb', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => false],
        ['key' => 'invoices', 'title' => 'Factures', 'icon' => 'receipt-outline', 'path' => '/dashboard/client/finance', 'web' => 'native', 'mobile' => 'native', 'roles' => ['client'], 'responsive_verified' => true],
        ['key' => 'help', 'title' => 'Aide', 'icon' => 'help-circle-outline', 'path' => '/help', 'web' => 'native', 'mobile' => 'webview', 'roles' => [], 'responsive_verified' => true],
    ],
];
