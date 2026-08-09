<?php

/**
 * Registre des modules — source unique des points d'entrée du web.
 *
 * Il remplace quatre registres épars : les trois tableaux inline de `navigation-menu.blade.php`,
 * et les liens en dur des deux layouts société.
 *
 * `CatalogueDesModulesTest` part de la table de routes RÉELLE : toute page de tableau de bord
 * absente d'ici fait échouer la suite. C'est volontaire — ce dépôt a déjà produit des tests de
 * joignabilité qui asséraient une déclaration au lieu d'un chemin.
 *
 * Forme d'une entrée :
 *   key       string  identifiant stable
 *   label     string  libellé lu par l'utilisateur
 *   icon      string  emoji, traduit en Heroicon par `ModuleIcons`
 *   route     string  route nommée, cible de la case
 *   context   string  client | employe | admin | client-company | provider-company
 *   category  string  clé de `categories` ci-dessous
 *   primary   bool    true = reste dans la navbar allégée (5 par contexte au plus)
 */
return [
    'categories' => [
        'rendez-vous' => 'Rendez-vous & planning',
        'missions' => 'Missions & terrain',
        'documents' => 'Documents & contrats',
        'finance' => 'Finance & paiements',
        'comptes' => 'Comptes & organisations',
        'prestataires' => 'Prestataires & équipes',
        'communication' => 'Communication',
        'qualite' => 'Qualité & litiges',
        'conformite' => 'Conformité & sécurité',
        'croissance' => 'Croissance & fidélité',
        'donnees' => 'Données & analytics',
        'plateforme' => 'Plateforme & réglages',
    ],

    'catalogue' => [
        /*
         * ---- TRANSVERSAUX (contexte `*`) ----
         *
         * Ces pages ne vivent sous AUCUN tableau de bord : `user/profile`, `notifications`,
         * `aide`, `legal/*`. Le catalogue ayant été bâti à partir des registres de navigation, qui
         * ne couvraient que `admin/*` et `dashboard/*`, elles n'appartenaient à aucun rôle — les
         * cinq espaces étaient sans profil ni notifications dans leur page Modules.
         *
         * Elles sont déclarées UNE fois. Les recopier dans les cinq contextes serait cinq
         * occasions d'en oublier une.
         */
        ['key' => '*:profile.show', 'label' => 'Mon compte', 'icon' => '👤', 'route' => 'profile.show', 'context' => '*', 'category' => 'comptes', 'primary' => false],
        ['key' => '*:notifications.index', 'label' => 'Notifications', 'icon' => '🔔', 'route' => 'notifications.index', 'context' => '*', 'category' => 'communication', 'primary' => false],
        ['key' => '*:help.center', 'label' => 'Aide', 'icon' => '❓', 'route' => 'help.center', 'context' => '*', 'category' => 'plateforme', 'primary' => false],
        ['key' => '*:terms.show', 'label' => 'Conditions générales', 'icon' => '📜', 'route' => 'terms.show', 'context' => '*', 'category' => 'plateforme', 'primary' => false],
        ['key' => '*:policy.show', 'label' => 'Confidentialité', 'icon' => '🔐', 'route' => 'policy.show', 'context' => '*', 'category' => 'plateforme', 'primary' => false],
        ['key' => '*:legal.mentions', 'label' => 'Mentions légales', 'icon' => '📑', 'route' => 'legal.mentions', 'context' => '*', 'category' => 'plateforme', 'primary' => false],
        ['key' => '*:legal.cookies', 'label' => 'Cookies', 'icon' => '🍪', 'route' => 'legal.cookies', 'context' => '*', 'category' => 'plateforme', 'primary' => false],

        // ---- client ----
        ['key' => 'client:client.dashboard', 'label' => 'Accueil', 'icon' => '🏠', 'route' => 'client.dashboard', 'context' => 'client', 'category' => 'rendez-vous', 'primary' => true],
        ['key' => 'client:client.historique', 'label' => 'Historique', 'icon' => '🕘', 'route' => 'client.historique', 'context' => 'client', 'category' => 'rendez-vous', 'primary' => true],
        ['key' => 'client:client.rendezvous.create', 'label' => 'Nouveau RDV', 'icon' => '➕', 'route' => 'client.rendezvous.create', 'context' => 'client', 'category' => 'rendez-vous', 'primary' => true],
        ['key' => 'client:client.rendezvous.index', 'label' => 'Mes rendez-vous', 'icon' => '📅', 'route' => 'client.rendezvous.index', 'context' => 'client', 'category' => 'rendez-vous', 'primary' => true],
        ['key' => 'client:client.contracts', 'label' => 'Contrats', 'icon' => '📜', 'route' => 'client.contracts', 'context' => 'client', 'category' => 'documents', 'primary' => false],
        ['key' => 'client:client.finance', 'label' => 'Finance', 'icon' => '💳', 'route' => 'client.finance', 'context' => 'client', 'category' => 'finance', 'primary' => false],
        ['key' => 'client:client.payment.methods', 'label' => 'Cartes bancaires', 'icon' => '💳', 'route' => 'client.payment.methods', 'context' => 'client', 'category' => 'finance', 'primary' => false],
        ['key' => 'client:client.subscriptions', 'label' => 'Abonnements', 'icon' => '🔁', 'route' => 'client.subscriptions', 'context' => 'client', 'category' => 'finance', 'primary' => false],
        ['key' => 'client:client.subscriptions-v2', 'label' => 'Abonnements v2', 'icon' => '🔄', 'route' => 'client.subscriptions-v2', 'context' => 'client', 'category' => 'finance', 'primary' => false],
        ['key' => 'client:client.wallet', 'label' => 'Portefeuille', 'icon' => '👛', 'route' => 'client.wallet', 'context' => 'client', 'category' => 'finance', 'primary' => false],
        /*
         * LA PORTE VERS L'ESPACE SOCIÉTÉ, ET SA CONDITION.
         *
         * `visible_si` appelle la méthode du modèle User : sans elle, la case s'afficherait à tout
         * client, et un particulier cliquerait vers un 403 gardé par `org.type`. L'ancienne navbar
         * portait cette condition en dur — le registre la porte désormais.
         */
        ['key' => 'client:client-company.dashboard', 'label' => 'Espace entreprise', 'icon' => '🏢', 'route' => 'client-company.dashboard', 'context' => 'client', 'category' => 'comptes', 'primary' => false, 'visible_si' => 'belongsToClientCompany'],
        ['key' => 'client:client.profile', 'label' => 'Profil client', 'icon' => '👤', 'route' => 'client.profile', 'context' => 'client', 'category' => 'comptes', 'primary' => false],
        ['key' => 'client:client.profile.edit', 'label' => 'Éditer mon profil', 'icon' => '✏️', 'route' => 'client.profile.edit', 'context' => 'client', 'category' => 'comptes', 'primary' => false],
        ['key' => 'client:client.ai.quote.photo', 'label' => 'Devis IA depuis photo', 'icon' => '🤖', 'route' => 'client.ai.quote.photo', 'context' => 'client', 'category' => 'prestataires', 'primary' => false],
        ['key' => 'client:client.bundles.manage', 'label' => 'Chantiers groupés', 'icon' => '🏗️', 'route' => 'client.bundles.manage', 'context' => 'client', 'category' => 'prestataires', 'primary' => false],
        ['key' => 'client:client.providers.browse', 'label' => 'Trouver un prestataire', 'icon' => '🔍', 'route' => 'client.providers.browse', 'context' => 'client', 'category' => 'prestataires', 'primary' => true],
        ['key' => 'client:client.chat.inbox', 'label' => 'Messagerie', 'icon' => '💬', 'route' => 'client.chat.inbox', 'context' => 'client', 'category' => 'communication', 'primary' => false],
        ['key' => 'client:client.favorite-employes', 'label' => 'Prestataires favoris', 'icon' => '❤️', 'route' => 'client.favorite-employes', 'context' => 'client', 'category' => 'communication', 'primary' => false],
        ['key' => 'client:client.claims', 'label' => 'Litiges', 'icon' => '⚠️', 'route' => 'client.claims', 'context' => 'client', 'category' => 'qualite', 'primary' => false],
        ['key' => 'client:client.gdpr.data', 'label' => 'Mes données RGPD', 'icon' => '🔐', 'route' => 'client.gdpr.data', 'context' => 'client', 'category' => 'conformite', 'primary' => false],
        ['key' => 'client:client.kyb.onboarding', 'label' => 'Vérification entreprise (KYB)', 'icon' => '🏢', 'route' => 'client.kyb.onboarding', 'context' => 'client', 'category' => 'conformite', 'primary' => false],
        ['key' => 'client:client.loyalty', 'label' => 'Programme fidélité', 'icon' => '🎖️', 'route' => 'client.loyalty', 'context' => 'client', 'category' => 'croissance', 'primary' => false],
        ['key' => 'client:client.loyalty.rewards', 'label' => 'Récompenses', 'icon' => '🎁', 'route' => 'client.loyalty.rewards', 'context' => 'client', 'category' => 'croissance', 'primary' => false],
        ['key' => 'client:client.nps.survey', 'label' => 'Mon avis (NPS)', 'icon' => '⭐', 'route' => 'client.nps.survey', 'context' => 'client', 'category' => 'croissance', 'primary' => false],
        ['key' => 'client:client.referrals', 'label' => 'Parrainage', 'icon' => '🤝', 'route' => 'client.referrals', 'context' => 'client', 'category' => 'croissance', 'primary' => false],
        ['key' => 'client:client.api-tokens', 'label' => 'API tokens', 'icon' => '🔑', 'route' => 'client.api-tokens', 'context' => 'client', 'category' => 'plateforme', 'primary' => false],
        /*
         * Ces trois pages existaient sans figurer dans les tableaux de la navbar. Deux n'étaient
         * liées que depuis le bloc mobile de la vue, la troisième depuis l'en-tête du tableau de
         * bord client. Libellés tirés des composants : ClientCalendarFC,
         * RecurringTemplatesGallery, ClientAnalyticsDashboard.
         */
        ['key' => 'client:client.calendar.interactive', 'label' => 'Calendrier interactif', 'icon' => '📅', 'route' => 'client.calendar.interactive', 'context' => 'client', 'category' => 'rendez-vous', 'primary' => false],
        ['key' => 'client:client.recurring.templates', 'label' => 'Templates 1-clic', 'icon' => '⭐', 'route' => 'client.recurring.templates', 'context' => 'client', 'category' => 'rendez-vous', 'primary' => false],
        ['key' => 'client:client.analytics.dashboard', 'label' => 'Mes statistiques', 'icon' => '📊', 'route' => 'client.analytics.dashboard', 'context' => 'client', 'category' => 'donnees', 'primary' => false],

        /*
         * Propres aux CLIENTS, particuliers comme sociétés : commander une intervention, chercher
         * un prestataire, souscrire. Ces pages sont publiques d'accès mais ne figuraient dans
         * aucun contexte — un client ne trouvait donc pas « Commander » dans sa page Modules.
         */
        ['key' => 'client:booking.create', 'label' => 'Prendre rendez-vous', 'icon' => '➕', 'route' => 'booking.create', 'context' => 'client', 'category' => 'rendez-vous', 'primary' => false],
        ['key' => 'client:premium.offer', 'label' => 'Offre Premium', 'icon' => '⭐', 'route' => 'premium.offer', 'context' => 'client', 'category' => 'finance', 'primary' => false],

        // ---- employe ----
        /*
         * Propres aux PRESTATAIRES, indépendants comme salariés de société : l'état de leur
         * dossier de vérification, et leur présence terrain. Deux pages qui décident de ce qu'ils
         * peuvent faire, et qu'aucun menu ne citait.
         */
        ['key' => 'employe:provider.onboarding', 'label' => 'Mon dossier', 'icon' => '🚀', 'route' => 'provider.onboarding', 'context' => 'employe', 'category' => 'conformite', 'primary' => false],
        ['key' => 'employe:presence.me', 'label' => 'Ma présence', 'icon' => '🟢', 'route' => 'presence.me', 'context' => 'employe', 'category' => 'missions', 'primary' => false],
        ['key' => 'employe:employe.disponibilites', 'label' => 'Disponibilités', 'icon' => '🕒', 'route' => 'employe.disponibilites', 'context' => 'employe', 'category' => 'rendez-vous', 'primary' => true],
        ['key' => 'employe:employe.google.calendar', 'label' => 'Google Agenda', 'icon' => '🗓️', 'route' => 'employe.google.calendar', 'context' => 'employe', 'category' => 'rendez-vous', 'primary' => false],
        ['key' => 'employe:employe.planning', 'label' => 'Planning', 'icon' => '📅', 'route' => 'employe.planning', 'context' => 'employe', 'category' => 'rendez-vous', 'primary' => true],
        ['key' => 'employe:employe.bundle-quotes', 'label' => 'Devis chantiers', 'icon' => '🏗️', 'route' => 'employe.bundle-quotes', 'context' => 'employe', 'category' => 'missions', 'primary' => true],
        ['key' => 'employe:employe.dashboard', 'label' => 'Ma journée', 'icon' => '🏠', 'route' => 'employe.dashboard', 'context' => 'employe', 'category' => 'missions', 'primary' => true],
        ['key' => 'employe:employe.historique', 'label' => 'Historique', 'icon' => '🕘', 'route' => 'employe.historique', 'context' => 'employe', 'category' => 'missions', 'primary' => false],
        ['key' => 'employe:employe.missions', 'label' => 'Mes missions', 'icon' => '📋', 'route' => 'employe.missions', 'context' => 'employe', 'category' => 'missions', 'primary' => true],
        ['key' => 'employe:employe.earnings', 'label' => 'Tableau de bord revenus', 'icon' => '💰', 'route' => 'employe.earnings', 'context' => 'employe', 'category' => 'finance', 'primary' => false],
        ['key' => 'employe:employe.stripe-connect.start', 'label' => 'Stripe Connect', 'icon' => '💳', 'route' => 'employe.stripe-connect.start', 'context' => 'employe', 'category' => 'finance', 'primary' => false],
        ['key' => 'employe:employe.wallet', 'label' => 'Mon portefeuille', 'icon' => '👛', 'route' => 'employe.wallet', 'context' => 'employe', 'category' => 'finance', 'primary' => false],
        ['key' => 'employe:employe.coordination', 'label' => 'Coordination', 'icon' => '🧭', 'route' => 'employe.coordination', 'context' => 'employe', 'category' => 'prestataires', 'primary' => false],
        ['key' => 'employe:employe.team', 'label' => 'Équipe terrain', 'icon' => '👥', 'route' => 'employe.team', 'context' => 'employe', 'category' => 'prestataires', 'primary' => false],
        ['key' => 'employe:employe.teamlead.operations', 'label' => 'Chef d’équipe', 'icon' => '🧑‍💼', 'route' => 'employe.teamlead.operations', 'context' => 'employe', 'category' => 'prestataires', 'primary' => false],
        ['key' => 'employe:employe.disputes', 'label' => 'Mes litiges', 'icon' => '⚠️', 'route' => 'employe.disputes', 'context' => 'employe', 'category' => 'qualite', 'primary' => false],
        ['key' => 'employe:employe.incident', 'label' => 'Incident', 'icon' => '⚠️', 'route' => 'employe.incident', 'context' => 'employe', 'category' => 'qualite', 'primary' => false],
        ['key' => 'employe:employe.kyc', 'label' => 'KYC / Identité', 'icon' => '🛡️', 'route' => 'employe.kyc', 'context' => 'employe', 'category' => 'conformite', 'primary' => false],
        ['key' => 'employe:employe.validation.multiple', 'label' => 'Validation multiple', 'icon' => '✅', 'route' => 'employe.validation.multiple', 'context' => 'employe', 'category' => 'conformite', 'primary' => false],
        ['key' => 'employe:employe.badges', 'label' => 'Mes badges', 'icon' => '🏆', 'route' => 'employe.badges', 'context' => 'employe', 'category' => 'croissance', 'primary' => false],
        // « Ce que je fais, et où » : les deux tables que lit la requête candidate du dispatch.
        // Cocher une case change les offres reçues dans la seconde — c'est la page la plus
        // structurante de l'espace prestataire, elle est donc primaire.
        ['key' => 'employe:employe.trades-zones', 'label' => 'Métiers et zones', 'icon' => '🧭', 'route' => 'employe.trades-zones', 'context' => 'employe', 'category' => 'missions', 'primary' => false],
        ['key' => 'employe:employe.feedbacks', 'label' => 'Feedbacks reçus', 'icon' => '💬', 'route' => 'employe.feedbacks', 'context' => 'employe', 'category' => 'croissance', 'primary' => false],
        ['key' => 'employe:employe.ratings', 'label' => 'Mes avis', 'icon' => '⭐', 'route' => 'employe.ratings', 'context' => 'employe', 'category' => 'croissance', 'primary' => false],

        // ---- admin ----
        ['key' => 'admin:admin.availability.center', 'label' => 'Disponibilités', 'icon' => '📆', 'route' => 'admin.availability.center', 'context' => 'admin', 'category' => 'rendez-vous', 'primary' => false],
        ['key' => 'admin:admin.planning', 'label' => 'Planning', 'icon' => '📅', 'route' => 'admin.planning', 'context' => 'admin', 'category' => 'rendez-vous', 'primary' => true],
        ['key' => 'admin:admin.automation', 'label' => 'Automation', 'icon' => '⚙️', 'route' => 'admin.automation', 'context' => 'admin', 'category' => 'missions', 'primary' => false],
        ['key' => 'admin:admin.bundles.center', 'label' => 'Bundles chantiers', 'icon' => '🏗️', 'route' => 'admin.bundles.center', 'context' => 'admin', 'category' => 'missions', 'primary' => false],
        ['key' => 'admin:admin.fleet-v2.center', 'label' => 'Fleet véhicules', 'icon' => '🚐', 'route' => 'admin.fleet-v2.center', 'context' => 'admin', 'category' => 'missions', 'primary' => false],
        ['key' => 'admin:admin.missions', 'label' => 'Missions', 'icon' => '📋', 'route' => 'admin.missions', 'context' => 'admin', 'category' => 'missions', 'primary' => true],
        ['key' => 'admin:admin.orchestration', 'label' => 'Orchestration', 'icon' => '🧭', 'route' => 'admin.orchestration', 'context' => 'admin', 'category' => 'missions', 'primary' => false],
        ['key' => 'admin:admin.presence.center', 'label' => 'Presence providers', 'icon' => '🟢', 'route' => 'admin.presence.center', 'context' => 'admin', 'category' => 'missions', 'primary' => false],
        ['key' => 'admin:admin.trip-tracking.center', 'label' => 'Trip Tracking GPS', 'icon' => '📍', 'route' => 'admin.trip-tracking.center', 'context' => 'admin', 'category' => 'missions', 'primary' => false],
        ['key' => 'admin:admin.accounting-v2.center', 'label' => 'Comptabilité (FEC/Sage)', 'icon' => '📒', 'route' => 'admin.accounting-v2.center', 'context' => 'admin', 'category' => 'documents', 'primary' => false],
        ['key' => 'admin:admin.contracts-v2.center', 'label' => 'Contrats v2', 'icon' => '📜', 'route' => 'admin.contracts-v2.center', 'context' => 'admin', 'category' => 'documents', 'primary' => false],
        ['key' => 'admin:admin.b2b.monthly-invoices', 'label' => 'Factures B2B', 'icon' => '🧾', 'route' => 'admin.b2b.monthly-invoices', 'context' => 'admin', 'category' => 'finance', 'primary' => false],
        ['key' => 'admin:admin.business.dashboard', 'label' => 'Business', 'icon' => '🏢', 'route' => 'admin.business.dashboard', 'context' => 'admin', 'category' => 'finance', 'primary' => false],
        ['key' => 'admin:admin.cancellations-v2.center', 'label' => 'Annulations v2', 'icon' => '🚫', 'route' => 'admin.cancellations-v2.center', 'context' => 'admin', 'category' => 'finance', 'primary' => false],
        ['key' => 'admin:admin.customer.credits', 'label' => 'Crédits clients', 'icon' => '💰', 'route' => 'admin.customer.credits', 'context' => 'admin', 'category' => 'finance', 'primary' => false],
        ['key' => 'admin:admin.enterprise.approvals', 'label' => 'Approbations', 'icon' => '📑', 'route' => 'admin.enterprise.approvals', 'context' => 'admin', 'category' => 'finance', 'primary' => false],
        ['key' => 'admin:admin.finance', 'label' => 'Finance', 'icon' => '💶', 'route' => 'admin.finance', 'context' => 'admin', 'category' => 'finance', 'primary' => false],
        ['key' => 'admin:admin.pricing-v2.center', 'label' => 'Pricing v2 (DSL)', 'icon' => '💵', 'route' => 'admin.pricing-v2.center', 'context' => 'admin', 'category' => 'finance', 'primary' => false],
        ['key' => 'admin:admin.stripe-connect.providers', 'label' => 'Stripe prestataires', 'icon' => '💳', 'route' => 'admin.stripe-connect.providers', 'context' => 'admin', 'category' => 'finance', 'primary' => false],
        ['key' => 'admin:admin.stripe.hardening', 'label' => 'Stripe hardening', 'icon' => '💳', 'route' => 'admin.stripe.hardening', 'context' => 'admin', 'category' => 'finance', 'primary' => false],
        ['key' => 'admin:admin.subscriptions-v2.center', 'label' => 'Abonnements v2', 'icon' => '🔁', 'route' => 'admin.subscriptions-v2.center', 'context' => 'admin', 'category' => 'finance', 'primary' => false],
        ['key' => 'admin:admin.tips.center', 'label' => 'Pourboires (Tips)', 'icon' => '💰', 'route' => 'admin.tips.center', 'context' => 'admin', 'category' => 'finance', 'primary' => false],
        ['key' => 'admin:admin.b2b.operations', 'label' => 'B2B opérations', 'icon' => '🏢', 'route' => 'admin.b2b.operations', 'context' => 'admin', 'category' => 'comptes', 'primary' => false],
        ['key' => 'admin:admin.entreprises', 'label' => 'Entreprises', 'icon' => '🏢', 'route' => 'admin.entreprises', 'context' => 'admin', 'category' => 'comptes', 'primary' => false],
        ['key' => 'admin:admin.international', 'label' => 'International', 'icon' => '🌍', 'route' => 'admin.international', 'context' => 'admin', 'category' => 'comptes', 'primary' => false],
        ['key' => 'admin:admin.onboarding.documents', 'label' => 'Documents onboarding', 'icon' => '📎', 'route' => 'admin.onboarding.documents', 'context' => 'admin', 'category' => 'comptes', 'primary' => false],
        ['key' => 'admin:admin.onboarding.providers', 'label' => 'Onboarding prestataires', 'icon' => '🚀', 'route' => 'admin.onboarding.providers', 'context' => 'admin', 'category' => 'comptes', 'primary' => false],
        ['key' => 'admin:admin.premium.clients', 'label' => 'Clients premium', 'icon' => '⭐', 'route' => 'admin.premium.clients', 'context' => 'admin', 'category' => 'comptes', 'primary' => false],
        ['key' => 'admin:admin.services', 'label' => 'Services', 'icon' => '🧽', 'route' => 'admin.services', 'context' => 'admin', 'category' => 'comptes', 'primary' => false],
        ['key' => 'admin:admin.sites', 'label' => 'Sites', 'icon' => '📍', 'route' => 'admin.sites', 'context' => 'admin', 'category' => 'comptes', 'primary' => false],
        ['key' => 'admin:admin.teams.partners', 'label' => 'Équipes & partenaires', 'icon' => '👥', 'route' => 'admin.teams.partners', 'context' => 'admin', 'category' => 'comptes', 'primary' => false],
        ['key' => 'admin:admin.utilisateurs.manage', 'label' => 'Utilisateurs', 'icon' => '👤', 'route' => 'admin.utilisateurs.manage', 'context' => 'admin', 'category' => 'comptes', 'primary' => false],
        ['key' => 'admin:admin.zones', 'label' => 'Zones', 'icon' => '🗺️', 'route' => 'admin.zones', 'context' => 'admin', 'category' => 'comptes', 'primary' => false],
        ['key' => 'admin:admin.onboarding-v2.center', 'label' => 'Onboarding v2', 'icon' => '🚪', 'route' => 'admin.onboarding-v2.center', 'context' => 'admin', 'category' => 'prestataires', 'primary' => false],
        ['key' => 'admin:admin.providers.registrations', 'label' => 'Inscriptions prestataires', 'icon' => '🙋', 'route' => 'admin.providers.registrations', 'context' => 'admin', 'category' => 'prestataires', 'primary' => false],
        ['key' => 'admin:admin.chat-v2.center', 'label' => 'Chat & messagerie', 'icon' => '💬', 'route' => 'admin.chat-v2.center', 'context' => 'admin', 'category' => 'communication', 'primary' => false],
        ['key' => 'admin:admin.emails', 'label' => 'Emails produit', 'icon' => '✉️', 'route' => 'admin.emails', 'context' => 'admin', 'category' => 'communication', 'primary' => false],
        ['key' => 'admin:admin.notification-preferences.center', 'label' => 'Notifications prefs', 'icon' => '🔔', 'route' => 'admin.notification-preferences.center', 'context' => 'admin', 'category' => 'communication', 'primary' => false],
        ['key' => 'admin:admin.push.center', 'label' => 'Push notifications', 'icon' => '📱', 'route' => 'admin.push.center', 'context' => 'admin', 'category' => 'communication', 'primary' => false],
        ['key' => 'admin:admin.realtime.center', 'label' => 'Realtime / Reverb', 'icon' => '⚡', 'route' => 'admin.realtime.center', 'context' => 'admin', 'category' => 'communication', 'primary' => false],
        ['key' => 'admin:admin.sms.center', 'label' => 'SMS / WhatsApp', 'icon' => '💬', 'route' => 'admin.sms.center', 'context' => 'admin', 'category' => 'communication', 'primary' => false],
        ['key' => 'admin:admin.badges.center', 'label' => 'Provider badges', 'icon' => '🏅', 'route' => 'admin.badges.center', 'context' => 'admin', 'category' => 'qualite', 'primary' => false],
        ['key' => 'admin:admin.disputes.center', 'label' => 'Litiges & SAV', 'icon' => '🛟', 'route' => 'admin.disputes.center', 'context' => 'admin', 'category' => 'qualite', 'primary' => false],
        ['key' => 'admin:admin.feedbacks', 'label' => 'Feedbacks', 'icon' => '💬', 'route' => 'admin.feedbacks', 'context' => 'admin', 'category' => 'qualite', 'primary' => false],
        ['key' => 'admin:admin.quality.center', 'label' => 'Inspections qualité', 'icon' => '✔️', 'route' => 'admin.quality.center', 'context' => 'admin', 'category' => 'qualite', 'primary' => false],
        ['key' => 'admin:admin.ratings.moderation', 'label' => 'Modération avis', 'icon' => '⭐', 'route' => 'admin.ratings.moderation', 'context' => 'admin', 'category' => 'qualite', 'primary' => false],
        ['key' => 'admin:admin.safety.center', 'label' => 'Signalements & blocks', 'icon' => '🚫', 'route' => 'admin.safety.center', 'context' => 'admin', 'category' => 'qualite', 'primary' => false],
        ['key' => 'admin:admin.audit.center', 'label' => 'Audit v2', 'icon' => '🔍', 'route' => 'admin.audit.center', 'context' => 'admin', 'category' => 'conformite', 'primary' => false],
        ['key' => 'admin:admin.gdpr.center', 'label' => 'GDPR / RGPD', 'icon' => '🔐', 'route' => 'admin.gdpr.center', 'context' => 'admin', 'category' => 'conformite', 'primary' => false],
        ['key' => 'admin:admin.insurance.center', 'label' => 'Assurance', 'icon' => '🛡️', 'route' => 'admin.insurance.center', 'context' => 'admin', 'category' => 'conformite', 'primary' => false],
        ['key' => 'admin:admin.kyb-v2.center', 'label' => 'KYB entreprises', 'icon' => '🏢', 'route' => 'admin.kyb-v2.center', 'context' => 'admin', 'category' => 'conformite', 'primary' => false],
        ['key' => 'admin:admin.kyc.center', 'label' => 'KYC providers', 'icon' => '🪪', 'route' => 'admin.kyc.center', 'context' => 'admin', 'category' => 'conformite', 'primary' => false],
        ['key' => 'admin:admin.risk.center', 'label' => 'Risk scoring', 'icon' => '🚨', 'route' => 'admin.risk.center', 'context' => 'admin', 'category' => 'conformite', 'primary' => false],
        ['key' => 'admin:admin.loyalty.center', 'label' => 'Programme fidélité', 'icon' => '🎖️', 'route' => 'admin.loyalty.center', 'context' => 'admin', 'category' => 'croissance', 'primary' => false],
        ['key' => 'admin:admin.loyalty.rewards.center', 'label' => 'Récompenses loyalty', 'icon' => '🎁', 'route' => 'admin.loyalty.rewards.center', 'context' => 'admin', 'category' => 'croissance', 'primary' => false],
        ['key' => 'admin:admin.marketing.center', 'label' => 'Marketing automation', 'icon' => '📣', 'route' => 'admin.marketing.center', 'context' => 'admin', 'category' => 'croissance', 'primary' => false],
        ['key' => 'admin:admin.promotions.campaigns', 'label' => 'Campagnes promo', 'icon' => '📢', 'route' => 'admin.promotions.campaigns', 'context' => 'admin', 'category' => 'croissance', 'primary' => false],
        ['key' => 'admin:admin.promotions.codes', 'label' => 'Codes promo', 'icon' => '🎟️', 'route' => 'admin.promotions.codes', 'context' => 'admin', 'category' => 'croissance', 'primary' => false],
        ['key' => 'admin:admin.promotions.referrals', 'label' => 'Parrainage', 'icon' => '🤝', 'route' => 'admin.promotions.referrals', 'context' => 'admin', 'category' => 'croissance', 'primary' => false],
        ['key' => 'admin:admin.ai.dispatch', 'label' => 'IA Dispatch', 'icon' => '🤖', 'route' => 'admin.ai.dispatch', 'context' => 'admin', 'category' => 'donnees', 'primary' => false],
        /*
         * Le centre de répartition : l'histoire complète d'une recherche — qui a été sollicité,
         * quand, à quelle distance, refus ou silence. Sans lui, les quatre causes d'un échec de
         * dispatch se ressemblent et l'exploitation conclut « pas assez de prestataires ».
         *
         * `primary => false` : la barre d'accès rapide n'en porte que cinq, et les cinq places sont
         * déjà prises par des pages qu'un administrateur ouvre chaque jour. Une sixième les
         * remplacerait toutes par un menu déroulant.
         */
        ['key' => 'admin:admin.dispatch.center', 'label' => 'Répartition', 'icon' => '📡', 'route' => 'admin.dispatch.center', 'context' => 'admin', 'category' => 'donnees', 'primary' => false],
        ['key' => 'admin:admin.alerts', 'label' => 'Alertes', 'icon' => '🚨', 'route' => 'admin.alerts', 'context' => 'admin', 'category' => 'donnees', 'primary' => true],
        ['key' => 'admin:admin.analytics', 'label' => 'Analytics', 'icon' => '📈', 'route' => 'admin.analytics', 'context' => 'admin', 'category' => 'donnees', 'primary' => false],
        ['key' => 'admin:admin.analytics.cancellations', 'label' => 'Raisons annulation', 'icon' => '❌', 'route' => 'admin.analytics.cancellations', 'context' => 'admin', 'category' => 'donnees', 'primary' => false],
        ['key' => 'admin:admin.analytics.center', 'label' => 'Analytics v2', 'icon' => '📊', 'route' => 'admin.analytics.center', 'context' => 'admin', 'category' => 'donnees', 'primary' => false],
        ['key' => 'admin:admin.dashboard', 'label' => 'Dashboard', 'icon' => '📊', 'route' => 'admin.dashboard', 'context' => 'admin', 'category' => 'donnees', 'primary' => true],
        ['key' => 'admin:admin.home', 'label' => 'Vue d’ensemble', 'icon' => '🗂️', 'route' => 'admin.home', 'context' => 'admin', 'category' => 'donnees', 'primary' => true],
        ['key' => 'admin:admin.matching.insights', 'label' => 'Matching insights', 'icon' => '🎯', 'route' => 'admin.matching.insights', 'context' => 'admin', 'category' => 'donnees', 'primary' => false],
        ['key' => 'admin:admin.nps.center', 'label' => 'NPS scoring', 'icon' => '📊', 'route' => 'admin.nps.center', 'context' => 'admin', 'category' => 'donnees', 'primary' => false],
        ['key' => 'admin:admin.api-tokens-v2.center', 'label' => 'API Tokens v2', 'icon' => '🔑', 'route' => 'admin.api-tokens-v2.center', 'context' => 'admin', 'category' => 'plateforme', 'primary' => false],
        ['key' => 'admin:admin.countries', 'label' => 'Pays', 'icon' => '🗺️', 'route' => 'admin.countries', 'context' => 'admin', 'category' => 'plateforme', 'primary' => false],
        ['key' => 'admin:admin.feature-flags.manager', 'label' => 'Feature flags', 'icon' => '🚩', 'route' => 'admin.feature-flags.manager', 'context' => 'admin', 'category' => 'plateforme', 'primary' => false],
        ['key' => 'admin:admin.fx.center', 'label' => 'FX (devises)', 'icon' => '💱', 'route' => 'admin.fx.center', 'context' => 'admin', 'category' => 'plateforme', 'primary' => false],
        ['key' => 'admin:admin.geolocation-v2.center', 'label' => 'Géolocalisation', 'icon' => '🗺️', 'route' => 'admin.geolocation-v2.center', 'context' => 'admin', 'category' => 'plateforme', 'primary' => false],
        ['key' => 'admin:admin.modules', 'label' => 'Modules', 'icon' => '🧩', 'route' => 'admin.modules', 'context' => 'admin', 'category' => 'plateforme', 'primary' => false],
        ['key' => 'admin:admin.order-engine.catalog', 'label' => 'Catalogue géographique', 'icon' => '🗺️', 'route' => 'admin.order-engine.catalog', 'context' => 'admin', 'category' => 'plateforme', 'primary' => false],
        ['key' => 'admin:admin.outils', 'label' => 'Outils admin', 'icon' => '🛠️', 'route' => 'admin.outils', 'context' => 'admin', 'category' => 'plateforme', 'primary' => false],
        ['key' => 'admin:admin.platform.readiness', 'label' => 'Readiness', 'icon' => '✅', 'route' => 'admin.platform.readiness', 'context' => 'admin', 'category' => 'plateforme', 'primary' => false],
        ['key' => 'admin:admin.trades', 'label' => 'Trades catalogue', 'icon' => '🛠️', 'route' => 'admin.trades', 'context' => 'admin', 'category' => 'plateforme', 'primary' => false],
        /*
         * Ces trois pages n'étaient atteignables que depuis une autre page — la modale calendrier
         * du tableau de bord, l'écran du calendrier interne, le panneau d'activité. Libellés tirés
         * des composants : CalendrierInterne, GoogleAgendaSettings, AuditLogsCenter.
         */
        ['key' => 'admin:admin.calendar', 'label' => 'Calendrier interne', 'icon' => '📅', 'route' => 'admin.calendar', 'context' => 'admin', 'category' => 'rendez-vous', 'primary' => false],
        ['key' => 'admin:admin.calendar.settings', 'label' => 'Réglages Google Agenda', 'icon' => '🗓️', 'route' => 'admin.calendar.settings', 'context' => 'admin', 'category' => 'rendez-vous', 'primary' => false],
        ['key' => 'admin:admin.audit.logs', 'label' => 'Journaux d’audit', 'icon' => '🔍', 'route' => 'admin.audit.logs', 'context' => 'admin', 'category' => 'conformite', 'primary' => false],
        ['key' => 'admin:admin.translations.center', 'label' => 'Traductions i18n', 'icon' => '🌐', 'route' => 'admin.translations.center', 'context' => 'admin', 'category' => 'plateforme', 'primary' => false],
        ['key' => 'admin:admin.webhooks-v2.center', 'label' => 'Webhooks B2B', 'icon' => '🔗', 'route' => 'admin.webhooks-v2.center', 'context' => 'admin', 'category' => 'plateforme', 'primary' => false],

        // ---- client-company ----
        /*
         * Une société cliente commande comme un particulier — la page est la même. Sa vérification
         * d'entreprise et ses données personnelles aussi : ces routes sont gardées `role:client`,
         * et `isClient()` est vrai pour elle (il délègue à `isClientCompany()`).
         */
        ['key' => 'client-company:booking.create', 'label' => 'Prendre rendez-vous', 'icon' => '➕', 'route' => 'booking.create', 'context' => 'client-company', 'category' => 'rendez-vous', 'primary' => false],
        ['key' => 'client-company:client.kyb.onboarding', 'label' => 'Vérification entreprise (KYB)', 'icon' => '🏢', 'route' => 'client.kyb.onboarding', 'context' => 'client-company', 'category' => 'conformite', 'primary' => false],
        ['key' => 'client-company:client.gdpr.data', 'label' => 'Mes données RGPD', 'icon' => '🔐', 'route' => 'client.gdpr.data', 'context' => 'client-company', 'category' => 'conformite', 'primary' => false],
        ['key' => 'client-company:client-company.bookings.bulk-import', 'label' => 'Import bulk', 'icon' => '📤', 'route' => 'client-company.bookings.bulk-import', 'context' => 'client-company', 'category' => 'rendez-vous', 'primary' => false],
        ['key' => 'client-company:client-company.bookings.index', 'label' => 'Réservations', 'icon' => '📅', 'route' => 'client-company.bookings.index', 'context' => 'client-company', 'category' => 'rendez-vous', 'primary' => true],
        // Le bouton d'appel du layout, hors de sa liste de liens — servi par `BookingHub`.
        ['key' => 'client-company:client-company.bookings.create', 'label' => 'Nouvelle réservation', 'icon' => '➕', 'route' => 'client-company.bookings.create', 'context' => 'client-company', 'category' => 'rendez-vous', 'primary' => false],
        ['key' => 'client-company:client-company.bookings.multi-site', 'label' => 'Multi-locaux', 'icon' => '🏢', 'route' => 'client-company.bookings.multi-site', 'context' => 'client-company', 'category' => 'rendez-vous', 'primary' => false],
        ['key' => 'client-company:client-company.contracts', 'label' => 'Contrats', 'icon' => '📄', 'route' => 'client-company.contracts', 'context' => 'client-company', 'category' => 'documents', 'primary' => false],
        ['key' => 'client-company:client-company.contracts.signing-appointments', 'label' => 'Signatures', 'icon' => '✍️', 'route' => 'client-company.contracts.signing-appointments', 'context' => 'client-company', 'category' => 'documents', 'primary' => false],
        ['key' => 'client-company:client-company.billing', 'label' => 'Facturation', 'icon' => '🧾', 'route' => 'client-company.billing', 'context' => 'client-company', 'category' => 'finance', 'primary' => true],
        ['key' => 'client-company:client-company.sites', 'label' => 'Mes locaux', 'icon' => '📍', 'route' => 'client-company.sites', 'context' => 'client-company', 'category' => 'comptes', 'primary' => true],
        ['key' => 'client-company:client-company.members', 'label' => 'Membres', 'icon' => '👥', 'route' => 'client-company.members', 'context' => 'client-company', 'category' => 'prestataires', 'primary' => true],
        ['key' => 'client-company:client-company.disputes', 'label' => 'Litiges', 'icon' => '⚠️', 'route' => 'client-company.disputes', 'context' => 'client-company', 'category' => 'qualite', 'primary' => false],
        ['key' => 'client-company:client-company.analytics', 'label' => 'Analytics', 'icon' => '📊', 'route' => 'client-company.analytics', 'context' => 'client-company', 'category' => 'donnees', 'primary' => false],
        ['key' => 'client-company:client-company.dashboard', 'label' => 'Accueil', 'icon' => '🏠', 'route' => 'client-company.dashboard', 'context' => 'client-company', 'category' => 'donnees', 'primary' => true],

        // ---- provider-company ----
        /*
         * Un gérant reste un prestataire vérifié : son dossier et sa présence le concernent au
         * même titre que l'indépendant. L'espace société n'en montrait aucun.
         */
        ['key' => 'provider-company:provider.onboarding', 'label' => 'Dossier de la société', 'icon' => '🚀', 'route' => 'provider.onboarding', 'context' => 'provider-company', 'category' => 'conformite', 'primary' => false],
        ['key' => 'provider-company:presence.me', 'label' => 'Ma présence', 'icon' => '🟢', 'route' => 'presence.me', 'context' => 'provider-company', 'category' => 'missions', 'primary' => false],
        ['key' => 'provider-company:provider-company.dispatch', 'label' => 'Dispatch', 'icon' => '🗺️', 'route' => 'provider-company.dispatch', 'context' => 'provider-company', 'permission' => 'missions.dispatch', 'category' => 'missions', 'primary' => true],
        ['key' => 'provider-company:provider-company.tasks', 'label' => 'Tâches', 'icon' => '✅', 'route' => 'provider-company.tasks', 'context' => 'provider-company', 'category' => 'missions', 'primary' => true],
        ['key' => 'provider-company:provider-company.field-teams', 'label' => 'Équipes terrain', 'icon' => '🚚', 'route' => 'provider-company.field-teams', 'context' => 'provider-company', 'permission' => 'team.view', 'category' => 'prestataires', 'primary' => false],
        /*
         * Les IMPLANTATIONS de la société — à ne pas confondre avec « Sites desservis », qui sont
         * les locaux du CLIENT. Deux entrées voisines dans le répertoire, deux notions opposées :
         * les libellés le disent, faute de quoi un gérant déclare son dépôt chez son client.
         */
        ['key' => 'provider-company:provider-company.agencies', 'label' => 'Nos implantations', 'icon' => '🏢', 'route' => 'provider-company.agencies', 'context' => 'provider-company', 'permission' => 'agencies.view', 'category' => 'prestataires', 'primary' => false],
        // Les sites clients desservis, et le référent que la société y place — servi par
        // `SiteOperations`, et par l'API que l'écran natif « Sites desservis » consomme.
        ['key' => 'provider-company:provider-company.sites', 'label' => 'Sites desservis', 'icon' => '📍', 'route' => 'provider-company.sites', 'context' => 'provider-company', 'permission' => 'sites.view_all', 'category' => 'comptes', 'primary' => false],
        ['key' => 'provider-company:provider-company.team', 'label' => 'Équipe', 'icon' => '👥', 'route' => 'provider-company.team', 'context' => 'provider-company', 'permission' => 'team.view', 'category' => 'prestataires', 'primary' => true],
        // La matrice rôle → permissions PROPRE à la société. `members.manage_permissions` est
        // réservée au propriétaire par défaut : distribuer des droits n'est pas inviter.
        ['key' => 'provider-company:provider-company.role-permissions', 'label' => 'Rôles et permissions', 'icon' => '🔑', 'route' => 'provider-company.role-permissions', 'context' => 'provider-company', 'permission' => 'members.manage_permissions', 'category' => 'comptes', 'primary' => false],
        ['key' => 'provider-company:provider-company.channels', 'label' => 'Canaux', 'icon' => '💬', 'route' => 'provider-company.channels', 'context' => 'provider-company', 'category' => 'communication', 'primary' => true],
        ['key' => 'provider-company:provider-company.dashboard', 'label' => 'Dashboard', 'icon' => '🏗️', 'route' => 'provider-company.dashboard', 'context' => 'provider-company', 'category' => 'donnees', 'primary' => true],
    ],

    /*
     * Routes de tableau de bord qui ne sont PAS des modules : téléchargements et callbacks OAuth.
     * Elles n'ont pas de page, donc pas de case. Chaque ligne porte sa raison, pour qu'on ne
     * puisse pas y glisser un vrai module en douce.
     */
    'non_modules' => [
        'admin.export.csv' => 'Téléchargement CSV, pas une page',
        'admin.export.pdf' => 'Téléchargement PDF, pas une page',
        'admin.feedbacks.export' => 'Téléchargement, pas une page',
        'admin.feedbacks.export.csv' => 'Téléchargement CSV, pas une page',
        'admin.missions.export.pdf' => 'Téléchargement PDF, pas une page',
        'admin.quality.export.incidents.csv' => 'Téléchargement CSV, pas une page',
        'admin.quality.export.missions.csv' => 'Téléchargement CSV, pas une page',
        'client.analytics.export.bookings' => 'Téléchargement CSV, pas une page',
        'client.analytics.export.kpis' => 'Téléchargement CSV, pas une page',
        'client.analytics.export.monthly_revenue' => 'Téléchargement CSV, pas une page',
        'client.exports.bookings.xlsx' => 'Téléchargement XLSX, pas une page',
        'employe.stripe-connect.refresh' => 'Callback OAuth Stripe, pas une page',
        'employe.stripe-connect.return' => 'Callback OAuth Stripe, pas une page',

        /*
         * `/dashboard` est un AIGUILLAGE, pas une page : une closure qui redirige vers le tableau
         * de bord du rôle. Lui donner une case renverrait l'utilisateur là où il est déjà.
         */
        'dashboard' => 'Redirection vers le tableau de bord du rôle, pas une page',

        /*
         * Les cinq répertoires eux-mêmes. Leur donner une case les ferait se lister — la page
         * renverrait vers la page. C'est le garde-fou qui les a signalés à leur création, ce qui
         * est précisément son travail.
         */
        'client.modules' => 'Le répertoire lui-même',
        'employe.modules' => 'Le répertoire lui-même',
        'admin.modules.directory' => 'Le répertoire lui-même',
        'client-company.modules' => 'Le répertoire lui-même',
        'provider-company.modules' => 'Le répertoire lui-même',
    ],
];
