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

        /*
         * La réservation, c'est le MOTEUR DE COMMANDE — et lui seul.
         *
         * Cette entrée pointait vers l'assistant natif en cinq étapes figées, qui ne connaît ni
         * secteur, ni question propre au métier, ni instantané de réponse. Deux parcours écrivant
         * la même table par des chemins différents produiraient des devis explicables ou non selon
         * la porte empruntée — et le onzième critère d'acceptation serait vrai une fois sur deux.
         *
         * En vue embarquée le temps que le natif rattrape : basculer `mobile` sur 'native' suffira,
         * sans autre changement de code. La clé reste `booking` : c'est le même module, servi
         * autrement.
         */
        ['key' => 'booking', 'title' => 'Commander', 'icon' => 'sparkles-outline', 'path' => '/commander', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['client'], 'responsive_verified' => true],

        // Native today (hot operational paths already built in Expo)
        ['key' => 'tracking', 'title' => 'Suivi', 'icon' => 'navigate-outline', 'path' => '/dashboard/client/rendez-vous', 'web' => 'native', 'mobile' => 'native', 'roles' => ['client'], 'responsive_verified' => true],
        ['key' => 'chat', 'title' => 'Messages', 'icon' => 'chatbubble-outline', 'path' => '/dashboard/client/messagerie', 'web' => 'native', 'mobile' => 'native', 'roles' => [], 'responsive_verified' => true],
        ['key' => 'missions', 'title' => 'Missions', 'icon' => 'briefcase-outline', 'path' => '/dashboard/employe/missions', 'web' => 'native', 'mobile' => 'native', 'roles' => ['provider'], 'responsive_verified' => true],
        ['key' => 'earnings', 'title' => 'Revenus', 'icon' => 'cash-outline', 'path' => '/dashboard/employe/revenus', 'web' => 'native', 'mobile' => 'native', 'roles' => ['provider'], 'responsive_verified' => true],

        // Long-tail served via embedded web (migrate to native later)
        ['key' => 'accounting', 'title' => 'Comptabilité', 'icon' => 'document-text-outline', 'path' => '/admin/accounting-v2', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'audit', 'title' => 'Audit', 'icon' => 'shield-checkmark-outline', 'path' => '/admin/audit', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'kyb', 'title' => 'KYB', 'icon' => 'business-outline', 'path' => '/admin/kyb-v2', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'invoices', 'title' => 'Factures', 'icon' => 'receipt-outline', 'path' => '/dashboard/client/finance', 'web' => 'native', 'mobile' => 'native', 'roles' => ['client'], 'responsive_verified' => true],
        ['key' => 'help', 'title' => 'Aide', 'icon' => 'help-circle-outline', 'path' => '/aide', 'web' => 'native', 'mobile' => 'webview', 'roles' => [], 'responsive_verified' => true],

        /*
         * Le constructeur de parcours — l'écran dont dépend toute la promesse du module.
         *
         * Une quarantaine de modules d'administration étaient servis en vue embarquée, et pas
         * celui-ci : un administrateur sur téléphone ne pouvait atteindre ni le catalogue, ni le
         * questionnaire, ni les règles d'affichage.
         *
         * C'est le CATALOGUE qui est déclaré, pas `/admin/parcours/{trade}` : ce dernier porte un
         * paramètre et n'est pas un chemin concret. On y arrive depuis le catalogue, qui en est la
         * porte d'entrée naturelle.
         *
         * `responsive_verified` MESURÉ, pas déclaré : balayage Playwright à 390 px sur le catalogue
         * ET sur le constructeur qui s'ouvre depuis lui, cinq critères verts. Il a fallu corriger
         * des liens-boutons de 20 px de haut, hostiles au pouce, avant de pouvoir l'écrire.
         */
        ['key' => 'admin-order-engine', 'title' => 'Catalogue de commande', 'icon' => 'sparkles-outline', 'path' => '/admin/catalogue', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],

        // ── Generated by `php artisan parity:scaffold-registry` (every navigable
        //    GET area, one segment past its role prefix). All webview for v1;
        //    flip `mobile` to 'native' to migrate one later. Titles humanized,
        //    role-scoped. Dropped 3 dedup of existing native areas
        //    (employe/missions, employe/revenus, client/messagerie).
        ['key' => 'dashboard-employe-google-calendar', 'title' => 'Google Calendar', 'icon' => 'apps-outline', 'path' => '/dashboard/employe/google-calendar', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['provider'], 'responsive_verified' => true],
        ['key' => 'admin-dashboard', 'title' => 'Tableau de bord', 'icon' => 'apps-outline', 'path' => '/admin/dashboard', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-home', 'title' => 'Accueil admin', 'icon' => 'apps-outline', 'path' => '/admin/home', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-missions', 'title' => 'Missions', 'icon' => 'apps-outline', 'path' => '/admin/missions', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-utilisateurs', 'title' => 'Utilisateurs', 'icon' => 'apps-outline', 'path' => '/admin/utilisateurs', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-users', 'title' => 'Users', 'icon' => 'apps-outline', 'path' => '/admin/utilisateurs', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => false],
        ['key' => 'admin-alerts', 'title' => 'Alertes', 'icon' => 'apps-outline', 'path' => '/admin/alerts', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-analytics', 'title' => 'Analytics', 'icon' => 'apps-outline', 'path' => '/admin/analytics', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-credits-clients', 'title' => 'Crédits clients', 'icon' => 'apps-outline', 'path' => '/admin/credits-clients', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-avis', 'title' => 'Avis', 'icon' => 'apps-outline', 'path' => '/admin/avis', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-stripe', 'title' => 'Stripe', 'icon' => 'apps-outline', 'path' => '/admin/stripe', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-translations', 'title' => 'Traductions', 'icon' => 'apps-outline', 'path' => '/admin/translations', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-disputes', 'title' => 'Litiges', 'icon' => 'apps-outline', 'path' => '/admin/disputes', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-kyc', 'title' => 'KYC', 'icon' => 'apps-outline', 'path' => '/admin/kyc', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-gdpr', 'title' => 'RGPD', 'icon' => 'apps-outline', 'path' => '/admin/gdpr', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-loyalty', 'title' => 'Fidélité', 'icon' => 'apps-outline', 'path' => '/admin/loyalty', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-tips', 'title' => 'Pourboires', 'icon' => 'apps-outline', 'path' => '/admin/tips', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-trip-tracking', 'title' => 'Suivi trajet', 'icon' => 'apps-outline', 'path' => '/admin/trip-tracking', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-presence', 'title' => 'Présence', 'icon' => 'apps-outline', 'path' => '/admin/presence', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-nps', 'title' => 'NPS', 'icon' => 'apps-outline', 'path' => '/admin/nps', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-safety', 'title' => 'Sécurité', 'icon' => 'apps-outline', 'path' => '/admin/safety', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-badges', 'title' => 'Badges', 'icon' => 'apps-outline', 'path' => '/admin/badges', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-bundles', 'title' => 'Bundles', 'icon' => 'apps-outline', 'path' => '/admin/bundles', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-sms', 'title' => 'SMS', 'icon' => 'apps-outline', 'path' => '/admin/sms', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-push', 'title' => 'Push', 'icon' => 'apps-outline', 'path' => '/admin/push', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-realtime', 'title' => 'Temps réel', 'icon' => 'apps-outline', 'path' => '/admin/realtime', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-analytics-v2', 'title' => 'Analytics V2', 'icon' => 'apps-outline', 'path' => '/admin/analytics-v2', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-availability', 'title' => 'Disponibilités', 'icon' => 'apps-outline', 'path' => '/admin/availability', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-risk', 'title' => 'Risque', 'icon' => 'apps-outline', 'path' => '/admin/risk', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-marketing', 'title' => 'Marketing', 'icon' => 'apps-outline', 'path' => '/admin/marketing', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-insurance', 'title' => 'Assurance', 'icon' => 'apps-outline', 'path' => '/admin/insurance', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-fx', 'title' => 'FX', 'icon' => 'apps-outline', 'path' => '/admin/fx', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-notification-preferences', 'title' => 'Préférences de notification', 'icon' => 'apps-outline', 'path' => '/admin/notification-preferences', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-quality', 'title' => 'Qualité', 'icon' => 'apps-outline', 'path' => '/admin/quality', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-cancellations-v2', 'title' => 'Annulations V2', 'icon' => 'apps-outline', 'path' => '/admin/cancellations-v2', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-onboarding-v2', 'title' => 'Onboarding V2', 'icon' => 'apps-outline', 'path' => '/admin/onboarding-v2', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-pricing-v2', 'title' => 'Tarification V2', 'icon' => 'apps-outline', 'path' => '/admin/pricing-v2', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-contracts-v2', 'title' => 'Contrats V2', 'icon' => 'apps-outline', 'path' => '/admin/contracts-v2', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-webhooks-v2', 'title' => 'Webhooks V2', 'icon' => 'apps-outline', 'path' => '/admin/webhooks-v2', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-geolocation-v2', 'title' => 'Géolocalisation V2', 'icon' => 'apps-outline', 'path' => '/admin/geolocation-v2', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-api-tokens-v2', 'title' => 'API Tokens V2', 'icon' => 'apps-outline', 'path' => '/admin/api-tokens-v2', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-chat-v2', 'title' => 'Chat V2', 'icon' => 'apps-outline', 'path' => '/admin/chat-v2', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-subscriptions-v2', 'title' => 'Abonnements V2', 'icon' => 'apps-outline', 'path' => '/admin/subscriptions-v2', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-fleet-v2', 'title' => 'Flotte V2', 'icon' => 'apps-outline', 'path' => '/admin/fleet-v2', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-feature-flags', 'title' => 'Feature Flags', 'icon' => 'apps-outline', 'path' => '/admin/feature-flags', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-stripe-connect-providers', 'title' => 'Stripe Connect prestataires', 'icon' => 'apps-outline', 'path' => '/admin/stripe-connect-providers', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-business-dashboard', 'title' => 'Tableau de bord business', 'icon' => 'apps-outline', 'path' => '/admin/business-dashboard', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-platform-readiness', 'title' => 'Préparation plateforme', 'icon' => 'apps-outline', 'path' => '/admin/platform-readiness', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-approbations-entreprises', 'title' => 'Approbations entreprises', 'icon' => 'apps-outline', 'path' => '/admin/approbations-entreprises', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-sites', 'title' => 'Sites', 'icon' => 'apps-outline', 'path' => '/admin/sites', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-trades', 'title' => 'Métiers', 'icon' => 'apps-outline', 'path' => '/admin/trades', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-onboarding-providers', 'title' => 'Onboarding prestataires', 'icon' => 'apps-outline', 'path' => '/admin/onboarding-providers', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-onboarding-documents', 'title' => 'Documents onboarding', 'icon' => 'apps-outline', 'path' => '/admin/onboarding-documents', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'dashboard-client', 'title' => 'Espace client', 'icon' => 'apps-outline', 'path' => '/dashboard/client', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['client'], 'responsive_verified' => true],
        ['key' => 'dashboard-client-rendez-vous', 'title' => 'Rendez-vous', 'icon' => 'apps-outline', 'path' => '/dashboard/client/rendez-vous', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['client'], 'responsive_verified' => true],
        ['key' => 'dashboard-client-prestataires', 'title' => 'Prestataires', 'icon' => 'apps-outline', 'path' => '/dashboard/client/prestataires', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['client'], 'responsive_verified' => true],
        ['key' => 'dashboard-client-donnees', 'title' => 'Données', 'icon' => 'apps-outline', 'path' => '/dashboard/client/donnees', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['client'], 'responsive_verified' => true],
        ['key' => 'dashboard-client-fidelite', 'title' => 'Fidélité', 'icon' => 'apps-outline', 'path' => '/dashboard/client/fidelite', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['client'], 'responsive_verified' => true],
        ['key' => 'dashboard-client-parrainage', 'title' => 'Parrainage', 'icon' => 'apps-outline', 'path' => '/dashboard/client/parrainage', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['client'], 'responsive_verified' => true],

        // LOCATION ENTRE MEMBRES — un membre loue et prete, quel que soit son role : les trois
        // ecrans s'ouvrent donc aux quatre profils. Rendus sans chrome sous `?embed=1`, verifie
        // a 390px. Sans ces lignes, le module etait invisible dans les applications.
        ['key' => 'peer-catalogue', 'title' => 'Louer un véhicule', 'icon' => 'key-outline', 'path' => '/louer', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['client', 'entreprise', 'provider', 'provider_company'], 'responsive_verified' => true],
        ['key' => 'peer-mes-locations', 'title' => 'Mes locations', 'icon' => 'car-outline', 'path' => '/dashboard/location-entre-membres/mes-locations', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['client', 'entreprise', 'provider', 'provider_company'], 'responsive_verified' => true],
        ['key' => 'peer-mes-vehicules', 'title' => 'Mes véhicules en location', 'icon' => 'construct-outline', 'path' => '/dashboard/location-entre-membres/mes-vehicules', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['client', 'entreprise', 'provider', 'provider_company'], 'responsive_verified' => true],
        ['key' => 'dashboard-client-portefeuille', 'title' => 'Portefeuille', 'icon' => 'apps-outline', 'path' => '/dashboard/client/portefeuille', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['client'], 'responsive_verified' => true],
        ['key' => 'dashboard-client-litiges', 'title' => 'Litiges', 'icon' => 'apps-outline', 'path' => '/dashboard/client/litiges', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['client'], 'responsive_verified' => true],
        ['key' => 'dashboard-client-historique', 'title' => 'Historique', 'icon' => 'apps-outline', 'path' => '/dashboard/client/historique', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['client'], 'responsive_verified' => true],
        ['key' => 'dashboard-client-abonnements-v2', 'title' => 'Abonnements V2', 'icon' => 'apps-outline', 'path' => '/dashboard/client/abonnements-v2', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['client'], 'responsive_verified' => true],
        ['key' => 'dashboard-client-api-tokens', 'title' => 'API Tokens', 'icon' => 'apps-outline', 'path' => '/dashboard/client/api-tokens', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['client'], 'responsive_verified' => true],
        ['key' => 'dashboard-client-kyb-onboarding', 'title' => 'Onboarding KYB', 'icon' => 'apps-outline', 'path' => '/dashboard/client/kyb-onboarding', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['client'], 'responsive_verified' => true],
        ['key' => 'dashboard-client-contrats', 'title' => 'Contrats', 'icon' => 'apps-outline', 'path' => '/dashboard/client/contrats', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['client'], 'responsive_verified' => true],
        ['key' => 'dashboard-client-nps', 'title' => 'NPS', 'icon' => 'apps-outline', 'path' => '/dashboard/client/nps', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['client'], 'responsive_verified' => true],
        ['key' => 'dashboard-client-chantiers-groupes', 'title' => 'Chantiers groupés', 'icon' => 'apps-outline', 'path' => '/dashboard/client/chantiers-groupes', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['client'], 'responsive_verified' => true],
        ['key' => 'dashboard-client-devis-ia', 'title' => 'Devis IA', 'icon' => 'apps-outline', 'path' => '/dashboard/client/devis-ia', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['client'], 'responsive_verified' => true],
        ['key' => 'dashboard-client-analytics', 'title' => 'Analytics', 'icon' => 'apps-outline', 'path' => '/dashboard/entreprise-client/moi/analytics', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['entreprise'], 'responsive_verified' => true],
        ['key' => 'dashboard-employe', 'title' => 'Espace prestataire', 'icon' => 'apps-outline', 'path' => '/dashboard/employe', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['provider'], 'responsive_verified' => true],
        ['key' => 'dashboard-employe-avis', 'title' => 'Avis', 'icon' => 'apps-outline', 'path' => '/dashboard/employe/avis', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['provider'], 'responsive_verified' => true],
        ['key' => 'dashboard-employe-portefeuille', 'title' => 'Portefeuille', 'icon' => 'apps-outline', 'path' => '/dashboard/employe/portefeuille', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['provider'], 'responsive_verified' => true],
        ['key' => 'dashboard-employe-litiges', 'title' => 'Litiges', 'icon' => 'apps-outline', 'path' => '/dashboard/employe/litiges', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['provider'], 'responsive_verified' => true],
        ['key' => 'dashboard-employe-verification', 'title' => 'Vérification', 'icon' => 'apps-outline', 'path' => '/dashboard/employe/verification', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['provider'], 'responsive_verified' => true],
        ['key' => 'dashboard-employe-badges', 'title' => 'Badges', 'icon' => 'apps-outline', 'path' => '/dashboard/employe/badges', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['provider'], 'responsive_verified' => true],
        ['key' => 'dashboard-employe-disponibilites', 'title' => 'Disponibilités', 'icon' => 'apps-outline', 'path' => '/dashboard/employe/disponibilites', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['provider'], 'responsive_verified' => true],
        ['key' => 'dashboard-employe-planning', 'title' => 'Planning', 'icon' => 'apps-outline', 'path' => '/dashboard/employe/planning', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['provider'], 'responsive_verified' => true],
        ['key' => 'dashboard-employe-historique', 'title' => 'Historique', 'icon' => 'apps-outline', 'path' => '/dashboard/employe/historique', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['provider'], 'responsive_verified' => true],
        ['key' => 'dashboard-employe-incident', 'title' => 'Incident', 'icon' => 'apps-outline', 'path' => '/dashboard/employe/incident', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['provider'], 'responsive_verified' => true],
        ['key' => 'dashboard-employe-equipe', 'title' => 'Équipe', 'icon' => 'apps-outline', 'path' => '/dashboard/employe/equipe', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['provider'], 'responsive_verified' => true],
        ['key' => 'dashboard-employe-coordination', 'title' => 'Coordination', 'icon' => 'apps-outline', 'path' => '/dashboard/employe/coordination', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['provider'], 'responsive_verified' => true],
        ['key' => 'dashboard-employe-chef-equipe', 'title' => 'Chef d\'équipe', 'icon' => 'apps-outline', 'path' => '/dashboard/employe/chef-equipe', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['provider'], 'responsive_verified' => true],
        ['key' => 'dashboard-employe-feedbacks', 'title' => 'Feedbacks', 'icon' => 'apps-outline', 'path' => '/dashboard/employe/feedbacks', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['provider'], 'responsive_verified' => true],
        ['key' => 'dashboard-employe-validation-multiple-rdv', 'title' => 'Validation multiple RDV', 'icon' => 'apps-outline', 'path' => '/dashboard/employe/validation-multiple-rdv', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['provider'], 'responsive_verified' => true],
        ['key' => 'admin-planning', 'title' => 'Planning', 'icon' => 'apps-outline', 'path' => '/admin/planning', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-calendar', 'title' => 'Calendrier', 'icon' => 'apps-outline', 'path' => '/admin/calendar', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-feedbacks', 'title' => 'Feedbacks', 'icon' => 'apps-outline', 'path' => '/admin/feedbacks', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-finance', 'title' => 'Finance', 'icon' => 'apps-outline', 'path' => '/admin/finance', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-outils', 'title' => 'Outils', 'icon' => 'apps-outline', 'path' => '/admin/outils', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-services', 'title' => 'Services', 'icon' => 'apps-outline', 'path' => '/admin/services', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-teams-partners', 'title' => 'Équipes & partenaires', 'icon' => 'apps-outline', 'path' => '/admin/teams-partners', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-international', 'title' => 'International', 'icon' => 'apps-outline', 'path' => '/admin/international', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-orchestration', 'title' => 'Orchestration', 'icon' => 'apps-outline', 'path' => '/admin/orchestration', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-automation', 'title' => 'Automation', 'icon' => 'apps-outline', 'path' => '/admin/automation', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-modules', 'title' => 'Modules', 'icon' => 'apps-outline', 'path' => '/admin/modules', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-countries', 'title' => 'Pays', 'icon' => 'apps-outline', 'path' => '/admin/countries', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-emails', 'title' => 'Emails', 'icon' => 'apps-outline', 'path' => '/admin/emails', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
        ['key' => 'admin-premium-clients', 'title' => 'Clients premium', 'icon' => 'apps-outline', 'path' => '/admin/premium/clients', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],

        // ── B2B / entreprise (company dashboards)
        ['key' => 'dashboard-entreprise-client', 'title' => 'Espace entreprise', 'icon' => 'apps-outline', 'path' => '/dashboard/entreprise-client', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['entreprise'], 'responsive_verified' => true],
        ['key' => 'dashboard-entreprise-client-locaux', 'title' => 'Locaux', 'icon' => 'apps-outline', 'path' => '/dashboard/entreprise-client/locaux', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['entreprise'], 'responsive_verified' => true],
        ['key' => 'dashboard-entreprise-client-reservations', 'title' => 'Réservations', 'icon' => 'apps-outline', 'path' => '/dashboard/entreprise-client/reservations', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['entreprise'], 'responsive_verified' => true],
        ['key' => 'dashboard-entreprise-client-membres', 'title' => 'Membres', 'icon' => 'apps-outline', 'path' => '/dashboard/entreprise-client/membres', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['entreprise'], 'responsive_verified' => true],
        ['key' => 'dashboard-entreprise-client-facturation', 'title' => 'Facturation', 'icon' => 'apps-outline', 'path' => '/dashboard/entreprise-client/facturation', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['entreprise'], 'responsive_verified' => true],
        ['key' => 'dashboard-entreprise-client-contrats', 'title' => 'Contrats', 'icon' => 'document-attach-outline', 'path' => '/dashboard/entreprise-client/contrats', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['entreprise'], 'responsive_verified' => true],
        ['key' => 'dashboard-entreprise-prestataire', 'title' => 'Espace prestataire entreprise', 'icon' => 'apps-outline', 'path' => '/dashboard/entreprise-prestataire', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['provider_company'], 'responsive_verified' => true],
        ['key' => 'dashboard-entreprise-prestataire-canaux', 'title' => 'Canaux', 'icon' => 'apps-outline', 'path' => '/dashboard/entreprise-prestataire/canaux', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['provider_company'], 'responsive_verified' => true],
        ['key' => 'dashboard-entreprise-prestataire-taches', 'title' => 'Tâches', 'icon' => 'apps-outline', 'path' => '/dashboard/entreprise-prestataire/taches', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['provider_company'], 'responsive_verified' => true],
        ['key' => 'dashboard-entreprise-prestataire-dispatch', 'title' => 'Dispatch', 'icon' => 'apps-outline', 'path' => '/dashboard/entreprise-prestataire/dispatch', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['provider_company'], 'responsive_verified' => true],
        ['key' => 'dashboard-entreprise-prestataire-equipe', 'title' => 'Équipe', 'icon' => 'apps-outline', 'path' => '/dashboard/entreprise-prestataire/equipe', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['provider_company'], 'responsive_verified' => true],

        // ── B2B operations (admin oversight of company contracts + SLA breaches)
        ['key' => 'admin-b2b-operations', 'title' => 'Opérations B2B', 'icon' => 'business-outline', 'path' => '/admin/b2b/operations', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => true],
    ],
];
