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
 *   permission string|string[]  facultatif — une liste signifie « l'une OU l'autre »
 *
 * LA PERMISSION D'UNE CASE DOIT ÊTRE EXACTEMENT CELLE DE SON ÉCRAN.
 *
 * Ce champ n'est pas une politique de sécurité : la sécurité est dans le `mount()` du composant, qui
 * reste le seul juge. Il ne sert qu'à ne pas mentir sur ce qui s'ouvre — et il a menti dans les deux
 * sens tant que rien ne le mesurait :
 *
 *  - PLUS STRICTE QUE L'ÉCRAN, elle cache un module à qui y a droit. « Planning et absences »
 *    exigeait `team.view` alors que `WorkforcePlanning` n'exige rien : sept sous-rôles sur onze,
 *    dont l'exécutant à qui l'écran est destiné, n'avaient aucune porte vers leur propre planning.
 *  - PLUS LARGE QUE L'ÉCRAN, elle promet une page et rend un refus. Trente-quatre couples
 *    (sous-rôle, case) menaient à un 403 : voir une case grise vaut mieux que cliquer sur un mur.
 *
 * `CoherenceDesTuilesEtDesEcransTest` frappe les routes pour chacun des onze sous-rôles et exige
 * l'équivalence. Il ne lit pas le code : une première version comparait les textes par expression
 * régulière et a produit deux faux positifs en dix minutes.
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
        /*
         * LES DEVIS REÇUS (E24) — la moitié cliente du module.
         *
         * Une société qui bâtit un devis et l'envoie à un client qui ne peut pas y répondre n'a rien
         * envoyé : le client découvre le document par une notification et répond par téléphone,
         * c'est-à-dire hors de toute trace.
         */
        ['key' => 'client:client.quotes', 'label' => 'Devis reçus', 'icon' => '📄', 'route' => 'client.quotes', 'context' => 'client', 'category' => 'finance', 'primary' => false],
        /*
         * LE CARNET DE LIEUX (E2). Ce qui compte n'est pas l'adresse mais les CONSIGNES qui
         * l'accompagnent — l'étage, le digicode, la clé chez la voisine, l'allergie aux produits
         * chlorés. Elles se redonnaient oralement à chaque nouveau prestataire, ou se perdaient.
         */
        ['key' => 'client:client.places', 'label' => 'Mes lieux', 'icon' => '🏠', 'route' => 'client.places', 'context' => 'client', 'category' => 'comptes', 'primary' => false],
        // E4 — le budget maison. Tout est déjà en base et personne ne le voit : le client reçoit
        // ses factures une par une, sans jamais voir la tendance.
        /*
         * E6 — « Ma protection ». Assurance, annulation et litiges ont chacun leur écran, et aucun
         * client ne sait ce qu'il a : il découvre sa couverture au moment du sinistre, ses frais
         * d'annulation en annulant.
         */
        ['key' => 'client:client.protection', 'label' => 'Ma protection', 'icon' => '🛡️', 'route' => 'client.protection', 'context' => 'client', 'category' => 'qualite', 'primary' => false],
        ['key' => 'client:client.subscriptions-v2', 'label' => 'Abonnements', 'icon' => '🔄', 'route' => 'client.subscriptions-v2', 'context' => 'client', 'category' => 'finance', 'primary' => false],
        /*
         * NOS LOCATIONS -- l'entree cliente vers le catalogue de vehicules.
         *
         * Elle double la case du parcours de commande, et ce n'est pas une redondance : la case
         * n'existe que pendant qu'on compose une commande, alors qu'un client qui veut seulement
         * louer une voiture doit pouvoir y aller directement depuis son menu. C'est aussi ce qui
         * rend ces pages ATTEIGNABLES au sens du garde-fou de navigation -- une page qu'on ne
         * trouve qu'en tapant son URL n'existe pas vraiment.
         */
        ['key' => 'client:location.catalogue', 'label' => 'Location de voitures', 'icon' => '🚙', 'route' => 'location.catalogue', 'context' => 'client', 'category' => 'finance', 'primary' => false],
        ['key' => 'client:peer.catalogue', 'label' => 'Louer entre membres', 'icon' => '🔑', 'route' => 'peer.catalogue', 'context' => 'client', 'category' => 'prestataires', 'primary' => false],
        ['key' => 'client:peer.sejours', 'label' => 'Louer un logement', 'icon' => '🏠', 'route' => 'peer.sejours', 'context' => 'client', 'category' => 'prestataires', 'primary' => false],
        ['key' => 'client:peer.my-rentals', 'label' => 'Mes locations de véhicule', 'icon' => '🚗', 'route' => 'peer.my-rentals', 'context' => 'client', 'category' => 'prestataires', 'primary' => false],
        ['key' => 'client:peer.owner.vehicles', 'label' => 'Mes véhicules en location', 'icon' => '🅿️', 'route' => 'peer.owner.vehicles', 'context' => 'client', 'category' => 'croissance', 'primary' => false],
        ['key' => 'client:peer.owner.stays', 'label' => 'Mes logements en location', 'icon' => '🏠', 'route' => 'peer.owner.stays', 'context' => 'client', 'category' => 'croissance', 'primary' => false],
        ['key' => 'client:client.wallet', 'label' => 'Portefeuille', 'icon' => '👛', 'route' => 'client.wallet', 'context' => 'client', 'category' => 'finance', 'primary' => false],
        /*
         * LA PORTE VERS L'ESPACE SOCIÉTÉ, ET SA CONDITION.
         *
         * `visible_si` appelle la méthode du modèle User : sans elle, la case s'afficherait à tout
         * client, et un particulier cliquerait vers un 403 gardé par `org.type`. L'ancienne navbar
         * portait cette condition en dur — le registre la porte désormais.
         */
        ['key' => 'client:client.ai.quote.photo', 'label' => 'Devis IA depuis photo', 'icon' => '🤖', 'route' => 'client.ai.quote.photo', 'context' => 'client', 'category' => 'prestataires', 'primary' => false],
        ['key' => 'client:client.bundles.manage', 'label' => 'Chantiers groupés', 'icon' => '🏗️', 'route' => 'client.bundles.manage', 'context' => 'client', 'category' => 'prestataires', 'primary' => false],
        ['key' => 'client:client.providers.browse', 'label' => 'Trouver un prestataire', 'icon' => '🔍', 'route' => 'client.providers.browse', 'context' => 'client', 'category' => 'prestataires', 'primary' => true],
        ['key' => 'client:client.companies.browse', 'label' => 'Trouver une société', 'icon' => '🏢', 'route' => 'client.companies.browse', 'context' => 'client', 'category' => 'prestataires', 'primary' => false],
        ['key' => 'client:client.chat.inbox', 'label' => 'Messagerie', 'icon' => '💬', 'route' => 'client.chat.inbox', 'context' => 'client', 'category' => 'communication', 'primary' => false],
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
        ['key' => 'client:client.analytics.dashboard', 'label' => 'Mes statistiques', 'icon' => '📊', 'route' => 'client.analytics.dashboard', 'context' => 'client', 'category' => 'donnees', 'primary' => false, 'visible_si' => 'isClientCompany'],

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
        ['key' => 'employe:peer.catalogue', 'label' => 'Louer entre membres', 'icon' => '🔑', 'route' => 'peer.catalogue', 'context' => 'employe', 'category' => 'prestataires', 'primary' => false],
        ['key' => 'employe:peer.sejours', 'label' => 'Louer un logement', 'icon' => '🏠', 'route' => 'peer.sejours', 'context' => 'employe', 'category' => 'prestataires', 'primary' => false],
        ['key' => 'employe:peer.my-rentals', 'label' => 'Mes locations de véhicule', 'icon' => '🚗', 'route' => 'peer.my-rentals', 'context' => 'employe', 'category' => 'prestataires', 'primary' => false],
        ['key' => 'employe:peer.owner.vehicles', 'label' => 'Mes véhicules en location', 'icon' => '🅿️', 'route' => 'peer.owner.vehicles', 'context' => 'employe', 'category' => 'croissance', 'primary' => false],
        ['key' => 'employe:peer.owner.stays', 'label' => 'Mes logements en location', 'icon' => '🏠', 'route' => 'peer.owner.stays', 'context' => 'employe', 'category' => 'croissance', 'primary' => false],
        ['key' => 'employe:provider.onboarding', 'label' => 'Mon dossier', 'icon' => '🚀', 'route' => 'provider.onboarding', 'context' => 'employe', 'category' => 'conformite', 'primary' => false],
        /*
         * VÉRIFICATION FACIALE — visible SEULEMENT quand elle concerne la personne qui regarde.
         *
         * Sans `visible_si`, la case s'afficherait à tout prestataire, y compris à l'immense
         * majorité dont aucun métier ne l'exige : une case qui mène à « rien à faire ici » apprend
         * à ne plus lire le menu.
         *
         * Et sans case du tout, la page ne serait atteignable qu'en tapant son URL — ce que
         * `ToutePageEstAtteignableTest` refuse à juste titre, puisque c'est la cible d'une
         * redirection que le prestataire doit aussi pouvoir rouvrir de lui-même.
         */
        ['key' => 'employe:provider.face-check', 'label' => 'Vérification d’identité', 'icon' => '🪞', 'route' => 'provider.face-check', 'context' => 'employe', 'category' => 'conformite', 'primary' => false, 'visible_si' => 'estSoumisAuControleFacial'],
        ['key' => 'employe:presence.me', 'label' => 'Ma présence', 'icon' => '🟢', 'route' => 'presence.me', 'context' => 'employe', 'category' => 'missions', 'primary' => false],
        ['key' => 'employe:employe.disponibilites', 'label' => 'Disponibilités', 'icon' => '🕒', 'route' => 'employe.disponibilites', 'context' => 'employe', 'category' => 'rendez-vous', 'primary' => true],
        ['key' => 'employe:employe.google.calendar', 'label' => 'Google Agenda', 'icon' => '🗓️', 'route' => 'employe.google.calendar', 'context' => 'employe', 'category' => 'rendez-vous', 'primary' => false],
        ['key' => 'employe:employe.planning', 'label' => 'Planning', 'icon' => '📅', 'route' => 'employe.planning', 'context' => 'employe', 'category' => 'rendez-vous', 'primary' => true],
        /*
         * DÉTRÔNÉ DE LA BARRE ALLÉGÉE au profit de « Sécurité ».
         *
         * Elle n'accepte que cinq entrées par contexte, et c'est une règle de conception, pas une
         * limite technique : au-delà, plus rien ne ressort. Il fallait donc arbitrer.
         *
         * « Devis chantiers » est un écran d'enchère qu'on ouvre quand on cherche du travail —
         * utile, occasionnel, et parfaitement à sa place dans le répertoire. « Sécurité » est le
         * seul écran de ce registre dont l'indisponibilité se compte en intégrité physique, et il
         * n'a rien à faire au fond d'un répertoire qu'on ouvre à tête reposée.
         */
        ['key' => 'employe:employe.bundle-quotes', 'label' => 'Devis chantiers', 'icon' => '🏗️', 'route' => 'employe.bundle-quotes', 'context' => 'employe', 'category' => 'missions', 'primary' => false],
        ['key' => 'employe:employe.dashboard', 'label' => 'Ma journée', 'icon' => '🏠', 'route' => 'employe.dashboard', 'context' => 'employe', 'category' => 'missions', 'primary' => true],
        ['key' => 'employe:employe.historique', 'label' => 'Historique', 'icon' => '🕘', 'route' => 'employe.historique', 'context' => 'employe', 'category' => 'missions', 'primary' => false],
        ['key' => 'employe:employe.missions', 'label' => 'Mes missions', 'icon' => '📋', 'route' => 'employe.missions', 'context' => 'employe', 'category' => 'missions', 'primary' => true],
        ['key' => 'employe:employe.earnings', 'label' => 'Tableau de bord revenus', 'icon' => '💰', 'route' => 'employe.earnings', 'context' => 'employe', 'category' => 'finance', 'primary' => false],
        /*
         * E33 — le mode sécurité. `primary` à VRAI : c'est la seule entrée de tout ce registre
         * dont l'indisponibilité se compte en intégrité physique, et elle n'a rien à faire au
         * fond d'un répertoire qu'on ouvre à tête reposée.
         */
        ['key' => 'employe:employe.safety', 'label' => 'Sécurité', 'icon' => '🛡️', 'route' => 'employe.safety', 'context' => 'employe', 'category' => 'conformite', 'primary' => true],
        // E12 — où se placer, et à quelle heure. Les ledgers du dispatch portaient la réponse
        // depuis le chantier de répartition ; personne ne la lisait.
        ['key' => 'employe:employe.heatmap', 'label' => 'Où me placer', 'icon' => '🗺️', 'route' => 'employe.heatmap', 'context' => 'employe', 'category' => 'donnees', 'primary' => false],
        ['key' => 'employe:employe.stripe-connect.start', 'label' => 'Stripe Connect', 'icon' => '💳', 'route' => 'employe.stripe-connect.start', 'context' => 'employe', 'category' => 'finance', 'primary' => false],
        ['key' => 'employe:employe.wallet', 'label' => 'Mon portefeuille', 'icon' => '👛', 'route' => 'employe.wallet', 'context' => 'employe', 'category' => 'finance', 'primary' => false],
        ['key' => 'employe:employe.coordination', 'label' => 'Coordination', 'icon' => '🧭', 'route' => 'employe.coordination', 'context' => 'employe', 'category' => 'prestataires', 'primary' => false],
        ['key' => 'employe:employe.team', 'label' => 'Équipe terrain', 'icon' => '👥', 'route' => 'employe.team', 'context' => 'employe', 'category' => 'prestataires', 'primary' => false],
        ['key' => 'employe:employe.teamlead.operations', 'label' => 'Chef d’équipe', 'icon' => '🧑‍💼', 'route' => 'employe.teamlead.operations', 'context' => 'employe', 'category' => 'prestataires', 'primary' => false, 'visible_si' => 'isFieldTeamLead'],
        ['key' => 'employe:employe.disputes', 'label' => 'Mes litiges', 'icon' => '⚠️', 'route' => 'employe.disputes', 'context' => 'employe', 'category' => 'qualite', 'primary' => false],
        ['key' => 'employe:employe.incident', 'label' => 'Incident', 'icon' => '⚠️', 'route' => 'employe.incident', 'context' => 'employe', 'category' => 'qualite', 'primary' => false],
        ['key' => 'employe:employe.kyc', 'label' => 'KYC / Identité', 'icon' => '🛡️', 'route' => 'employe.kyc', 'context' => 'employe', 'category' => 'conformite', 'primary' => false],
        /*
         * Distinct de « KYC / Identité » juste au-dessus, et il faut que ça se voie : l'un vérifie
         * QUI vous êtes, l'autre CE QUE vous avez le droit de conduire. Les fondre ferait chercher
         * un permis dans un écran qui parle de carte d'identité — et un prestataire qui ne trouve
         * pas où déposer sa pièce ne la dépose pas.
         */
        ['key' => 'employe:employe.driving', 'label' => 'Conduite et véhicule', 'icon' => '🚗', 'route' => 'employe.driving', 'context' => 'employe', 'category' => 'conformite', 'primary' => false],
        ['key' => 'employe:employe.validation.multiple', 'label' => 'Validation multiple', 'icon' => '✅', 'route' => 'employe.validation.multiple', 'context' => 'employe', 'category' => 'conformite', 'primary' => false],
        ['key' => 'employe:employe.badges', 'label' => 'Mes badges', 'icon' => '🏆', 'route' => 'employe.badges', 'context' => 'employe', 'category' => 'croissance', 'primary' => false],
        // « Ce que je fais, et où » : les deux tables que lit la requête candidate du dispatch.
        // Cocher une case change les offres reçues dans la seconde — c'est la page la plus
        // structurante de l'espace prestataire, elle est donc primaire.
        ['key' => 'employe:employe.trades-zones', 'label' => 'Métiers et zones', 'icon' => '🧭', 'route' => 'employe.trades-zones', 'context' => 'employe', 'category' => 'missions', 'primary' => false],
        ['key' => 'employe:employe.feedbacks', 'label' => 'Feedbacks reçus', 'icon' => '💬', 'route' => 'employe.feedbacks', 'context' => 'employe', 'category' => 'croissance', 'primary' => false],
        ['key' => 'employe:employe.ratings', 'label' => 'Mes avis', 'icon' => '⭐', 'route' => 'employe.ratings', 'context' => 'employe', 'category' => 'croissance', 'primary' => false],

        // ---- admin ----
        ['key' => 'admin:peer.admin', 'label' => 'Location entre membres', 'icon' => '🔑', 'route' => 'peer.admin', 'context' => 'admin', 'category' => 'prestataires', 'primary' => false, 'gate' => 'manage-peer-rentals'],
        ['key' => 'admin:admin.availability.center', 'label' => 'Disponibilités', 'icon' => '📆', 'route' => 'admin.availability.center', 'context' => 'admin', 'category' => 'rendez-vous', 'primary' => false, 'gate' => 'manage-calendar'],
        ['key' => 'admin:admin.planning', 'label' => 'Planning', 'icon' => '📅', 'route' => 'admin.planning', 'context' => 'admin', 'category' => 'rendez-vous', 'primary' => true, 'gate' => 'manage-calendar'],
        ['key' => 'admin:admin.automation', 'label' => 'Automation', 'icon' => '⚙️', 'route' => 'admin.automation', 'context' => 'admin', 'category' => 'missions', 'primary' => false, 'gate' => 'manage-automation'],
        ['key' => 'admin:admin.bundles.center', 'label' => 'Bundles chantiers', 'icon' => '🏗️', 'route' => 'admin.bundles.center', 'context' => 'admin', 'category' => 'missions', 'primary' => false, 'gate' => 'manage-orchestration'],
        ['key' => 'admin:admin.fleet-v2.center', 'label' => 'Fleet véhicules', 'icon' => '🚐', 'route' => 'admin.fleet-v2.center', 'context' => 'admin', 'category' => 'missions', 'primary' => false, 'gate' => 'manage-orchestration'],
        ['key' => 'admin:admin.rentals.center', 'label' => 'Nos locations', 'icon' => '🚙', 'route' => 'admin.rentals.center', 'context' => 'admin', 'category' => 'finance', 'primary' => false, 'gate' => 'manage-rentals'],
        ['key' => 'admin:admin.missions', 'label' => 'Missions', 'icon' => '📋', 'route' => 'admin.missions', 'context' => 'admin', 'category' => 'missions', 'primary' => true, 'gate' => 'manage-orchestration'],
        ['key' => 'admin:admin.orchestration', 'label' => 'Orchestration', 'icon' => '🧭', 'route' => 'admin.orchestration', 'context' => 'admin', 'category' => 'missions', 'primary' => false, 'gate' => 'manage-orchestration'],
        ['key' => 'admin:admin.presence.center', 'label' => 'Presence providers', 'icon' => '🟢', 'route' => 'admin.presence.center', 'context' => 'admin', 'category' => 'missions', 'primary' => false, 'gate' => 'manage-orchestration'],
        ['key' => 'admin:admin.trip-tracking.center', 'label' => 'Trip Tracking GPS', 'icon' => '📍', 'route' => 'admin.trip-tracking.center', 'context' => 'admin', 'category' => 'missions', 'primary' => false, 'gate' => 'manage-orchestration'],
        ['key' => 'admin:admin.accounting-v2.center', 'label' => 'Comptabilité (FEC/Sage)', 'icon' => '📒', 'route' => 'admin.accounting-v2.center', 'context' => 'admin', 'category' => 'documents', 'primary' => false, 'gate' => 'manage-accounting'],
        ['key' => 'admin:admin.contracts-v2.center', 'label' => 'Contrats v2', 'icon' => '📜', 'route' => 'admin.contracts-v2.center', 'context' => 'admin', 'category' => 'documents', 'primary' => false, 'gate' => 'manage-entreprises'],
        ['key' => 'admin:admin.b2b.monthly-invoices', 'label' => 'Factures B2B', 'icon' => '🧾', 'route' => 'admin.b2b.monthly-invoices', 'context' => 'admin', 'category' => 'finance', 'primary' => false, 'gate' => 'manage-entreprises'],
        ['key' => 'admin:admin.business.dashboard', 'label' => 'Business', 'icon' => '🏢', 'route' => 'admin.business.dashboard', 'context' => 'admin', 'category' => 'finance', 'primary' => false, 'gate' => 'manage-finance'],
        ['key' => 'admin:admin.cancellations-v2.center', 'label' => 'Annulations v2', 'icon' => '🚫', 'route' => 'admin.cancellations-v2.center', 'context' => 'admin', 'category' => 'finance', 'primary' => false, 'gate' => 'manage-finance'],
        // Meme categorie et meme garde que son voisin : qui administre les politiques d'annulation
        // administre leur questionnaire.
        ['key' => 'admin:admin.cancellation-questions.center', 'label' => 'Questionnaire d’annulation', 'icon' => '❓', 'route' => 'admin.cancellation-questions.center', 'context' => 'admin', 'category' => 'finance', 'primary' => false, 'gate' => 'manage-finance'],
        ['key' => 'admin:admin.customer.credits', 'label' => 'Crédits clients', 'icon' => '💰', 'route' => 'admin.customer.credits', 'context' => 'admin', 'category' => 'finance', 'primary' => false, 'gate' => 'manage-finance'],
        ['key' => 'admin:admin.enterprise.approvals', 'label' => 'Approbations', 'icon' => '📑', 'route' => 'admin.enterprise.approvals', 'context' => 'admin', 'category' => 'finance', 'primary' => false, 'gate' => 'manage-entreprises'],
        ['key' => 'admin:admin.finance', 'label' => 'Finance', 'icon' => '💶', 'route' => 'admin.finance', 'context' => 'admin', 'category' => 'finance', 'primary' => false, 'gate' => 'manage-finance'],
        ['key' => 'admin:admin.pricing-v2.center', 'label' => 'Pricing v2 (DSL)', 'icon' => '💵', 'route' => 'admin.pricing-v2.center', 'context' => 'admin', 'category' => 'finance', 'primary' => false, 'gate' => 'manage-finance'],
        ['key' => 'admin:admin.stripe-connect.providers', 'label' => 'Stripe prestataires', 'icon' => '💳', 'route' => 'admin.stripe-connect.providers', 'context' => 'admin', 'category' => 'finance', 'primary' => false, 'gate' => 'manage-finance'],
        ['key' => 'admin:admin.stripe.hardening', 'label' => 'Stripe hardening', 'icon' => '💳', 'route' => 'admin.stripe.hardening', 'context' => 'admin', 'category' => 'finance', 'primary' => false, 'gate' => 'manage-finance'],
        ['key' => 'admin:admin.subscriptions-v2.center', 'label' => 'Abonnements v2', 'icon' => '🔁', 'route' => 'admin.subscriptions-v2.center', 'context' => 'admin', 'category' => 'finance', 'primary' => false, 'gate' => 'manage-finance'],
        ['key' => 'admin:admin.tips.center', 'label' => 'Pourboires (Tips)', 'icon' => '💰', 'route' => 'admin.tips.center', 'context' => 'admin', 'category' => 'finance', 'primary' => false, 'gate' => 'manage-finance'],
        ['key' => 'admin:admin.b2b.operations', 'label' => 'B2B opérations', 'icon' => '🏢', 'route' => 'admin.b2b.operations', 'context' => 'admin', 'category' => 'comptes', 'primary' => false, 'gate' => 'manage-entreprises'],
        ['key' => 'admin:admin.entreprises', 'label' => 'Entreprises', 'icon' => '🏢', 'route' => 'admin.entreprises', 'context' => 'admin', 'category' => 'comptes', 'primary' => false, 'gate' => 'manage-entreprises'],
        ['key' => 'admin:admin.premium.clients', 'label' => 'Clients premium', 'icon' => '⭐', 'route' => 'admin.premium.clients', 'context' => 'admin', 'category' => 'comptes', 'primary' => false, 'gate' => 'manage-premium'],
        ['key' => 'admin:admin.sites', 'label' => 'Sites', 'icon' => '📍', 'route' => 'admin.sites', 'context' => 'admin', 'category' => 'comptes', 'primary' => false, 'gate' => 'manage-entreprises'],
        ['key' => 'admin:admin.teams.partners', 'label' => 'Équipes & partenaires', 'icon' => '👥', 'route' => 'admin.teams.partners', 'context' => 'admin', 'category' => 'comptes', 'primary' => false, 'gate' => 'manage-entreprises'],
        ['key' => 'admin:admin.utilisateurs.manage', 'label' => 'Utilisateurs', 'icon' => '👤', 'route' => 'admin.utilisateurs.manage', 'context' => 'admin', 'category' => 'comptes', 'primary' => false, 'gate' => 'manage-users'],
        ['key' => 'admin:admin.onboarding-v2.center', 'label' => 'Onboarding prestataires', 'icon' => '🚪', 'route' => 'admin.onboarding-v2.center', 'context' => 'admin', 'category' => 'prestataires', 'primary' => false, 'gate' => 'manage-users'],
        ['key' => 'admin:admin.chat-v2.center', 'label' => 'Chat & messagerie', 'icon' => '💬', 'route' => 'admin.chat-v2.center', 'context' => 'admin', 'category' => 'communication', 'primary' => false, 'gate' => 'manage-communication'],
        ['key' => 'admin:admin.emails', 'label' => 'Emails produit', 'icon' => '✉️', 'route' => 'admin.emails', 'context' => 'admin', 'category' => 'communication', 'primary' => false, 'gate' => 'manage-communication'],
        ['key' => 'admin:admin.notification-preferences.center', 'label' => 'Notifications prefs', 'icon' => '🔔', 'route' => 'admin.notification-preferences.center', 'context' => 'admin', 'category' => 'communication', 'primary' => false, 'gate' => 'manage-communication'],
        ['key' => 'admin:admin.push.center', 'label' => 'Push notifications', 'icon' => '📱', 'route' => 'admin.push.center', 'context' => 'admin', 'category' => 'communication', 'primary' => false, 'gate' => 'manage-communication'],
        ['key' => 'admin:admin.realtime.center', 'label' => 'Realtime / Reverb', 'icon' => '⚡', 'route' => 'admin.realtime.center', 'context' => 'admin', 'category' => 'communication', 'primary' => false, 'gate' => 'manage-communication'],
        ['key' => 'admin:admin.sms.center', 'label' => 'SMS / WhatsApp', 'icon' => '💬', 'route' => 'admin.sms.center', 'context' => 'admin', 'category' => 'communication', 'primary' => false, 'gate' => 'manage-communication'],
        ['key' => 'admin:admin.badges.center', 'label' => 'Provider badges', 'icon' => '🏅', 'route' => 'admin.badges.center', 'context' => 'admin', 'category' => 'qualite', 'primary' => false, 'gate' => 'manage-quality'],
        ['key' => 'admin:admin.disputes.center', 'label' => 'Litiges & SAV', 'icon' => '🛟', 'route' => 'admin.disputes.center', 'context' => 'admin', 'category' => 'qualite', 'primary' => false, 'gate' => 'manage-quality'],
        ['key' => 'admin:admin.feedbacks', 'label' => 'Feedbacks', 'icon' => '💬', 'route' => 'admin.feedbacks', 'context' => 'admin', 'category' => 'qualite', 'primary' => false, 'gate' => 'manage-quality'],
        ['key' => 'admin:admin.quality.center', 'label' => 'Inspections qualité', 'icon' => '✔️', 'route' => 'admin.quality.center', 'context' => 'admin', 'category' => 'qualite', 'primary' => false, 'gate' => 'manage-quality'],
        ['key' => 'admin:admin.ratings.moderation', 'label' => 'Modération avis', 'icon' => '⭐', 'route' => 'admin.ratings.moderation', 'context' => 'admin', 'category' => 'qualite', 'primary' => false, 'gate' => 'manage-quality'],
        ['key' => 'admin:admin.safety.center', 'label' => 'Signalements & blocks', 'icon' => '🚫', 'route' => 'admin.safety.center', 'context' => 'admin', 'category' => 'qualite', 'primary' => false, 'gate' => 'manage-quality'],
        /*
         * E29 + E30 + E31 — la santé du marché. Le centre de répartition montre les missions en
         * cours ; il ne dit pas si le marché TIENT. Une zone où une recherche sur trois s'épuise
         * sans candidat s'apprend aujourd'hui par les plaintes des clients, trois mois trop tard.
         */
        ['key' => 'admin:admin.marketplace.health', 'label' => 'Santé du marché', 'icon' => '📉', 'route' => 'admin.marketplace.health', 'context' => 'admin', 'category' => 'donnees', 'primary' => false, 'gate' => 'manage-analytics'],
        ['key' => 'admin:admin.audit.center', 'label' => 'Audit v2', 'icon' => '🔍', 'route' => 'admin.audit.center', 'context' => 'admin', 'category' => 'conformite', 'primary' => false, 'gate' => 'manage-audit-logs'],
        ['key' => 'admin:admin.gdpr.center', 'label' => 'GDPR / RGPD', 'icon' => '🔐', 'route' => 'admin.gdpr.center', 'context' => 'admin', 'category' => 'conformite', 'primary' => false, 'gate' => 'manage-compliance'],
        ['key' => 'admin:admin.insurance.center', 'label' => 'Assurance', 'icon' => '🛡️', 'route' => 'admin.insurance.center', 'context' => 'admin', 'category' => 'conformite', 'primary' => false, 'gate' => 'manage-compliance'],
        ['key' => 'admin:admin.kyb-v2.center', 'label' => 'KYB entreprises', 'icon' => '🏢', 'route' => 'admin.kyb-v2.center', 'context' => 'admin', 'category' => 'conformite', 'primary' => false, 'gate' => 'manage-compliance'],
        ['key' => 'admin:admin.risk.center', 'label' => 'Risk scoring', 'icon' => '🚨', 'route' => 'admin.risk.center', 'context' => 'admin', 'category' => 'conformite', 'primary' => false, 'gate' => 'manage-compliance'],
        ['key' => 'admin:admin.face-check.center', 'label' => 'Vérification faciale', 'icon' => '🪞', 'route' => 'admin.face-check.center', 'context' => 'admin', 'category' => 'conformite', 'primary' => false, 'gate' => 'manage-face-check'],
        ['key' => 'admin:admin.loyalty.center', 'label' => 'Programme fidélité', 'icon' => '🎖️', 'route' => 'admin.loyalty.center', 'context' => 'admin', 'category' => 'croissance', 'primary' => false, 'gate' => 'manage-automation'],
        ['key' => 'admin:admin.loyalty.rewards.center', 'label' => 'Récompenses loyalty', 'icon' => '🎁', 'route' => 'admin.loyalty.rewards.center', 'context' => 'admin', 'category' => 'croissance', 'primary' => false, 'gate' => 'manage-automation'],
        ['key' => 'admin:admin.marketing.center', 'label' => 'Marketing automation', 'icon' => '📣', 'route' => 'admin.marketing.center', 'context' => 'admin', 'category' => 'croissance', 'primary' => false, 'gate' => 'manage-automation'],
        ['key' => 'admin:admin.promotions.campaigns', 'label' => 'Campagnes promo', 'icon' => '📢', 'route' => 'admin.promotions.campaigns', 'context' => 'admin', 'category' => 'croissance', 'primary' => false, 'gate' => 'manage-automation'],
        ['key' => 'admin:admin.promotions.codes', 'label' => 'Codes promo', 'icon' => '🎟️', 'route' => 'admin.promotions.codes', 'context' => 'admin', 'category' => 'croissance', 'primary' => false, 'gate' => 'manage-automation'],
        ['key' => 'admin:admin.promotions.referrals', 'label' => 'Parrainage', 'icon' => '🤝', 'route' => 'admin.promotions.referrals', 'context' => 'admin', 'category' => 'croissance', 'primary' => false, 'gate' => 'manage-automation'],
        /*
         * Le centre de répartition : l'histoire complète d'une recherche — qui a été sollicité,
         * quand, à quelle distance, refus ou silence. Sans lui, les quatre causes d'un échec de
         * dispatch se ressemblent et l'exploitation conclut « pas assez de prestataires ».
         *
         * `primary => false` : la barre d'accès rapide n'en porte que cinq, et les cinq places sont
         * déjà prises par des pages qu'un administrateur ouvre chaque jour. Une sixième les
         * remplacerait toutes par un menu déroulant.
         */
        ['key' => 'admin:admin.dispatch.center', 'label' => 'Répartition', 'icon' => '📡', 'route' => 'admin.dispatch.center', 'context' => 'admin', 'category' => 'donnees', 'primary' => false, 'gate' => 'manage-orchestration'],
        ['key' => 'admin:admin.alerts', 'label' => 'Alertes', 'icon' => '🚨', 'route' => 'admin.alerts', 'context' => 'admin', 'category' => 'donnees', 'primary' => true, 'gate' => 'manage-analytics'],
        ['key' => 'admin:admin.analytics', 'label' => 'Analytics', 'icon' => '📈', 'route' => 'admin.analytics', 'context' => 'admin', 'category' => 'donnees', 'primary' => false, 'gate' => 'manage-analytics'],
        ['key' => 'admin:admin.analytics.cancellations', 'label' => 'Raisons annulation', 'icon' => '❌', 'route' => 'admin.analytics.cancellations', 'context' => 'admin', 'category' => 'donnees', 'primary' => false, 'gate' => 'manage-analytics'],
        ['key' => 'admin:admin.analytics.center', 'label' => 'Analytics v2', 'icon' => '📊', 'route' => 'admin.analytics.center', 'context' => 'admin', 'category' => 'donnees', 'primary' => false, 'gate' => 'manage-analytics'],
        ['key' => 'admin:admin.analytics.exploration', 'label' => 'Exploration métier', 'icon' => '🔎', 'route' => 'admin.analytics.exploration', 'context' => 'admin', 'category' => 'donnees', 'primary' => false, 'gate' => 'manage-analytics'],
        ['key' => 'admin:admin.dashboard', 'label' => 'Dashboard', 'icon' => '📊', 'route' => 'admin.dashboard', 'context' => 'admin', 'category' => 'donnees', 'primary' => true],
        ['key' => 'admin:admin.home', 'label' => 'Vue d’ensemble', 'icon' => '🗂️', 'route' => 'admin.home', 'context' => 'admin', 'category' => 'donnees', 'primary' => true],
        ['key' => 'admin:admin.nps.center', 'label' => 'NPS scoring', 'icon' => '📊', 'route' => 'admin.nps.center', 'context' => 'admin', 'category' => 'donnees', 'primary' => false, 'gate' => 'manage-analytics'],
        ['key' => 'admin:admin.api-tokens-v2.center', 'label' => 'API Tokens v2', 'icon' => '🔑', 'route' => 'admin.api-tokens-v2.center', 'context' => 'admin', 'category' => 'plateforme', 'primary' => false, 'gate' => 'manage-platform'],
        ['key' => 'admin:admin.feature-flags.manager', 'label' => 'Feature flags', 'icon' => '🚩', 'route' => 'admin.feature-flags.manager', 'context' => 'admin', 'category' => 'plateforme', 'primary' => false, 'gate' => 'manage-modules'],
        ['key' => 'admin:admin.fx.center', 'label' => 'FX (devises)', 'icon' => '💱', 'route' => 'admin.fx.center', 'context' => 'admin', 'category' => 'plateforme', 'primary' => false, 'gate' => 'manage-international'],
        ['key' => 'admin:admin.geolocation-v2.center', 'label' => 'Géolocalisation', 'icon' => '🗺️', 'route' => 'admin.geolocation-v2.center', 'context' => 'admin', 'category' => 'plateforme', 'primary' => false, 'gate' => 'manage-platform'],
        ['key' => 'admin:admin.modules', 'label' => 'Modules', 'icon' => '🧩', 'route' => 'admin.modules', 'context' => 'admin', 'category' => 'plateforme', 'primary' => false, 'gate' => 'manage-modules'],
        ['key' => 'admin:admin.order-engine.catalog', 'label' => 'Catalogue géographique', 'icon' => '🗺️', 'route' => 'admin.order-engine.catalog', 'context' => 'admin', 'category' => 'plateforme', 'primary' => false, 'gate' => 'manage-platform'],
        ['key' => 'admin:admin.outils', 'label' => 'Outils admin', 'icon' => '🛠️', 'route' => 'admin.outils', 'context' => 'admin', 'category' => 'plateforme', 'primary' => false, 'gate' => 'manage-platform'],
        ['key' => 'admin:admin.platform.readiness', 'label' => 'Readiness', 'icon' => '✅', 'route' => 'admin.platform.readiness', 'context' => 'admin', 'category' => 'plateforme', 'primary' => false, 'gate' => 'manage-platform'],
        /*
         * Ces trois pages n'étaient atteignables que depuis une autre page — la modale calendrier
         * du tableau de bord, l'écran du calendrier interne, le panneau d'activité. Libellés tirés
         * des composants : CalendrierInterne, GoogleAgendaSettings, AuditLogsCenter.
         */
        ['key' => 'admin:admin.calendar', 'label' => 'Calendrier interne', 'icon' => '📅', 'route' => 'admin.calendar', 'context' => 'admin', 'category' => 'rendez-vous', 'primary' => false, 'gate' => 'manage-calendar'],
        ['key' => 'admin:admin.calendar.settings', 'label' => 'Réglages Google Agenda', 'icon' => '🗓️', 'route' => 'admin.calendar.settings', 'context' => 'admin', 'category' => 'rendez-vous', 'primary' => false, 'gate' => 'manage-calendar'],
        ['key' => 'admin:admin.audit.logs', 'label' => 'Journaux d’audit', 'icon' => '🔍', 'route' => 'admin.audit.logs', 'context' => 'admin', 'category' => 'conformite', 'primary' => false, 'gate' => 'manage-audit-logs'],
        ['key' => 'admin:admin.translations.center', 'label' => 'Traductions i18n', 'icon' => '🌐', 'route' => 'admin.translations.center', 'context' => 'admin', 'category' => 'plateforme', 'primary' => false, 'gate' => 'manage-international'],
        ['key' => 'admin:admin.webhooks-v2.center', 'label' => 'Webhooks B2B', 'icon' => '🔗', 'route' => 'admin.webhooks-v2.center', 'context' => 'admin', 'category' => 'plateforme', 'primary' => false, 'gate' => 'manage-platform'],

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
        ['key' => 'client-company:client-company.bookings.index', 'label' => 'Réservations', 'icon' => '📅', 'route' => 'client-company.bookings.index', 'context' => 'client-company', 'category' => 'rendez-vous', 'permission' => 'bookings.create', 'primary' => true],
        // Le bouton d'appel du layout, hors de sa liste de liens — servi par `BookingHub`.
        ['key' => 'client-company:client-company.bookings.create', 'label' => 'Nouvelle réservation', 'icon' => '➕', 'route' => 'client-company.bookings.create', 'context' => 'client-company', 'category' => 'rendez-vous', 'permission' => 'bookings.create', 'primary' => false],
        ['key' => 'client-company:client-company.bookings.multi-site', 'label' => 'Multi-locaux', 'icon' => '🏢', 'route' => 'client-company.bookings.multi-site', 'context' => 'client-company', 'category' => 'rendez-vous', 'primary' => false],
        ['key' => 'client-company:client-company.contracts', 'label' => 'Contrats', 'icon' => '📄', 'route' => 'client-company.contracts', 'context' => 'client-company', 'category' => 'documents', 'primary' => false],
        ['key' => 'client-company:client-company.contracts.signing-appointments', 'label' => 'Signatures', 'icon' => '✍️', 'route' => 'client-company.contracts.signing-appointments', 'context' => 'client-company', 'category' => 'documents', 'primary' => false],
        ['key' => 'client-company:client-company.billing', 'label' => 'Facturation', 'icon' => '🧾', 'route' => 'client-company.billing', 'context' => 'client-company', 'category' => 'finance', 'permission' => 'finance.view', 'primary' => true],
        /*
         * E7 + E8 + E9 + E11 — le pilotage. Le plafond alerte sans bloquer, l'approbation entre
         * dans le dispatch, le niveau de service répond à « est-ce qu'il tient ses engagements »,
         * et l'export épargne douze PDF à ressaisir à la main.
         */
        // L'écran s'ouvre par `finance.view` OU `bookings.approve` : le budget d'un côté, la file
        // d'approbation de l'autre. La case n'annonçait que la première, si bien que le directeur
        // opérations — qui approuve mais ne voit pas la finance — n'avait aucune porte vers les
        // demandes qui l'attendaient.
        ['key' => 'client-company:client-company.governance', 'label' => 'Pilotage', 'icon' => '🎯', 'route' => 'client-company.governance', 'context' => 'client-company', 'permission' => ['finance.view', 'bookings.approve'], 'category' => 'donnees', 'primary' => false],
        ['key' => 'client-company:client-company.sites', 'label' => 'Mes locaux', 'icon' => '📍', 'route' => 'client-company.sites', 'context' => 'client-company', 'category' => 'comptes', 'permission' => 'sites.view_all', 'primary' => true],
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
        ['key' => 'provider-company:provider-company.tasks', 'label' => 'Tâches', 'icon' => '✅', 'route' => 'provider-company.tasks', 'context' => 'provider-company', 'category' => 'missions', 'permission' => 'tasks.create', 'primary' => true],
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
        /*
         * `members.invite` ET NON `team.view` : c'est ce que `TeamManagement::mount()` exige.
         *
         * La case annonçait `team.view`, si bien qu'elle mentait DANS LES DEUX SENS. Le coordinateur
         * et le chef d'équipe la voyaient et récoltaient un 403 — une case qui promet une page et
         * rend un refus vaut moins que pas de case. Le gestionnaire, lui, pouvait inviter mais
         * n'avait aucune porte : l'écran n'était atteignable qu'en tapant l'URL.
         *
         * `team.view` reste la bonne clé pour « Équipes terrain », qui est un AUTRE écran : voir les
         * équipes n'est pas gérer qui entre dans la société.
         */
        ['key' => 'provider-company:provider-company.team', 'label' => 'Équipe', 'icon' => '👥', 'route' => 'provider-company.team', 'context' => 'provider-company', 'permission' => 'members.invite', 'category' => 'prestataires', 'primary' => true],
        /*
         * « Qui travaille quand » n'était écrit nulle part : quelqu'un qui ne travaillait pas ce
         * jour-là passait pour disponible, et l'auto-assignation lui envoyait une course à
         * vingt-trois heures. Les absences vivent sur le même écran — un planning qui les ignore
         * envoie la course le premier jour des vacances.
         */
        /*
         * AUCUNE PERMISSION : L'ÉCRAN EST CELUI DE CHACUN, et il filtre lui-même.
         *
         * `WorkforcePlanning` ne garde pas son montage — c'est délibéré, et c'est ce qui permet à un
         * exécutant de consulter SON planning et de poser SON congé. Sans `team.view`, il ne voit
         * que ses propres créneaux et que ses propres absences ; celles des collègues sont un fait
         * médical ou familial, gardé plus sévèrement.
         *
         * La case, elle, exigeait `team.view` : SEPT sous-rôles sur onze — dont l'exécutant, à qui
         * l'écran est d'abord destiné — n'avaient aucune porte vers leur propre planning. La
         * capacité existait, personne ne pouvait l'atteindre sans taper l'URL. C'est l'écran
         * orphelin déjà rencontré sur ce dépôt, cette fois par la permission plutôt que par la
         * navigation.
         */
        ['key' => 'provider-company:provider-company.planning', 'label' => 'Planning et absences', 'icon' => '🗓️', 'route' => 'provider-company.planning', 'context' => 'provider-company', 'category' => 'rendez-vous', 'primary' => true],
        // Les heures pointées, et la marge qu'elles laissent. Une société sait ce qu'elle facture,
        // pas ce que ça lui coûte : les deux termes n'existaient pas avant.
        // Sans permission, pour la même raison que le planning : `TimesheetCenter` montre ses
        // propres heures à qui n'a pas `team.view`, et la marge à qui a `analytics.view`.
        ['key' => 'provider-company:provider-company.timesheets', 'label' => 'Heures et rentabilité', 'icon' => '⏱️', 'route' => 'provider-company.timesheets', 'context' => 'provider-company', 'category' => 'finance', 'primary' => false],
        // Le stock de consommables. `inventory.view` va jusqu'aux exécutants : savoir ce qui reste
        // avant de partir n'est pas commander.
        ['key' => 'provider-company:provider-company.inventory', 'label' => 'Consommables', 'icon' => '📦', 'route' => 'provider-company.inventory', 'context' => 'provider-company', 'permission' => 'inventory.view', 'category' => 'missions', 'primary' => false],
        // E24 — le devis que la société bâtit elle-même, au lieu de le faire saisir à la main par
        // un administrateur de la plateforme.
        ['key' => 'provider-company:provider-company.quotes', 'label' => 'Devis', 'icon' => '📄', 'route' => 'provider-company.quotes', 'context' => 'provider-company', 'permission' => 'quotes.view', 'category' => 'finance', 'primary' => false],
        /*
         * E25 — le recrutement. L'invitation à jeton concluait DÉJÀ le recrutement ; l'offre et le
         * tri se passaient hors de la plateforme, et le lien entre les deux n'existait dans aucune
         * donnée : impossible de savoir quelle annonce avait produit quelle recrue.
         */
        ['key' => 'provider-company:provider-company.recruitment', 'label' => 'Recrutement', 'icon' => '📢', 'route' => 'provider-company.recruitment', 'context' => 'provider-company', 'permission' => 'recruitment.view', 'category' => 'prestataires', 'primary' => false],
        // E26 + E27 — le score qualité interne et la flotte : qui peut travailler demain, et avec
        // quoi. L'écran s'ouvre par `missions.quality` OU par `fleet.view` ; la case porte donc les
        // DEUX clés. N'en annoncer qu'une marchait par coïncidence — tout porteur de
        // `missions.quality` a aussi `fleet.view` aujourd'hui — et se serait cassé au premier
        // ajustement de la matrice, sans que rien ne le signale.
        ['key' => 'provider-company:provider-company.quality-fleet', 'label' => 'Qualité et matériel', 'icon' => '🔧', 'route' => 'provider-company.quality-fleet', 'context' => 'provider-company', 'permission' => ['missions.quality', 'fleet.view'], 'category' => 'qualite', 'primary' => false],
        // La matrice rôle → permissions PROPRE à la société. `members.manage_permissions` est
        // réservée au propriétaire par défaut : distribuer des droits n'est pas inviter.
        ['key' => 'provider-company:provider-company.role-permissions', 'label' => 'Rôles et permissions', 'icon' => '🔑', 'route' => 'provider-company.role-permissions', 'context' => 'provider-company', 'permission' => 'members.manage_permissions', 'category' => 'comptes', 'primary' => false],
        ['key' => 'provider-company:provider-company.channels', 'label' => 'Canaux', 'icon' => '💬', 'route' => 'provider-company.channels', 'context' => 'provider-company', 'category' => 'communication', 'permission' => 'channels.create', 'primary' => true],
        ['key' => 'provider-company:provider-company.dashboard', 'label' => 'Dashboard', 'icon' => '🏗️', 'route' => 'provider-company.dashboard', 'context' => 'provider-company', 'category' => 'donnees', 'primary' => true],
    ],

    /*
     * Routes de tableau de bord qui ne sont PAS des modules : téléchargements et callbacks OAuth.
     * Elles n'ont pas de page, donc pas de case. Chaque ligne porte sa raison, pour qu'on ne
     * puisse pas y glisser un vrai module en douce.
     */
    'non_modules' => [
        'peer.owner.stay' => 'Éditeur d’une annonce : on y entre depuis « Mes logements », jamais depuis un menu',
        'peer.sejour' => 'Page publique d’une annonce : on y entre depuis le catalogue, jamais depuis un menu',
        'admin.kyc.center' => 'Fusionnée dans le centre onboarding ; l’URL survit en redirection',
        'admin.onboarding.documents' => 'Fusionnée dans le centre onboarding ; l’URL survit en redirection',
        'admin.onboarding.providers' => 'Fusionnée dans le centre onboarding ; l’URL survit en redirection',
        'admin.providers.registrations' => 'Fusionnée dans le centre onboarding ; l’URL survit en redirection',
        'admin.countries' => 'Fusionnée dans le catalogue ; l’URL survit en redirection',
        'admin.international' => 'Fusionnée dans le catalogue ; l’URL survit en redirection',
        'admin.services' => 'Fusionnée dans le catalogue ; l’URL survit en redirection',
        'admin.trades' => 'Fusionnée dans le catalogue ; l’URL survit en redirection',
        'admin.zones' => 'Fusionnée dans le catalogue ; l’URL survit en redirection',
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

        // Sous-page d'`admin.automation`, atteinte depuis sa liste : une case propre la ferait
        // apparaître comme un module distinct, sous la même capacité que celui qui l'ouvre.
        'admin.automation.regles.creer' => 'Sous-page du centre d’automatisation, pas un module',
        'admin.automation.reglages' => 'Sous-page du centre d’automatisation, pas un module',
        'admin.automation.propositions' => 'Sous-page du centre d’automatisation, pas un module',
    ],
];
