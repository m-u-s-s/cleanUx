<?php

/**
 * Registre de couverture de la console d'administration mobile.
 *
 * L'administration web porte 99 routes. Ce fichier dit, pour chacune, comment le mobile la sert :
 *
 *   pending    — pas encore couverte. Le module reste VISIBLE dans l'annuaire, marqué « à venir ».
 *   descriptor — servie par le moteur de console générique : liste, détail, actions.
 *   report     — servie comme SYNTHÈSE : des tuiles chiffrées, pas une liste. Certains modules
 *                n'ont aucune table derrière eux, et les forcer en liste aurait montré un écran
 *                vide en prétendant les couvrir.
 *   screen     — servie par un écran natif sur-mesure.
 *
 * POURQUOI LES MODULES NON COUVERTS RESTENT VISIBLES. Masquer ce qui n'est pas prêt donnerait une
 * application qui a l'air complète et un chantier dont personne ne peut mesurer l'avancement.
 * L'annuaire affiche le compte exact de ce qui reste.
 *
 * Le test d'inventaire (tests/Feature/Admin/AdminConsoleInventoryTest.php) refuse toute divergence
 * entre ce registre et le routeur : une page ajoutée au web sans entrée ici fait échouer la suite,
 * une entrée dont la route disparaît aussi. C'est la seule garantie MÉCANIQUE que rien n'est
 * oublié — le reste serait un jugement, et un jugement ne tient pas sur 99 pages.
 *
 * La référence est donnée en CHEMIN et non en nom de classe : un nom de classe ferait importer le
 * namespace de tests dans un fichier de configuration chargé en production.
 *
 * Les sous-routes (détail, export, édition) sont rattachées au module qui les porte plutôt que
 * déclarées à part : elles ne sont pas des destinations d'annuaire, mais elles doivent être
 * comptées pour que l'inventaire soit complet.
 */
return [

    'groups' => [
        'pilotage' => 'Pilotage',
        'operations' => 'Opérations',
        'personnes' => 'Personnes et comptes',
        'catalogue' => 'Catalogue et prix',
        'argent' => 'Argent et conformité',
        'croissance' => 'Croissance',
        'plateforme' => 'Plateforme',
    ],

    'modules' => [

        // ── Pilotage ────────────────────────────────────────────────────────────────────────
        ['key' => 'dashboard', 'title' => 'Tableau de bord', 'group' => 'pilotage', 'icon' => 'speedometer-outline', 'coverage' => 'report', 'routes' => ['admin/dashboard']],
        ['key' => 'home', 'title' => 'Accueil admin', 'group' => 'pilotage', 'icon' => 'home-outline', 'coverage' => 'report', 'routes' => ['admin/home']],
        ['key' => 'business', 'title' => 'Tableau de bord business', 'group' => 'pilotage', 'icon' => 'trending-up-outline', 'coverage' => 'report', 'routes' => ['admin/business-dashboard']],
        ['key' => 'alerts', 'title' => 'Alertes', 'group' => 'pilotage', 'icon' => 'warning-outline', 'coverage' => 'report', 'routes' => ['admin/alerts']],
        ['key' => 'analytics', 'title' => 'Analytics', 'group' => 'pilotage', 'icon' => 'bar-chart-outline', 'coverage' => 'report', 'routes' => ['admin/analytics']],
        ['key' => 'analytics-v2', 'title' => 'Analytics V2', 'group' => 'pilotage', 'icon' => 'analytics-outline', 'coverage' => 'descriptor', 'routes' => ['admin/analytics-v2']],
        ['key' => 'cancellation-reasons', 'title' => 'Motifs d’annulation', 'group' => 'pilotage', 'icon' => 'close-circle-outline', 'coverage' => 'descriptor', 'routes' => ['admin/analytics/cancellations']],
        ['key' => 'readiness', 'title' => 'Préparation plateforme', 'group' => 'pilotage', 'icon' => 'checkmark-done-outline', 'coverage' => 'report', 'routes' => ['admin/platform-readiness']],
        ['key' => 'nps', 'title' => 'NPS', 'group' => 'pilotage', 'icon' => 'happy-outline', 'coverage' => 'descriptor', 'routes' => ['admin/nps']],
        ['key' => 'feedbacks', 'title' => 'Retours clients', 'group' => 'pilotage', 'icon' => 'chatbox-ellipses-outline', 'coverage' => 'descriptor', 'routes' => ['admin/feedbacks', 'admin/feedbacks/export', 'admin/feedbacks/export-csv']],
        /*
         * `admin/modules-directory` est le RÉPERTOIRE des modules du web — la page qui range les
         * 83 modules admin en cases par fonction. Il est rattaché ici plutôt que déclaré à part,
         * pour la raison donnée en tête de fichier : ce n'est pas une destination d'annuaire, mais
         * il doit être compté pour que l'inventaire soit complet. On n'ouvre pas l'annuaire depuis
         * l'annuaire — côté mobile, c'est l'onglet `AdminDirectory` qui tient ce rôle, et il n'est
         * pas un module.
         */
        ['key' => 'platform-modules', 'title' => 'Modules de la plateforme', 'group' => 'pilotage', 'icon' => 'layers-outline', 'coverage' => 'descriptor', 'routes' => ['admin/modules', 'admin/modules-directory']],
        ['key' => 'tools', 'title' => 'Outils et exports', 'group' => 'pilotage', 'icon' => 'construct-outline', 'coverage' => 'report', 'routes' => ['admin/outils', 'admin/export/csv', 'admin/export/pdf']],

        // ── Opérations ──────────────────────────────────────────────────────────────────────
        ['key' => 'missions', 'title' => 'Missions', 'group' => 'operations', 'icon' => 'briefcase-outline', 'coverage' => 'descriptor', 'routes' => ['admin/missions', 'admin/missions/{mission}', 'admin/missions/export/pdf']],
        ['key' => 'planning', 'title' => 'Planning', 'group' => 'operations', 'icon' => 'calendar-number-outline', 'coverage' => 'descriptor', 'routes' => ['admin/planning']],
        ['key' => 'calendar', 'title' => 'Calendrier', 'group' => 'operations', 'icon' => 'calendar-outline', 'coverage' => 'descriptor', 'routes' => ['admin/calendar', 'admin/calendar/settings']],
        /*
         * La fiche d'UN prestataire s'est ajoutée le 2026-08-15 (`ddf1520f`) sans entrer ici, et
         * `AdminConsoleInventoryTest` le disait depuis : « Pages admin absentes de
         * config/admin_console.php : admin/availability/{user} ». C'était le seul rouge de la suite.
         * Le registre décrit la couverture mobile — une page absente d'ici est une page dont
         * personne ne sait si l'application la sert.
         */
        ['key' => 'availability', 'title' => 'Disponibilités', 'group' => 'operations', 'icon' => 'time-outline', 'coverage' => 'descriptor', 'routes' => ['admin/availability', 'admin/availability/{user}']],
        ['key' => 'presence', 'title' => 'Présence', 'group' => 'operations', 'icon' => 'radio-outline', 'coverage' => 'descriptor', 'routes' => ['admin/presence']],
        ['key' => 'trip-tracking', 'title' => 'Suivi de trajet', 'group' => 'operations', 'icon' => 'navigate-outline', 'coverage' => 'descriptor', 'routes' => ['admin/trip-tracking']],
        ['key' => 'ia-dispatch', 'title' => 'Dispatch IA', 'group' => 'operations', 'icon' => 'sparkles-outline', 'coverage' => 'descriptor', 'routes' => ['admin/ia-dispatch']],
        /*
         * LE CENTRE DE RÉPARTITION — l'histoire d'une recherche, pas un compteur.
         *
         * `coverage => 'report'` : ce n'est PAS une liste de lignes d'une table. Ce qu'il montre —
         * la chaîne d'offres d'une recherche, avec refus, silences et distances — n'appartient à
         * aucune table à elle seule, et le moteur de console générique n'a pas de forme pour ça.
         */
        ['key' => 'dispatch-center', 'title' => 'Répartition', 'group' => 'operations', 'icon' => 'radio-outline', 'coverage' => 'report', 'routes' => ['admin/repartition']],
        ['key' => 'matching', 'title' => 'Matching', 'group' => 'operations', 'icon' => 'git-compare-outline', 'coverage' => 'descriptor', 'routes' => ['admin/matching']],
        ['key' => 'orchestration', 'title' => 'Orchestration terrain', 'group' => 'operations', 'icon' => 'options-outline', 'coverage' => 'descriptor', 'routes' => ['admin/orchestration']],
        ['key' => 'quality', 'title' => 'Qualité', 'group' => 'operations', 'icon' => 'ribbon-outline', 'coverage' => 'descriptor', 'routes' => ['admin/quality', 'admin/quality/export/incidents.csv', 'admin/quality/export/missions.csv']],
        ['key' => 'safety', 'title' => 'Sécurité terrain', 'group' => 'operations', 'icon' => 'shield-outline', 'coverage' => 'descriptor', 'routes' => ['admin/safety']],
        /*
         * LA SANTÉ DU MARCHÉ (E29, E30, E31).
         *
         * `coverage` à `report` : c'est une SYNTHÈSE, pas un CRUD. Les trois gestes de rattrapage
         * — relancer, contacter, offrir — vivent sur l'écran web, là où on les décide en regardant
         * le tableau. Les porter au mobile supposerait de répondre à « et alors on relance quoi »
         * sans le contexte qui précède.
         */
        ['key' => 'marketplace-health', 'title' => 'Santé du marché', 'group' => 'pilotage', 'icon' => 'pulse-outline', 'coverage' => 'report', 'routes' => ['admin/marche']],
        ['key' => 'realtime', 'title' => 'Temps réel', 'group' => 'operations', 'icon' => 'pulse-outline', 'coverage' => 'descriptor', 'routes' => ['admin/realtime']],
        ['key' => 'bookings', 'title' => 'Rendez-vous et récurrences', 'group' => 'operations', 'icon' => 'repeat-outline', 'coverage' => 'descriptor', 'routes' => ['admin/recurrence/{rendezVous}/serie', 'admin/rendez-vous/{rendezVous}', 'admin/rendez-vous-series/{series}/edit']],
        ['key' => 'b2b-operations', 'title' => 'Opérations B2B', 'group' => 'operations', 'icon' => 'business-outline', 'coverage' => 'descriptor', 'routes' => ['admin/b2b/operations'], 'resources' => ['b2b-contracts', 'b2b-work-orders']],
        ['key' => 'automation', 'title' => 'Automatisation', 'group' => 'operations', 'icon' => 'flash-outline', 'coverage' => 'report', 'routes' => ['admin/automation']],

        // ── Personnes et comptes ────────────────────────────────────────────────────────────
        ['key' => 'users', 'title' => 'Utilisateurs', 'group' => 'personnes', 'icon' => 'people-outline', 'coverage' => 'descriptor', 'routes' => ['admin/utilisateurs']],
        ['key' => 'companies', 'title' => 'Entreprises', 'group' => 'personnes', 'icon' => 'business-outline', 'coverage' => 'descriptor', 'routes' => ['admin/entreprises']],
        ['key' => 'sites', 'title' => 'Sites', 'group' => 'personnes', 'icon' => 'location-outline', 'coverage' => 'descriptor', 'routes' => ['admin/sites']],
        ['key' => 'teams', 'title' => 'Équipes et partenaires', 'group' => 'personnes', 'icon' => 'people-circle-outline', 'coverage' => 'descriptor', 'routes' => ['admin/teams-partners']],
        ['key' => 'provider-registrations', 'title' => 'Inscriptions prestataires', 'group' => 'personnes', 'icon' => 'person-add-outline', 'coverage' => 'descriptor', 'routes' => ['admin/inscriptions-prestataires']],
        ['key' => 'onboarding-providers', 'title' => 'Onboarding prestataires', 'group' => 'personnes', 'icon' => 'footsteps-outline', 'coverage' => 'descriptor', 'routes' => ['admin/onboarding-providers']],
        ['key' => 'onboarding-documents', 'title' => 'Documents d’onboarding', 'group' => 'personnes', 'icon' => 'document-attach-outline', 'coverage' => 'descriptor', 'routes' => ['admin/onboarding-documents', 'admin/onboarding-documents/{document}/file']],
        ['key' => 'onboarding-v2', 'title' => 'Onboarding V2', 'group' => 'personnes', 'icon' => 'trail-sign-outline', 'coverage' => 'descriptor', 'routes' => ['admin/onboarding-v2']],
        ['key' => 'enterprise-approvals', 'title' => 'Approbations entreprises', 'group' => 'personnes', 'icon' => 'checkmark-circle-outline', 'coverage' => 'descriptor', 'routes' => ['admin/approbations-entreprises']],
        ['key' => 'kyc', 'title' => 'Vérifications KYC', 'group' => 'personnes', 'icon' => 'finger-print-outline', 'coverage' => 'descriptor', 'routes' => ['admin/kyc']],
        ['key' => 'kyb', 'title' => 'Vérifications KYB', 'group' => 'personnes', 'icon' => 'briefcase-outline', 'coverage' => 'descriptor', 'routes' => ['admin/kyb-v2']],
        ['key' => 'badges', 'title' => 'Badges', 'group' => 'personnes', 'icon' => 'medal-outline', 'coverage' => 'descriptor', 'routes' => ['admin/badges']],
        ['key' => 'premium', 'title' => 'Clients premium', 'group' => 'personnes', 'icon' => 'star-outline', 'coverage' => 'descriptor', 'routes' => ['admin/premium/clients']],
        ['key' => 'stripe-connect', 'title' => 'Stripe Connect prestataires', 'group' => 'personnes', 'icon' => 'card-outline', 'coverage' => 'descriptor', 'routes' => ['admin/stripe-connect-providers']],

        // ── Catalogue et prix ───────────────────────────────────────────────────────────────
        // Le catalogue est une descente à trois niveaux : pays → zones → secteurs et métiers.
        // Les trois routes appartiennent au même module — c'est un seul parcours, pas trois écrans
        // que l'on atteindrait séparément.
        ['key' => 'catalog', 'title' => 'Catalogue de commande', 'group' => 'catalogue', 'icon' => 'sparkles-outline', 'coverage' => 'descriptor', 'routes' => ['admin/catalogue', 'admin/catalogue/{country}', 'admin/catalogue/{country}/{zone}', 'admin/parcours/{trade}']],
        ['key' => 'services', 'title' => 'Services', 'group' => 'catalogue', 'icon' => 'list-outline', 'coverage' => 'descriptor', 'routes' => ['admin/services']],
        ['key' => 'trades', 'title' => 'Métiers', 'group' => 'catalogue', 'icon' => 'hammer-outline', 'coverage' => 'descriptor', 'routes' => ['admin/trades', 'admin/trades/{trade}/pricing']],
        ['key' => 'pricing', 'title' => 'Tarification V2', 'group' => 'catalogue', 'icon' => 'pricetag-outline', 'coverage' => 'descriptor', 'routes' => ['admin/pricing-v2']],
        ['key' => 'bundles', 'title' => 'Bundles', 'group' => 'catalogue', 'icon' => 'cube-outline', 'coverage' => 'descriptor', 'routes' => ['admin/bundles']],
        ['key' => 'zones', 'title' => 'Zones', 'group' => 'catalogue', 'icon' => 'map-outline', 'coverage' => 'descriptor', 'routes' => ['admin/zones']],
        ['key' => 'countries', 'title' => 'Pays', 'group' => 'catalogue', 'icon' => 'flag-outline', 'coverage' => 'descriptor', 'routes' => ['admin/countries']],
        ['key' => 'international', 'title' => 'International', 'group' => 'catalogue', 'icon' => 'globe-outline', 'coverage' => 'descriptor', 'routes' => ['admin/international']],

        // ── Argent et conformité ────────────────────────────────────────────────────────────
        ['key' => 'finance', 'title' => 'Finance', 'group' => 'argent', 'icon' => 'cash-outline', 'coverage' => 'report', 'routes' => ['admin/finance']],
        ['key' => 'accounting', 'title' => 'Comptabilité', 'group' => 'argent', 'icon' => 'calculator-outline', 'coverage' => 'descriptor', 'routes' => ['admin/accounting-v2']],
        ['key' => 'b2b-invoices', 'title' => 'Facturation mensuelle B2B', 'group' => 'argent', 'icon' => 'receipt-outline', 'coverage' => 'descriptor', 'routes' => ['admin/b2b/facturation-mensuelle']],
        ['key' => 'credits', 'title' => 'Crédits clients', 'group' => 'argent', 'icon' => 'wallet-outline', 'coverage' => 'descriptor', 'routes' => ['admin/credits-clients']],
        ['key' => 'tips', 'title' => 'Pourboires', 'group' => 'argent', 'icon' => 'gift-outline', 'coverage' => 'descriptor', 'routes' => ['admin/tips']],
        ['key' => 'fx', 'title' => 'Change et devises', 'group' => 'argent', 'icon' => 'swap-horizontal-outline', 'coverage' => 'descriptor', 'routes' => ['admin/fx']],
        ['key' => 'stripe', 'title' => 'Stripe', 'group' => 'argent', 'icon' => 'card-outline', 'coverage' => 'descriptor', 'routes' => ['admin/stripe']],
        ['key' => 'subscriptions', 'title' => 'Abonnements', 'group' => 'argent', 'icon' => 'refresh-circle-outline', 'coverage' => 'report', 'routes' => ['admin/subscriptions-v2']],
        ['key' => 'insurance', 'title' => 'Assurance', 'group' => 'argent', 'icon' => 'umbrella-outline', 'coverage' => 'descriptor', 'routes' => ['admin/insurance']],
        ['key' => 'cancellations', 'title' => 'Annulations', 'group' => 'argent', 'icon' => 'close-circle-outline', 'coverage' => 'descriptor', 'routes' => ['admin/cancellations-v2']],
        ['key' => 'disputes', 'title' => 'Litiges', 'group' => 'argent', 'icon' => 'alert-circle-outline', 'coverage' => 'descriptor', 'routes' => ['admin/disputes']],
        ['key' => 'risk', 'title' => 'Risque et fraude', 'group' => 'argent', 'icon' => 'shield-half-outline', 'coverage' => 'descriptor', 'routes' => ['admin/risk'], 'resources' => ['risk-holds']],
        ['key' => 'contracts', 'title' => 'Contrats', 'group' => 'argent', 'icon' => 'document-text-outline', 'coverage' => 'descriptor', 'routes' => ['admin/contracts-v2'], 'resources' => ['contract-signatures']],

        // ── Croissance ──────────────────────────────────────────────────────────────────────
        ['key' => 'marketing', 'title' => 'Marketing', 'group' => 'croissance', 'icon' => 'megaphone-outline', 'coverage' => 'descriptor', 'routes' => ['admin/marketing']],
        ['key' => 'promo-codes', 'title' => 'Codes promo', 'group' => 'croissance', 'icon' => 'ticket-outline', 'coverage' => 'descriptor', 'routes' => ['admin/promotions/codes']],
        ['key' => 'promo-campaigns', 'title' => 'Campagnes promo', 'group' => 'croissance', 'icon' => 'rocket-outline', 'coverage' => 'descriptor', 'routes' => ['admin/promotions/campagnes']],
        ['key' => 'referrals', 'title' => 'Parrainages', 'group' => 'croissance', 'icon' => 'share-social-outline', 'coverage' => 'descriptor', 'routes' => ['admin/promotions/parrainages']],
        ['key' => 'loyalty', 'title' => 'Fidélité', 'group' => 'croissance', 'icon' => 'diamond-outline', 'coverage' => 'descriptor', 'routes' => ['admin/loyalty', 'admin/loyalty/rewards']],
        ['key' => 'ratings', 'title' => 'Avis et modération', 'group' => 'croissance', 'icon' => 'star-half-outline', 'coverage' => 'descriptor', 'routes' => ['admin/avis']],
        ['key' => 'emails', 'title' => 'Emails produit', 'group' => 'croissance', 'icon' => 'mail-outline', 'coverage' => 'descriptor', 'routes' => ['admin/emails']],
        ['key' => 'sms', 'title' => 'SMS et WhatsApp', 'group' => 'croissance', 'icon' => 'chatbubble-outline', 'coverage' => 'descriptor', 'routes' => ['admin/sms']],
        ['key' => 'push', 'title' => 'Notifications push', 'group' => 'croissance', 'icon' => 'notifications-outline', 'coverage' => 'descriptor', 'routes' => ['admin/push']],
        ['key' => 'notification-preferences', 'title' => 'Préférences de notification', 'group' => 'croissance', 'icon' => 'toggle-outline', 'coverage' => 'descriptor', 'routes' => ['admin/notification-preferences']],

        // ── Plateforme ──────────────────────────────────────────────────────────────────────
        ['key' => 'audit', 'title' => 'Audit', 'group' => 'plateforme', 'icon' => 'shield-checkmark-outline', 'coverage' => 'descriptor', 'routes' => ['admin/audit', 'admin/audit/logs']],
        ['key' => 'gdpr', 'title' => 'RGPD', 'group' => 'plateforme', 'icon' => 'lock-closed-outline', 'coverage' => 'descriptor', 'routes' => ['admin/gdpr']],
        ['key' => 'feature-flags', 'title' => 'Feature flags', 'group' => 'plateforme', 'icon' => 'flag-outline', 'coverage' => 'descriptor', 'routes' => ['admin/feature-flags']],
        ['key' => 'api-tokens', 'title' => 'Jetons d’API', 'group' => 'plateforme', 'icon' => 'key-outline', 'coverage' => 'descriptor', 'routes' => ['admin/api-tokens-v2'], 'resources' => ['api-tokens-list']],
        ['key' => 'webhooks', 'title' => 'Webhooks sortants', 'group' => 'plateforme', 'icon' => 'git-network-outline', 'coverage' => 'descriptor', 'routes' => ['admin/webhooks-v2']],
        ['key' => 'geolocation', 'title' => 'Géolocalisation', 'group' => 'plateforme', 'icon' => 'locate-outline', 'coverage' => 'descriptor', 'routes' => ['admin/geolocation-v2']],
        ['key' => 'translations', 'title' => 'Traductions', 'group' => 'plateforme', 'icon' => 'language-outline', 'coverage' => 'descriptor', 'routes' => ['admin/translations']],
        ['key' => 'chat', 'title' => 'Chat et modération', 'group' => 'plateforme', 'icon' => 'chatbubbles-outline', 'coverage' => 'descriptor', 'routes' => ['admin/chat-v2']],
        ['key' => 'fleet', 'title' => 'Flotte et équipements', 'group' => 'plateforme', 'icon' => 'car-outline', 'coverage' => 'descriptor', 'routes' => ['admin/fleet-v2']],
    ],
];
