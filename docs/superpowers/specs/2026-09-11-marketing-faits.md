# Ce que Brio fait reellement — la matiere du discours commercial

Audit du 2026-09-11 : neuf agents, 1086 lectures de fichiers, preuve fichier:ligne exigee.
Rien ne s'ecrit en vitrine qui ne figure pas ici.

---

## Resume

Brio est une place de marché de services à la demande dont le catalogue entier — pays, zones, secteurs, métiers, questionnaires, options et prix par zone — est une donnée administrable depuis /admin/catalogue, jamais du code : le parcours public /commander descend SECTEUR → MÉTIER → QUESTIONS et rend un prix avant toute demande d'identité. Elle sert quatre familles de comptes (particulier, entreprise cliente, prestataire indépendant, société prestataire), chacune avec son espace complet, et pousse trois modes de commande : rendez-vous planifié, intervention immédiate, chantier multi-métiers séquencé. L'argent suit un modèle d'empreinte bancaire à capture manuelle — rien n'est débité avant la clôture de la mission — monté en charge à destination Stripe Connect, avec une commission de 15 % (plancher 2 €) prélevée sur le montant et non ajoutée au prix. Elle héberge en plus deux modules de location qu'aucun concurrent de services à domicile ne porte : « Nos locations » (la plateforme loue sa flotte) et la location entre membres (véhicules et logements entre comptes). Les données de référence livrées n'ouvrent qu'UN pays à la réservation, la Belgique, avec 6 zones, 6 secteurs et 16 métiers — et une part importante des intégrations tierces (KYC, assurance, sanctions, SMS, push, change, appels masqués, diffusion temps réel) est livrée en fournisseur simulé par défaut.

## Les piliers de valeur

### Le catalogue est une donnée, pas du code : ouvrir une ville ou un métier est une opération d'exploitation

*les-deux — rare-sur-le-marche*

Un secteur, un métier, une question, une option, une condition d'affichage, un tarif par zone, un multiplicateur, un barème kilométrique : tout se crée et se modifie depuis la console d'administration, sans déploiement. Une place de marché de services à domicile classique porte ses métiers en dur et ouvre une ville par une livraison logicielle.

- `routes/admin.php:702-711 — trois écrans routés Pays → Zones → Secteurs & métiers, plus le constructeur de questionnaire`
- `app/Livewire/Admin/OrderEngine/CountryCenter.php:79-126, ZoneCenter.php:120-150, CatalogCenter.php:91-119,213-267,393-455`
- `database/migrations/2026_05_27_000001_create_trade_zone_pricing_table.php:43 — clef unique (trade_id, service_zone_id) : l'activation ET le prix sont la même ligne`
- `app/Livewire/Admin/OrderEngine/QuestionnaireBuilder.php:228-307,532 — 14 types de question, conditions d'affichage, options tarifées, révisions publiées`
- `app/Support/Domain/QuestionType.php:39-45 et PricingUnit.php:19-22`

### Un compte, deux marchés de location en plus des services : la flotte de la plateforme et les biens des membres

*les-deux — rare-sur-le-marche*

Le même compte qui commande un peintre loue un utilitaire à la plateforme ou un studio à un autre membre. Aucun concurrent de services à domicile (Helpling, TaskRabbit, StarOfService, Wecasa, ListMinut) ne porte de place de marché de location de biens. Les deux modules sont techniquement étanches : tables, commissions et parcours distincts.

- `routes/web.php:147-151 — « Nos locations » : catalogue, page véhicule, récapitulatif, agences de retrait`
- `database/migrations/2026_09_02_090000_create_le_module_nos_locations.php:14,43,107,126 — 4 tables propres`
- `routes/peer-rental.php:28-61 (vérifié) — /louer, /louer/{vehicle}, /sejours, /sejour/{stay} publics ; espace de gestion sous /dashboard/location-entre-membres ; centre d'arbitrage admin`
- `database/migrations/2026_09_28_090000_socle_de_la_location_entre_membres.php — 12 tables dédiées, véhicules ET logements`
- `config/peer_rental.php:18 — commission 25 %, distincte des 15 % des prestations`
- `app/Services/PeerRental/PeerReturnCharges.php, PeerReviewService.php:76-116 — barèmes d'annulation, codes de remise/retour, états des lieux photo, frais de retard et carburant, avis en aveugle`

### Des espaces société complets DES DEUX CÔTÉS du marché

*les-deux — rare-sur-le-marche*

Un réseau de 40 agences commande en une saisie avec budgets par site et séparation des tâches ; une société de nettoyage de 30 salariés pilote son exploitation ici. Les places de marché grand public n'ont ni l'un ni l'autre.

- `routes/company-dashboards.php:39-89 — 55 routes pour l'entreprise cliente : locaux, budgets par site, pilotage, contrats, facturation, litiges, import CSV, demande multi-locaux`
- `routes/company-dashboards.php:92-155 — 15 écrans pour la société prestataire : dispatch interne, équipes terrain, planning et absences, heures et rentabilité, consommables, devis, recrutement, qualité, matériel`
- `app/Enums/OrganizationRole.php:70-101 — 6 sous-rôles clients, 11 sous-rôles prestataires`
- `app/Services/PermissionService.php:265-297 — la société redéfinit elle-même sa matrice rôle → permission`
- `app/Services/Enterprise/MemberSiteAccessService.php:16-82 — un membre restreint à une liste de locaux, avec filtrage des factures`
- `app/Services/Enterprise/SiteBudgetService.php:68-150 — budget mensuel par local, seuil d'alerte réglable`

### Le chantier multi-métiers : plusieurs métiers, une commande, un paiement, un ordre tenu

*client — rare-sur-le-marche*

Une rénovation se commande en une fois, les métiers s'enchaînent dans le bon ordre avec le temps de séchage réservé, et chaque intervenant reste une mission autonome avec son prestataire et son paiement.

- `app/Services/OrderEngine/BundleComposer.php:23-60,96-222 — rang, délai d'attente après le précédent, frise, refus motivé d'un réordonnancement qui casse une dépendance`
- `database/migrations/2026_08_01_000100_create_sectors_and_extend_trades.php:79-96 — trade_bundle_suggestions, associations configurables avec délai de séchage`
- `database/seeders/OrderEngineCatalogSeeder.php:165-176 — 7 associations semées (peinture → nettoyage fin de chantier à 720 min, électricité → peinture à 2880 min)`
- `app/Services/OrderEngine/PricingEngine.php:169-205 (vérifié) — la remise multi-services est une LIGNE du devis, pas une soustraction silencieuse`
- `app/Services/OrderEngine/OrderConfirmationService.php:60-68,256-258 — une réservation PAR métier, envoyées une par une au dispatch`

### La preuve d'exécution est un faisceau technique, pas une parole

*les-deux — rare-sur-le-marche*

Code détenu par le client, croisement de position, photo empreintée cryptographiquement, chronologie horodatée, signature figée. Le VTC a le code ; la marketplace de services à domicile n'a en général qu'un bouton « terminé ».

- `app/Services/Missions/MissionVerificationCodeService.php:23-31,60-79 (vérifié) — code à 6 chiffres détenu par le CLIENT, stocké haché, 20 min de validité, 5 tentatives avant invalidation`
- `app/Services/TripTracking/PresenceCodeService.php:16-19 (vérifié) — second dispositif de confirmation de présence à l'arrivée : 10 min, 5 tentatives`
- `app/Services/Geo/OnSiteVerifier.php:37-105 — rayon toléré 250 m, élargi au plus à 500 m si l'appareil annonce un relevé imprécis ; position simulée rejetée`
- `app/Services/Missions/OnSite/MissionMediaService.php:59,67,69-92 — empreinte SHA-256, horodatage, position et précision sur chaque photo ; disque privé, URL signée ; seul un prestataire AFFECTÉ peut déposer`
- `app/Services/Missions/OnSite/MissionCheckInService.php:63-105 — client absent : preuve d'arrivée photographique, refusée si le client est en réalité présent`
- `app/Services/ContractsV2/ContractService.php:102-125 — signature du rapport sur place : empreinte du texte signé, IP hachée, navigateur, version des CGU, horodatage`

### L'argent du client n'est pas encaissé : il est bloqué, et prélevé après

*les-deux — solide*

« Paiement après la prestation » est vrai au sens strict et c'est l'argument le plus vendeur de la plateforme — à condition de ne jamais dire « débit au démarrage » comme le fait /aide aujourd'hui.

- `app/Services/Payments/MissionPaymentService.php:57 — capture_method « manual »`
- `app/Services/Missions/MissionLifecycleService.php:479-530 (vérifié) — la clôture déclenche dans l'ordre : capture, règlement du temps supplémentaire, calcul de commission, création du versement`
- `app/Services/Payments/MissionPaymentService.php:58-63 — transfer_data.destination sur le compte Connect du prestataire, commission en application_fee_amount : à la capture l'argent est déjà chez lui`
- `app/Services/OrderEngine/OrderPaymentPlanner.php:25-77 et config/order_engine.php:213-215 — seconde formule : acompte 30 % à partir de 500 €, solde bloqué, les deux montants affichés avant le clic`
- `app/Services/Payments/MissionPaymentService.php:201-256 — annulation avec frais : seuls les frais sont capturés, sinon l'empreinte est libérée au lieu d'être capturée à zéro`
- `app/Http/Controllers/Webhooks/StripeConnectWebhookController.php:33-60 — signature vérifiée, identifiant d'événement en clé unique : un webhook rejoué n'est pas compté deux fois`

### Le prix arrive avant l'identité, et il est explicable ligne par ligne

*client — solide*

Le visiteur compose, voit le montant bouger question par question, et n'est jamais engagé sur le haut d'une estimation qu'il n'a pas validée. Plusieurs concurrents affichent un prix sans compte, mais peu figent le devis dans les mots que le client a lus.

- `routes/web.php:160-176 — /commander et le récapitulatif sont publics, aucun middleware auth`
- `tests/Feature/OrderEngine/OrderJourneyTest.php — 21 tests verts, dont « a visitor gets a price without ever being asked who they are »`
- `app/Services/OrderEngine/PricingEngine.php:29 et app/Livewire/OrderEngine/OrderJourney.php:699-713 — recalcul à chaque réponse, détail ligne par ligne`
- `app/Services/OrderEngine/PricingEngine.php:351-395 — une réponse manquante donne une fourchette bornée par l'option la moins chère et la plus chère, pas un blocage`
- `app/Services/OrderEngine/OrderConfirmationService.php:309-315 — la réservation est engagée sur le BAS de la fourchette, jamais sur le haut`
- `app/Services/OrderEngine/OrderDraftManager.php:162-186 — le devis est figé avec la réservation : libellé de chaque question tel que le client l'a lu, dans SA langue`

### La facturation au temps passé, bornée des deux côtés et réglée après coup

*les-deux — rare-sur-le-marche*

Le compteur est ancré sur l'heure du serveur, la règle est la même des deux côtés, le dépassement est plafonné à la durée achetée et prolonger coûte moins cher que laisser filer. Mais c'est aujourd'hui une CAPACITÉ : aucun métier n'est livré avec le drapeau activé.

- `app/Services/Missions/HourlyMissionClock.php:24-121 — horloge SERVEUR : temps écoulé, échéance, dépassement, montant à l'instant`
- `config/order_engine.php:54-57 — franchise de 15 min, dépassement arrondi au quart d'heure entamé, multiplicateur 1,30, plafonné à la durée achetée`
- `app/Support/Pricing/HourlyRuleText.php:12-27 et resources/views/livewire/order-engine/partials/hours.blade.php:10-16,70 — la règle du dépassement est annoncée AU MOMENT du choix de la durée, pas dans des CGU`
- `app/Services/Missions/HourlyExtensionService.php:30-106 — prolongation en cours de mission au tarif NORMAL, 3 durées déjà chiffrées, refus motivé`
- `app/Services/Missions/HourlySettlementService.php:24-104,107-160 — constat figé en base puis encaissement hors session ; un règlement déjà encaissé n'est jamais rejoué`

### La règle de disponibilité est explicite et le refus est dit en toutes lettres

*les-deux — solide*

Le client apprend avant de s'engager qu'on n'intervient pas chez lui, au lieu d'attendre un professionnel qui ne viendra pas ; le prestataire ne s'inscrit pas sur un métier que personne ne peut commander.

- `database/migrations/2026_05_27_000001_create_trade_zone_pricing_table.php:43 — un métier est vendable à un endroit si et seulement s'il existe une ligne trade_zone_pricing ACTIVE pour (métier, zone) ; absence de ligne = fermé`
- `app/Services/OrderEngine/OrderConfirmationService.php:136-181 — 5 motifs de refus explicites : pas de service, pas d'adresse, zone non desservie, métier fermé dans la zone, trajet sans point d'arrivée`
- `app/Services/Dispatch/CandidateFinder.php:177-180 — le moteur de répartition s'arrête avant de chercher un candidat sur un couple fermé`
- `app/Services/OrderEngine/ZonePricingResolver.php:123-175 — repli sur la zone de couverture nationale avant refus`
- `app/Services/Catalog/RegistrationOptionsService.php:33-74 — à l'inscription, un prestataire ne voit que les métiers réellement ouverts quelque part`

### Le prestataire est instrumenté sur le terrain, et son argent est tracé à la ligne

*prestataire — solide*

Le fichier pour le comptable sort d'un clic, le taux d'acceptation lui est rendu au lieu d'être caché, et un remboursement partiel ne lui coûte que sa quote-part.

- `routes/api/provider.php:300-381,436-448 — plus de 25 points d'entrée de terrain : départ, arrivée, démarrage, clôture, photos, incidents, checklist, consommables, fiche d'accès, signature client, annonce de retard, supplément constaté, révision de devis, demande de renfort`
- `database/migrations/2026_05_18_100002_create_provider_wallet_transactions_table.php:11-63 — chaque mouvement est daté, typé, dirigé, avec solde après opération et clé d'idempotence unique (8 types, 5 états)`
- `app/Livewire/Provider/ProviderEarningsDashboard.php:63-104 et app/Services/Provider/TaxSummaryService.php — détail par jour/semaine/mois/année, meilleurs métiers, pourboires, export fiscal CSV annuel`
- `app/Services/Provider/OfferStatsService.php:16-57 — taux d'acceptation, médiane de réponse, motifs de refus rendus au prestataire`
- `app/Console/Commands/ProcessProviderPayouts.php:33-36,154-157 — versement gelé tant qu'une réclamation est ouverte`
- `app/Services/Payments/Webhooks/StripeWebhookHandlers.php:172-190 — reprise sur remboursement PROPORTIONNELLE, jamais totale`

### Le contrôle facial du prestataire bloque réellement le dispatch

*client — rare-sur-le-marche*

La fraude visée est le compte prêté à un tiers, et le blocage est effectif : le candidat disparaît de la requête de répartition. À nuancer deux fois : ne vise par défaut que 2 métiers (garde d'enfants, sécurité), et le comparateur facial est en fournisseur simulé.

- `app/Services/FaceCheck/FaceCheckDispatchFilter.php:22-36 et app/Services/Dispatch/CandidateFinder.php:216 — la requête candidate exclut tout profil non enrôlé, bloqué, ou dont le consentement a été retiré, quand le métier l'exige`
- `config/face_check.php:81-82,90,99,103,110,127,129 — cadence tirée au sort entre 24 h et 72 h, contrôle ouvert 15 min, seuil 75/100, appariement pièce d'identité 65/100, vivacité obligatoire, 3 essais, blocage dur après 2 échecs d'affilée`
- `config/face_check.php:167,181 et app/Services/FaceCheck/FaceCheckService.php:52-56,108-128 — traité en donnée d'article 9 : consentement versionné, retrait révoquant le visage, selfies purgés à 30 jours`
- `app/Console/Kernel.php:52 — la purge est planifiée`

### Le moteur de répartition est nommé, borné et pondéré

*prestataire — attendu-du-marche*

Ce qui fait recevoir plus de missions est nommé et pondéré, et l'offre est exclusive au lieu d'être une course au clic. C'est bon, c'est vérifiable, mais c'est le standard d'une place de marché à la demande : à vendre comme une garantie de sérieux, pas comme une exclusivité.

- `config/dispatch.php:22,24-35,51,123 — offre immédiate exclusive 20 s (30 s pour toiture, électricité, plomberie, déménagement) ; rendez-vous planifié 30 min, escalade jusqu'à 5 refus`
- `config/dispatch.php:80-82,97,113 — vagues concentriques 5 km, +5 km, maximum 20 km ; au rayon maximal diffusion à 20 candidats ; abandon à 300 s`
- `config/matching.php:22-32 — score à 9 dimensions : note 25 %, acceptation 15 %, proximité 15 %, complétion 10 %, charge 10 %, affinité client 10 %, réponse 5 %, spécialité 5 %, équilibrage 5 %`
- `app/Services/Dispatch/CandidateFinder.php:176-231 — 7 conditions cumulées d'éligibilité, dont l'exclusivité : personne ne reçoit deux offres à la fois`
- `app/Services/Dispatch/CandidateFinder.php:54-62 et config/dispatch.php:67 — trois conditions pour l'immédiat : statut online, position connue, battement de moins de 5 minutes`

### Le RGPD en libre-service, avec un effacement qui va chercher les modules secondaires

*les-deux — solide*

Le client récupère tout ce que la plateforme sait de lui en un fichier, sans écrire à personne. Attention au mot : c'est une anonymisation, pas une suppression.

- `app/Services/Gdpr/DataExportService.php:66-86 — 12 rubriques exportées, articles 15 et 20 cités, depuis le web ET l'API mobile`
- `config/gdpr.php:11 et app/Console/Kernel.php:63 — 30 jours de rétractation, exécution planifiée chaque jour à 04 h 30, annulable pendant le délai`
- `app/Services/Gdpr/DataErasureService.php:99-360 — anonymisation jusque dans les adresses de réservation, les textes libres d'avis et de litiges, les charges de notification, les conversations et les selfies de contrôle facial`
- `config/gdpr.php:39-46 — durées de conservation par nature : activité 730 j, notifications 365 j, sessions 90 j, pièces comptables 3650 j`

### Deux applications natives qui couvrent le dossier et l'argent, pas seulement les missions

*les-deux — attendu-du-marche*

Un indépendant sans ordinateur fait tout depuis son téléphone, et le prix calculé sur le téléphone est celui du web à la virgule près. Mais aucune des deux applications n'est publiée sur un magasin : les fichiers de soumission portent encore des valeurs de gabarit.

- `mobile/client/app.json et mobile/provider/app.json — deux applications Expo/React Native distinctes, 42 écrans côté client, 40 côté prestataire`
- `mobile/provider/src/navigation/TabNavigator.tsx:52-99,113 — 4 onglets, modale d'offre et compte à rebours montés AU-DESSUS de la navigation : l'offre s'affiche quel que soit l'écran ouvert`
- `mobile/provider/src/navigation/RootNavigator.tsx:536-673 — métiers et zones, disponibilités, KYC, contrôle facial, ouverture Stripe Connect, badges, avis, litiges, portefeuille avec retrait, notifications, sécurité`
- `mobile/client/src/screens/components/HomeActionsSheet.tsx:44-75 — l'application cliente n'a pas de second formulaire de réservation : ses trois entrées ouvrent le MÊME parcours web avec le mode dans l'URL`
- `mobile/client/src/screens/components/PresenceCodeCard.tsx:62-101 — le code s'affiche en QR ET en six chiffres ; l'application prestataire le scanne`

### Le parcours du prestataire s'ouvre seul, sans délai administratif imposé

*prestataire — solide*

On ne demande au prestataire que ce que son métier justifie, la liste est connue d'avance, et le dernier document déposé peut ouvrir le compte dans la seconde. Corollaire : aucune durée ne peut être promise, ni 24 h ni 48 h.

- `app/Services/Onboarding/ProviderAutoApproval.php:33-66,110-140 — dès qu'aucun bloquant ne reste, le profil passe en actif sans intervention humaine ; AUCUN délai n'est codé`
- `database/seeders/ProviderOnboardingJourneySeeder.php:49-133 — 6 étapes toutes obligatoires, avancement affiché`
- `config/onboarding_documents.php:23-73 et app/Services/Onboarding/ProviderDocumentRequirements.php — les pièces exigées sont DÉDUITES des métiers déclarés : RC pro et certification lues sur le métier, casier réservé à 2 métiers, permis et assurance véhicule pour tout métier de trajet`
- `routes/employe.php:52-56,63-65,93-95,105-107,180-190 et app/Http/Middleware/EnsureProviderIsApproved.php:26-35 — 7 routes restent ouvertes à un compte non approuvé : exactement celles qui permettent de compléter le dossier`

## Chiffres verifies, utilisables en communication (55)

**16 métiers** — Nombre de métiers distincts semés par le référentiel de production. VÉRIFIÉ MOI-MÊME : 12 dans TradeSeeder + 3 créés par OrderEngineCatalogSeeder (nettoyage fin de chantier, vitrerie, élagage) + 1 par CourseCatalogSeeder (course-vtc). Les autres slugs d'OrderEngineCatalogSeeder (peinture, plumbing, electrical, roofing, nettoyage, jardinage) réécrivent des métiers déjà existants.

> database/seeders/TradeSeeder.php:72,89,106,123,140,157,174,191,208,225,242,259 ; OrderEngineCatalogSeeder.php:549,698,817 ; CourseCatalogSeeder.php:46 ; chaîne d'appel ReferencePlatformSeeder.php:16-40

**6 secteurs** — Bâtiment & rénovation, Nettoyage, Espaces verts (OrderEngineCatalogSeeder), Mobilité (CourseCatalogSeeder), Services à la personne et Sécurité (TradeSectorLinkSeeder). ARBITRÉ : le secteur Mobilité EST créé, et CourseCatalogSeeder tourne AVANT TradeSectorLinkSeeder dans ReferencePlatformSeeder — donc le métier « déménagement » est bien rattaché.

> database/seeders/OrderEngineCatalogSeeder.php:197,211,224 ; CourseCatalogSeeder.php:32 (firstOrCreate slug=mobilite) ; TradeSectorLinkSeeder.php:39-53 ; ordre d'appel ReferencePlatformSeeder.php:34-38

**1 pays ouvert à la réservation : la Belgique** — Seul pays semé avec booking_enabled=true. Tout pays créé depuis l'administration naît fermé.

> database/seeders/BelgiumGeographySeeder.php:18-32 ; app/Livewire/Admin/OrderEngine/CountryCenter.php:120 force is_active=false et booking_enabled=false

**6 zones de service en Belgique** — Une couverture nationale plus cinq zones de ville (Bruxelles, Anvers, Gand, Liège, Namur), chacune avec son multiplicateur (1,00 à 1,10), son préavis (12 h à 24 h) et sa capacité quotidienne (25 à 200 missions).

> database/seeders/ZoneManagementSeeder.php:39-57 et :71-77

**3 régions, 11 provinces, 36 communes, 38 codes postaux** — Profondeur réelle de la géographie belge semée. Toute adresse non listée retombe sur la zone de couverture nationale.

> database/seeders/BelgiumGeographySeeder.php:123-127,133-145,150-186,190-262 ; repli app/Services/OrderEngine/ZonePricingResolver.php:165-175

**4 métiers sur 16 acceptent l'intervention immédiate** — Plomberie, électricité, nettoyage à domicile, course. VÉRIFIÉ : la colonne allows_asap a pour défaut FALSE en migration, et seuls quatre seeders l'activent. Double condition : le métier ET la ligne de zone. Un auditeur annonçait 3 (il oubliait la course).

> database/migrations/2026_08_01_000100_create_sectors_and_extend_trades.php:71 (default false) ; OrderEngineCatalogSeeder.php:358,438,632 ; CourseCatalogSeeder.php (allows_asap=true) ; garde app/Models/Trade.php:135-155 + ZonePricingResolver.php:41-49

**3 modes de commande** — Rendez-vous planifié, intervention immédiate, chantier multi-services. Tous trois offerts à l'écran.

> app/Support/Domain/OrderMode.php:7-21 ; resources/views/livewire/order-engine/partials/mode-cards.blade.php:23,30,37

**14 types de question, 5 unités de tarification** — Y compris le compteur, la surface avec assistant longueur × largeur, la photo, l'adresse et le point sur la carte ; une question peut être conditionnée par une réponse précédente.

> app/Support/Domain/QuestionType.php:39-45 ; app/Support/Domain/PricingUnit.php:19-22 ; OrderEngineCatalogSeeder.php:317-320

**50 questions, 76 options, 10 étapes semées** — Volume du questionnaire livré avec le référentiel.

> vérifié en base : Question::count()=50, QuestionOption::count()=76, QuestionStep::count()=10

**1 seul métier de trajet : la Course** — Un métier est un trajet s'il pose UNE question de lieu « départ » ET UNE question de lieu « arrivée » — règle dérivée du parcours, aucun drapeau à cocher.

> app/Support/Domain/TradeRouteRules.php:12-15,31-44 ; CourseCatalogSeeder.php:69-104 (LocationRole::PICKUP / DROPOFF)

**Course : 2,50 € de prise en charge, 1,40 €/km au-delà du 1er km, 0,30 €/min** — Barème kilométrique semé, réglable par métier ET par zone.

> database/seeders/CourseCatalogSeeder.php:110-131 (vérifié) ; colonnes migration 2026_05_27_000001:57-80

**Remise multi-services : −5 % à 2 services, −8 % à 3, −12 % à 4 et plus** — Barème du parcours /commander. VÉRIFIÉ : c'est bien ce barème que lit PricingEngine, et la remise est écrite comme une LIGNE du devis. Ne s'applique QU'EN mode « chantier groupé ».

> config/order_engine.php:84-88 ; app/Services/OrderEngine/PricingEngine.php:169-205 (bundleDiscountPercent lit order_engine.bundle_discount_percent)

**Service immédiat : ×1,30 sur le total, fourchette élargie de 15 %** — Majoration annoncée avant confirmation ; l'estimation est volontairement élargie parce que le questionnaire est raccourci.

> config/order_engine.php:12 et :110 ; app/Services/OrderEngine/PricingEngine.php:101-116

**Acompte 30 % à partir de 500 €** — Seconde formule de règlement, proposée uniquement au-dessus du seuil et jamais sur une prestation « sur devis ». Les deux montants sont affichés avant le clic.

> config/order_engine.php:213-215 ; app/Services/OrderEngine/OrderPaymentPlanner.php:25-77 ; vue order-confirmation.blade.php:202-216

**Commission 15 %, plancher 2 € par prestation** — Taux unique : le taux négocié par prestataire existe en base mais son drapeau est désactivé. La commission est prélevée SUR le montant payé, elle n'est pas ajoutée au prix du client. VÉRIFIÉ : le dépôt chiffre lui-même que le plancher prélève 2 € sur une course de 4,81 €, soit 41 %.

> config/brio.php:15, :18-27 (commentaire), :34 (use_negotiated_commission=false) ; app/Services/Payments/CommissionService.php:91-103 ; application_fee_amount MissionPaymentService.php:58

**Commission de la location entre membres : 25 %** — Taux distinct de celui des prestations.

> config/peer_rental.php:18

**Pourboire : suggestions 10/15/20 %, de 1 € à 500 €, fenêtre de 7 jours, retenue plateforme 0 %** — Au réglage livré, la plateforme ne retient rien sur le pourboire. C'est un réglage par variable d'environnement, pas un engagement gravé.

> config/tips.php:7-29 ; app/Services/Commission/ResolveurDeCommission.php:161

**Facturation à l'heure : 1 h à 12 h par pas de 0,5 h ; franchise 15 min ; dépassement ×1,30 arrondi au quart d'heure entamé, plafonné à la durée achetée** — Le sélecteur arrive AVANT les questions du métier et la règle du dépassement est annoncée là où le client décide.

> config/order_engine.php:41-57 ; app/Services/Missions/HourlyMissionClock.php:100-160 ; app/Support/Pricing/HourlyRuleText.php:12-27

**Annulation client : > 48 h gratuit, 48-24 h 25 %, 24-2 h 50 %, < 2 h 100 %** — Le barème réellement semé. Plus une pénalité de 5 % si le prestataire est déjà en route. Des motifs d'exemption existent (force majeure, urgence médicale plafonnée à 2 fois par 30 jours, prestataire absent).

> database/seeders/CancellationPoliciesSeeder.php:26-29 et :31-35 ; config/cancellation_v2.php:18

**Annulation d'une recherche immédiate : gratuite 3 minutes, puis 5 €** — Les deux montants sont annoncés avant le clic.

> config/order_engine.php:198-205 ; app/Services/OrderEngine/AsapDispatchService.php:85-106

**Offre immédiate : 20 s de réponse (30 s pour toiture, électricité, plomberie, déménagement) — rendez-vous planifié : 30 minutes** — Deux régimes distincts. Confondre les deux fait passer la plateforme pour plus brutale qu'elle n'est.

> config/dispatch.php:22, :24-35, :51 ; escalade jusqu'à 5 refus config/dispatch.php:123

**Recherche immédiate : 5 km, +5 km par vague, 20 km maximum, 20 candidats en diffusion finale, abandon à 300 s** — La proximité passe avant la note sur l'immédiat : le score ne départage qu'à distance égale.

> config/dispatch.php:80-82,97,113 ; app/Services/Dispatch/CandidateFinder.php:54-83

**Score de matching : 9 dimensions pondérées (note 25 %, acceptation 15 %, proximité 15 %, complétion 10 %, charge 10 %, affinité 10 %, réponse 5 %, spécialité 5 %, équilibrage 5 %)** — Ce qui apporte réellement plus de missions. Aucune de ces neuf dimensions ne lit les badges.

> config/matching.php:22-32 ; app/Services/Matching/MatchingScoreEngine.php:16-52

**Présence en ligne : 3 conditions — statut online, position connue, battement de moins de 5 minutes** — Le drapeau « en ligne » seul ne suffit pas à recevoir une course.

> config/dispatch.php:67 ; app/Services/Dispatch/CandidateFinder.php:54-62 ; app/Livewire/Provider/MaPresence.php:74-100

**Code de mission : 6 chiffres, 20 minutes, 5 tentatives, stocké haché** — VÉRIFIÉ dans le code. Code détenu par le CLIENT, consommé par le PRESTATAIRE, pour le démarrage et pour la fin.

> app/Services/Missions/MissionVerificationCodeService.php:23-31 (expires_at now()->addMinutes(20)), :60-79 (verification_max_attempts, défaut 5)

**Code de confirmation de présence : 6 chiffres, 10 minutes, 5 tentatives** — SECOND dispositif, distinct du précédent : il confirme l'ARRIVÉE sur place. Les deux existent réellement ; ne pas les confondre.

> app/Services/TripTracking/PresenceCodeService.php:16 (TTL_MINUTES=10) et :19 (MAX_ATTEMPTS=5)

**Contrôle de position : rayon toléré 250 m, plafonné à 500 m sur relevé imprécis ; géofence d'arrivée à 150 m** — Une position simulée par le téléphone est rejetée. Mais le contrôle se laisse sauter sans coordonnées de destination.

> config/trip_tracking.php:17-18,46,58,78,88 ; app/Services/Geo/OnSiteVerifier.php:37-105

**Photo de terrain : empreinte SHA-256, 12 Mo maximum, 5 formats, disque privé et URL signée** — Horodatage, position et précision du relevé sont écrits avec la photo ; seul un prestataire AFFECTÉ peut en déposer une.

> app/Services/Missions/OnSite/MissionMediaService.php:22-30,59,67,69-92 ; app/Models/MissionMedia.php:33-46

**Lien de suivi partagé : signé, valable 12 heures, sans compte** — Il ne montre qu'une position, une heure et un état — ni montant, ni adresse exacte. Proposé depuis deux endroits seulement (carte plein écran web, application).

> routes/web.php:186-201 ; app/Services/Client/SharedTrackingService.php:15-22

**Suivi par sondage : position toutes les 15 s, trace toutes les 30 s** — C'est le mécanisme réel quand aucun serveur de diffusion n'est branché — et config/broadcasting.php:19 retombe sur null par défaut.

> resources/views/livewire/client/client-live-tracking-map.blade.php:133-155 ; mobile/client/src/tracking/hooks.ts:91,123,152

**Litiges : première réponse 4 h (urgent), 12 h (haute), 24 h (normale), 48 h (basse) ; escalade à 24 h puis 48 h, 3 niveaux ; 10 types de résolution** — L'escalade est réellement appliquée par une tâche horaire. L'auto-résolution est bornée à 2 catégories sous un plafond de 200.

> config/disputes.php:11-39 ; app/Console/Kernel.php:96 ; app/Console/Commands/Disputes/ProcessDisputeSlaCommand.php:11-15 ; database/migrations/2026_05_18_120003:17-28

**Avis : 4 sous-notes (ponctualité, qualité, communication, rapport qualité-prix), édition 24 h, publication en double aveugle** — Le double aveugle est réel. La publication forcée à 14 jours ne l'est PAS : la méthode n'a aucun appelant en production.

> app/Services/Rating/RatingService.php:18-20,178-198 ; VÉRIFIÉ : publishExpiredPending() (RatingService.php:106) n'est appelé que par tests/Feature/Rating/RatingServiceTest.php:148 — aucune ligne d'app/Console/Kernel.php ne le planifie

**Contrôle facial : cadence 24-72 h, fenêtre 15 min, seuil 75/100, appariement pièce d'identité 65/100, 3 essais, blocage après 2 échecs d'affilée, selfies purgés à 30 jours** — Vise par défaut 2 métiers : garde d'enfants et sécurité.

> config/face_check.php:47,81-82,90,99,103,110,127,129,167,181 ; app/Console/Kernel.php:52

**6 étapes d'onboarding prestataire, toutes obligatoires ; 8 types de pièces ; casier exigé pour 2 métiers** — Les pièces sont déduites des métiers déclarés. Aucun délai d'approbation n'est codé : l'ouverture est automatique dès que le dossier est complet.

> database/seeders/ProviderOnboardingJourneySeeder.php:49-133 ; config/onboarding_documents.php:23-73 ; app/Services/Onboarding/ProviderAutoApproval.php:33-66

**Virement express : 1,5 % de frais, minimum 1 €, montant minimum 20 €, net affiché en euros avant le bouton** — Le seul geste de retrait à part ; le retrait ordinaire (minimum 10 €) ne crée qu'une demande et une écriture, sans appel de virement.

> app/Services/Payments/ExpressPayoutService.php:15,18,21,32-46 ; ProviderWalletService.php:18,306-345

**21 périmètres d'API, expiration 365 jours, grâce de rotation 24 h, 120 requêtes/minute** — VÉRIFIÉ : 21 en configuration ET 21 dans le seeder. La vitrine annonce 18.

> config/api_tokens_v2.php:55-71 (comptés : 21) ; database/seeders/ApiTokenScopesSeeder.php:12-33 (21 entrées)

**Webhooks sortants : 19 événements, signature HMAC-SHA256, tolérance 300 s, 6 tentatives (30 s à 6 h), suspension après 25 échecs consécutifs** — Le moteur est complet et les événements sont réellement émis depuis 8 points du domaine. Mais l'enregistrement d'un point de livraison est réservé à l'administration de la plateforme.

> config/webhooks_v2.php:21-84 ; app/Services/WebhooksV2/WebhookSigner.php:11-40 ; routes/api/admin.php:171

**6 langues d'interface actives (fr, nl, en, es, it, de), 387 clés par langue** — Ce sont de vraies traductions : 7 à 21 valeurs seulement sont identiques au français sur 387. Le portugais est déclaré mais désactivé.

> config/i18n.php:19-81 ; lang/fr.json, nl.json, en.json, es.json, it.json, de.json

**110 traductions de catalogue, champ `name` UNIQUEMENT** — 6 secteurs × 5 langues + 16 métiers × 5 langues. Les accroches de secteur et les descriptions de métier restent en français partout.

> database/seeders/CatalogueTraductionsSeeder.php:14-171 ; vérifié en base : catalog_translations groupé par field = { name: 110 }

**Comptabilité en partie double : minimum 2 lignes par transaction, 5 codes journaux, 5 formats d'export (CSV, FEC, Sage, QuickBooks IIF, XML)** — Le moteur refuse une écriture déséquilibrée, une ligne à la fois débitrice et créditrice, un compte inconnu, une écriture en période close. L'export FEC est branché et l'entreprise le télécharge elle-même.

> app/Services/AccountingV2/AccountingService.php:30-70,130-140 ; config/accounting_v2.php:128-142 ; app/Services/Enterprise/ClientAccountingExportService.php:17-95

**6 rôles clients et 11 rôles prestataires en société, matrice redéfinissable par la société** — La séparation des tâches existe nativement et se règle dans l'écran, pas par un ticket.

> app/Enums/OrganizationRole.php:70-101 ; app/Services/PermissionService.php:21-227,265-297 ; routes/company-dashboards.php:117

**Journal d'activité : 217 points d'écriture ; conservation d'audit 7 ans finance, 6 ans RGPD, 5 ans KYC** — Le registre réellement nourri est activity_logs. Le second registre, plus strict, n'est PAS alimenté par défaut (miroir désactivé).

> app/Support/ActivityLogger.php ; app/Livewire/Admin/AuditLogsCenter.php:54-82 ; config/audit.php:14 (miroir off), :36-53 (conservation)

**Webhooks Stripe : identifiant d'événement en clé unique, 5 tentatives avant file morte, relance horaire** — Un double débit par notification rejouée est structurellement écarté.

> app/Http/Controllers/Webhooks/StripeConnectWebhookController.php:33-60 ; database/migrations/2026_05_18_100001:14-32 ; app/Console/Kernel.php:109

**13 badges prestataire, 5 critères, 4 paliers, réévalués à la clôture d'une réservation** — Ils sont attribués et affichés — mais n'entrent dans AUCUNE des 9 dimensions du score de matching.

> database/seeders/ProviderBadgesSeeder.php:14-34 ; app/Services/Badges/ProviderBadgeEngine.php:17-46 ; app/Observers/BookingObserver.php:222-237

**Disponibilité par défaut du prestataire : 7 jours, 08:00-17:00, posée à l'inscription** — Il n'a rien à configurer pour commencer. Mais ces créneaux ne sont PAS lus par le moteur de répartition.

> app/Services/Availability/DefaultAvailabilityProvisioner.php:12-29 ; app/Actions/Fortify/CreateNewUser.php:216

**4 types de compte à l'inscription : particulier, entreprise cliente, prestataire indépendant, société de services** — Le chemin existe depuis le web, avec métiers et zones saisis dans le formulaire — la vitrine n'y mène jamais.

> resources/views/auth/register.blade.php:6,48-49,115-166 ; app/Actions/Fortify/CreateNewUser.php:82,103,113

**Chat client ↔ prestataire ouvert automatiquement à chaque réservation, avec 4 motifs masqués (e-mail, téléphone, IBAN, carte)** — Archivé à la clôture. Les échanges restent dans la plateforme, donc dans la trace en cas de litige.

> app/Observers/BookingObserver.php:127-133 ; app/Support/Chat/BookingChatAutoCreator.php:15-45 ; config/chat_v2.php:34-48

**5 canaux de notification : e-mail, SMS, push, in-app, webhook** — Chaque notification respecte les préférences du destinataire par catégorie. WhatsApp n'en fait PAS partie.

> config/notification_preferences.php:11,36-60

**45 services au catalogue hérité (ServiceCatalog), répartis sur les 12 métiers du socle** — Niveau HÉRITÉ, exploité par les écrans d'administration et l'API B2B — pas par le parcours /commander. Ne jamais l'additionner aux 16 métiers.

> database/seeders/ServiceCatalogSeeder.php:9,29,43-870 ; app/Livewire/Admin/CatalogueServices.php:334-397

**Contrat-cadre B2B : grille ligne à ligne par service, ou remise globale de 0 à 100 %, appliquée au prix de la réservation** — Le prix négocié s'applique tout seul, sans code promo. Mais seulement si le contrat désigne une société prestataire.

> app/Services/Contracts/ContractPricingResolver.php:12-40 ; ContractBookingHook.php:24-60 ; ContractResolver.php:22 (exige provider_organization_id)

**Délai de paiement B2B réglable de 0 à 365 jours ; échéance par défaut 30 jours pour une organisation, 14 sinon** — Le délai négocié se reporte automatiquement sur l'échéance des factures.

> app/Support/Livewire/Concerns/Admin/ManagesEntrepriseAccounts.php:155-220 ; app/Services/Finance/Concerns/SynchronizesFinanceDocuments.php:88-96

**Import de réservations en masse par CSV, plafonné à 2 Mo** — L'une des rares promesses B2B de l'accueil qui tienne : l'écran existe et est routé pour les sociétés clientes.

> app/Livewire/ClientCompany/BulkBookingImporter.php:29-66 ; routes/company-dashboards.php:85-88

**Ponctualité mesurée avec tolérance de 15 minutes, et les interventions sans relevé d'arrivée comptées À PART** — Le chiffre de ponctualité rendu à l'entreprise n'est pas gonflé : ce qui n'a pas été mesuré n'est pas rangé en « à l'heure ».

> app/Services/Enterprise/ServiceLevelService.php:13-95

**Parrainage : 10 € de crédit au parrain, 15 € de remise au filleul sur sa première réservation** — Double récompense, avec centre d'administration dédié.

> config/referral.php:9,23-32 ; routes/client.php:130 ; routes/admin.php:570

**Fidélité : 4 paliers, 10 points par euro, remise 0/5/10/15 %** — Les paliers sont semés. Le catalogue de récompenses échangeables, lui, ne l'est par aucun seeder.

> config/loyalty.php:30-47,55-103 ; database/seeders/LoyaltyTierSeeder.php:12

## Chiffres FAUX aujourd'hui en ligne (30)

- « 30+ métiers » — écrit en dur à cinq endroits (resources/views/home.blade.php:123 et :203, layouts/guest.blade.php:17 et :31, pages/services-index.blade.php:4 et :18). Le référentiel en sème SEIZE. C'est le mensonge le plus visible du site.
- « 20+ Métiers disponibles » (home.blade.php:406) — le même site s'auto-contredit : 30+ vingt lignes plus haut, 20+ dans le bandeau de chiffres. Le vrai nombre est 16.
- « plus de 20 métiers » dans mobile/client/STORE_METADATA.md:13 — même erreur, côté fiche de magasin.
- « 9 Pays supportés » (home.blade.php:410) — VÉRIFIÉ : un seul pays est semé avec booking_enabled=true, la Belgique. Le JSON-LD de toutes les pages du site déclare d'ailleurs areaServed = Belgium, sur la page même où le compteur affiche 9.
- La liste de neuf pays de la FAQ d'accueil, « Belgique, France, Pays-Bas, Allemagne, Espagne, Italie, Portugal, Luxembourg et Autriche » (home.blade.php:487) — aucun de ces huit autres pays n'a de géographie, de zone ni de ligne de tarif. Les neuf entrées de CountryConfigService ne sont qu'un socle de TVA/devise/fuseau.
- « 4.8 » de note moyenne — affiché quatre fois (home.blade.php:88, 98, 414). Aucune source, aucun calcul dans le dépôt.
- « 12 480 avis vérifiés » — affiché deux fois (home.blade.php:89, 99). Les chaînes « 12 480 » et « 12480 » n'existent nulle part ailleurs dans le dépôt. Un aggregateRating inventé dans du balisage structuré est en plus une sanction Google.
- « 18 scopes » pour l'API B2B (home.blade.php:388) — VÉRIFIÉ : il y en a 21, en configuration (config/api_tokens_v2.php:55-71) comme en base (ApiTokenScopesSeeder.php, 21 entrées).
- « 24/7 Support disponible » (home.blade.php:418) — rien dans le code ne le mesure ni ne l'engage. Les seuls délais qui existent sont les délais de première réponse aux litiges : 4 h à 48 h selon la priorité.
- « SLA 99.9% garanti » du plan Business (pages/pricing.blade.php:237) — aucune mesure de disponibilité, aucun engagement, nulle part dans app/ ni config/.
- « 20% sur chaque mission » de commission prestataire (app/Livewire/Public/HelpCenter.php:91) — VÉRIFIÉ : le taux est de 15 % (config/brio.php:15), plus un plancher de 2 € (config/brio.php:27). La vitrine sur-annonce de 5 points LE chiffre qu'un prestataire compare avant de s'inscrire.
- « Le débit effectif intervient au démarrage de la mission » (HelpCenter.php:57) — VÉRIFIÉ FAUX : la capture a lieu à la CLÔTURE (app/Services/Missions/MissionLifecycleService.php:479-483, capture_method « manual » MissionPaymentService.php:57). /aide détruit l'argument le plus vendeur du produit.
- « Annulation gratuite > 24h avant » (home.blade.php:488) et le barème de /aide (HelpCenter.php:40, « >24 h intégral ») — le barème réellement semé facture 25 % entre 48 h et 24 h. Trois pages publiques donnent aujourd'hui trois barèmes différents ; un client qui annule à 30 h se croit gratuit et paie 25 %.
- « Validation sous 48h » de l'inscription prestataire (HelpCenter.php:87) — aucun délai d'approbation n'est codé. L'ouverture est automatique dès que le dossier est complet : elle peut être instantanée comme rester bloquée indéfiniment.
- « généralement sous 2-5 jours ouvrés » pour le versement (HelpCenter.php:95) — rien dans le dépôt ne déclenche le versement bancaire. config/brio.php:37-48 dit lui-même que payout_delay_days est une ANNONCE, pas une planification.
- « Économie groupage : 176€ (−8%) » sur la carte de démonstration (home.blade.php:347) — VÉRIFIÉ : la carte montre QUATRE lots, et le barème réel du parcours donne −12 % à partir de 4 services (config/order_engine.php:84-88, lu par PricingEngine.php:169-205). Les quatre prix de la carte sont en plus entièrement écrits en dur. À noter : la valeur 10 % de config/bundles.php appartient à l'ANCIEN module de demande de devis, pas au parcours — la comparaison contre 10 % serait elle aussi fausse.
- « Assurance RC pro incluse sur chaque mission » (home.blade.php:167) et « Assurance incluse » dans la barre de confiance (home.blade.php:81) — contredit par la page tarifs du même site, où l'assurance n'apparaît que dans le plan Business à 29,99 € (config/premium.php:20, :34 false ; :48 true).
- « casier judiciaire vérifié » annoncé pour tous les prestataires (home.blade.php:167) — l'extrait n'est exigé que pour DEUX métiers, garde d'enfants et sécurité (config/onboarding_documents.php:23-28).
- « Identité contrôlée Onfido/Veriff » (home.blade.php:167) — le pilote KYC par défaut est « mock » (config/kyc.php:14). Pire : « veriff » et « sumsub » n'existent QUE dans la configuration, aucune classe ne les implémente (app/Providers/KycServiceProvider.php:37-41 lève une exception pour tout nom autre que mock ou onfido).
- « Peppol » (home.blade.php:381, pages/pricing.blade.php:229) — le mot n'existe que dans ces deux gabarits marketing. Aucune occurrence dans app/, config/, database/, routes/.
- « Factur-X XML CII embedded » (home.blade.php:382) — le constructeur existe mais n'a aucun appelant hors tests, et son propre retour dit que le PDF/A-3 à XML embarqué n'est pas fait (app/Services/Finance/EInvoicing/FacturXBuilder.php:103-104).
- « Essai gratuit 14 jours » (pages/pricing.blade.php:192, 352-353) — le bouton pointe sur l'inscription, aucune période d'essai n'est transmise au paiement, et le défaut de configuration est 0 jour (config/subscriptions_v2.php:35).
- Trois prix d'abonnement contradictoires selon la page : 9,99 € et 29,99 € sur /pricing, 29 € en dur sur /premium — la SEULE page reliée au paiement Stripe (app/Livewire/Client/PremiumOfferPage.php:20). Et /pricing lit config('premium.tiers') sans jamais l'utiliser : les trois prix y sont écrits en dur.
- « Prêt à réserver ? » / « A partir de X €/h » sur les pages de service — les pages publiques lisent trades.default_hourly_rate, colonne qu'AUCUN seeder n'écrit (vérifié : zéro occurrence dans database/seeders/). Les prix réels vivent dans base_price_cents, que ces pages ne lisent pas. Sur une base de référence, tout affiche « Devis sur demande ».
- « confirmation de fin de mission via QR code » (pages/service-trade.blade.php:258) — sur le web le client ne voit que SIX CHIFFRES ; le QR n'existe que dans l'application cliente. Et il y a deux dispositifs distincts : le code de mission (20 min, démarrage et fin) et le code de présence (10 min, arrivée).
- « recevez une fourchette de prix en 10 secondes » par photo (home.blade.php:31) — le délai HTTP configuré est de 30 s, l'écran est derrière role:client, et le service renvoie null en silence sans clé Anthropic.
- « Aucun concurrent ne fait ça » (home.blade.php:279) — rien dans le dépôt ne permet de l'établir. C'est la seule affirmation comparative du site.
- « satisfaction garantie » dans l'accroche du hero (home.blade.php:31) — aucune garantie de satisfaction ni remboursement inconditionnel n'existe dans le code. Il n'y a que des enquêtes NPS et une procédure de litige.
- « 6 langues » est juste pour l'interface produit, mais FAUX pour la vitrine : les cinq gabarits de vitrine ne contiennent AUCUN appel de traduction (grep __( = 0), et lang/*/vitrine.php n'existe qu'en fr, nl, en. Changer de langue ne change rien à la page d'accueil.
- « Téléchargez l'app Brio Provider » (home.blade.php:486) — les liens de magasin viennent de la table parametres et ne sont semés nulle part, donc le bloc de téléchargement DISPARAÎT. Et mobile/client/eas.json:27-29 porte encore PLACEHOLDER_APPLE_ID ; mobile/provider/eas.json n'a aucune section de soumission : les applications ne sont pas publiées.

## INTERDITS — ce qu'il ne faut jamais ecrire (81)

- NE PAS écrire « 30+ métiers » ni « 20+ ». Compter sur la table `trades` au moment du rendu, ou écrire 16. Aucun chiffre de métier en dur.
- NE PAS parler de PLUSIEURS PAYS, ni citer une liste de pays. Un seul est ouvert à la réservation : la Belgique. Formulation tenable : « la plateforme s'ouvre pays par pays, zone par zone ; aujourd'hui, la Belgique ».
- NE PAS écrire « 38 villes » ni « toute la Belgique couverte ». La géographie semée est 3 régions / 11 provinces / 36 communes / 38 codes postaux, et ce qui rattrape le reste est un repli sur une zone « couverture nationale ». C'est une couverture administrative, pas une présence terrain.
- NE PAS inventer ni réutiliser une note moyenne ou un volume d'avis. « 4.8 » et « 12 480 avis » n'ont aucune source. Un aggregateRating inventé dans du JSON-LD est une sanction Google, pas seulement une exagération.
- NE PAS promettre « un prix instantané pour chaque métier ». SIX métiers sur seize (bâtiment/gros œuvre, déménagement, garde d'enfants, levage, rénovation, sécurité) n'ont AUCUNE question semée, aucun prix de base, aucune unité de tarification : le moteur rend 0 €, pas « sur devis », et l'affichage n'a aucune garde contre zéro.
- NE PAS mettre « babysitting » ou « garde d'enfants » en vitrine sans corriger d'abord son parcours : c'est l'un des six métiers sans questionnaire, le client qui le choisit voit 0 €. L'accroche actuelle le cite (home.blade.php:31).
- NE PAS généraliser « devis gratuit en 2 minutes » à tout le catalogue. Deux métiers seulement annoncent honnêtement « sur devis » : toiture et élagage (pricing_unit = quote_only), et un métier QUOTE_ONLY n'affiche AUCUN chiffre, pas même un ordre de grandeur.
- NE PAS dire « prix ferme et définitif » ni « prix fixe garanti ». Ce qui est affiché est une ESTIMATION ; ce qui engage est le BAS de la fourchette, et l'écart se règle à la clôture sur constat. Formulation tenable : « vous savez entre quels montants ».
- NE PAS promettre un « prix dynamique selon l'heure, le jour ou la demande ». Le moteur de surge existe et est testé, mais son seul appelant (DynamicPricingService) n'a lui-même aucun appelant. Les colonnes night_multiplier, weekend_multiplier et emergency_multiplier sont éditables dans l'admin et n'atteignent JAMAIS le prix de /commander.
- NE PAS parler de TVA, de « prix TTC » ni de « TVA gérée pour vous ». Le moteur de prix client ne contient aucune occurrence de TVA ; app/Services/Tax/TaxCalculator.php n'a aucun appelant hors de son enregistrement en conteneur. La TVA n'existe qu'en aval, dans les écritures comptables.
- NE PAS promettre « payez dans votre devise » ni « taux du jour ». order_drafts.currency n'est écrit par AUCUN code du moteur de commande et reste à 'EUR' ; c'est cet instantané que Stripe lit pour débiter. Le symbole affiché suit les préférences du compte SANS convertir le montant : un compte en MAD verrait un dirham sur un nombre en euros. Et le fournisseur de change par défaut est 'mock', avec un repli 1:1.
- NE PAS promettre « payez avec vos avoirs / votre cagnotte ». CustomerCreditApplicationService n'est appelé que par le chemin B2B et récurrent. Le parcours /commander ne l'appelle jamais.
- NE PAS dire « réservez et payez en un clic ». Le paiement exige qu'un prestataire soit DÉJÀ assigné ET que son compte Stripe Connect soit finalisé ; sinon l'écran répond que le paiement sera pré-autorisé plus tard. L'empreinte est prise APRÈS l'acceptation.
- NE PAS dire « débit au démarrage de la mission » (l'erreur actuelle de /aide). La capture a lieu à la CLÔTURE. L'argument juste, et c'est le plus vendeur du produit, est « paiement après la prestation ».
- NE PAS annoncer l'acompte comme une option universelle : il n'est proposé qu'au-dessus de 500 €, et jamais sur une prestation « sur devis ».
- NE PAS présenter l'intervention immédiate comme une règle. Quatre métiers sur seize l'autorisent, ET il faut en plus que la zone l'ouvre. Ne jamais écrire « n'importe quel service en moins de X minutes ».
- NE PAS écrire « nous intervenons partout ». Sans ligne de tarif pour le couple (métier, zone), le service est FERMÉ et la commande refusée. La couverture annoncée doit correspondre aux zones réellement tarifées, pas à une ambition.
- NE PAS dire « le client voit le prix de son métier avant de payer » sans nuance géographique : en mode planifié, le carrousel n'applique AUCUN filtre de zone. Le refus « nous n'intervenons pas encore à cette adresse » n'arrive qu'à la confirmation — le client compose d'abord, et apprend ensuite.
- NE PAS présenter la facturation à l'heure comme un mode existant aujourd'hui. Le drapeau hourly_billing vaut false par défaut et AUCUN seeder ne l'active : le sélecteur d'heures n'apparaît que si un administrateur coche le drapeau ET renseigne un tarif horaire. C'est une capacité, pas une offre livrée.
- NE PAS dire « prolongez votre mission d'un clic depuis votre espace ». La prolongation existe côté API et dans l'application mobile cliente ; AUCUNE vue web ne l'appelle. Sur le web, le client ne peut pas prolonger.
- NE PAS écrire « aucune mission ne peut être clôturée sans votre accord ». Le code autorise explicitement la clôture sans code de fin, en se contentant de vérifier la position. Écrire que le code est le geste normal, et que la position est vérifiée dans tous les cas.
- NE PAS écrire « chaque intervention est géolocalisée » ni « impossible à contourner ». Le contrôle se laisse sauter dans deux cas documentés : sans coordonnées de destination le geste PASSE, et la clôture appelle le vérificateur sans exiger de position. Formuler « chaque geste porte son verdict de position, y compris quand il n'y en avait pas ».
- NE PAS dire « scannez le QR de votre prestataire ». Le sens du scan est UNIQUE : le client montre, le prestataire scanne. L'application prestataire n'affiche aucun QR. Et sur le web, le client ne voit que six chiffres — le panneau QR web est mort (il ne cherche que des codes non consommés, dans un bloc qui ne s'affiche qu'une fois la mission terminée).
- NE JAMAIS PROMETTRE « vos interventions sont assurées ». Aucun assureur n'est câblé : le fournisseur par défaut est 'mock', les deux intégrations nommées refusent faute de clé, et le bouchon fabrique des numéros de police marqués « simulated ». Une police émise aujourd'hui serait fictive.
- NE PAS afficher 5 000 € / 15 000 € / 50 000 € comme des plafonds de couverture : ce sont des valeurs de semeur (InsurancePlansSeeder), adossées à aucun contrat d'assureur.
- NE PAS écrire « ajoutez une assurance en un clic ». Aucun écran ne permet de souscrire : la souscription et la déclaration de sinistre n'existent que sous forme de routes d'API. L'écran « Ma protection » est en lecture seule et dit lui-même « l'assurance se souscrit au moment de la réservation » — ce qui n'est justement pas offert.
- NE PAS dire « Brio vous assure » à un prestataire. L'assurance est un produit vendu au CLIENT par réservation ; côté prestataire il n'existe qu'une exigence de justificatif RC professionnelle.
- NE PAS nommer Onfido, Veriff ni SumSub. Le pilote KYC par défaut est 'mock', et « veriff »/« sumsub » n'ont AUCUNE classe d'implémentation — le service provider lève une exception pour tout nom autre que mock ou onfido. Dire « vérification d'identité », sans fournisseur.
- NE PAS promettre de criblage anti-blanchiment, de listes de sanctions ni de PEP. Le contrat de criblage n'a qu'une implémentation, le bouchon, et le `match` n'a même pas de branche réelle.
- NE PAS annoncer une vérification d'entreprise « en direct sur les registres officiels ». Les trois fournisseurs KYB (identité, TVA, sanctions) valent 'mock' par défaut, clés INSEE et Companies House vides. Et le KYB ne garde AUCUNE porte : business_entities n'a pas de colonne d'organisation, et rien dans le code ne conditionne une commande, un contrat ou un paiement à « entreprise vérifiée ».
- NE PAS dire « tous nos prestataires passent un contrôle facial ». Il ne vise que les métiers dont la case est cochée — 2 par défaut, garde d'enfants et sécurité — et le comparateur facial est en fournisseur simulé. Ce qui est vrai et fort : le blocage du dispatch est réel.
- NE PAS dire « casier judiciaire vérifié » pour tous : deux métiers seulement l'exigent.
- NE PAS écrire « aucun prestataire ne travaille avant approbation » sans nuance : la garde EnsureProviderIsApproved laisse passer tout profil dont self_registered_at est nul. Un compte créé autrement n'est pas soumis à cette porte.
- NE PAS annoncer un délai d'approbation prestataire — ni 24 h, ni 48 h, ni aucun autre. AUCUN délai n'est codé. L'ouverture est automatique dès le dossier complet ; elle peut être instantanée comme rester bloquée indéfiniment sur une pièce manquante.
- NE PAS dire « 15 % de commission » sans le plancher de 2 €. Le dépôt chiffre lui-même 41 % sur une course de 4,81 €. La formulation honnête : « 15 %, plus un plancher de 2 € par encaissement, qui ne mord pas sur des interventions de 80 à 200 € ».
- NE PAS dire « payé sous 7 jours » comme une garantie. payout_delay_days est explicitement une ANNONCE : rien dans le dépôt ne déclenche le versement bancaire. Ce qui est vrai et fort : la part du prestataire est déjà sur son compte Connect à la capture.
- NE PAS dire « retirez votre argent quand vous voulez, il arrive sur votre compte ». requestWithdraw() ne fait aucun appel à Stripe : il crée une demande en attente et une écriture de débit. Le passage en banque suit le calendrier du compte Connect et n'est que CONSTATÉ par webhook.
- NE PAS dire « 100 % des pourboires pour vous » comme un engagement définitif : c'est un réglage par variable d'environnement, à 0 % aujourd'hui. Dire « aujourd'hui, la plateforme ne prélève rien sur les pourboires ».
- NE PAS dire « vos disponibilités décident des missions que vous recevez ». Les créneaux édités écrivent availability_slots, que le moteur de répartition ne lit jamais. Formulation tenable : « publiez vos créneaux et exportez-les en iCal ».
- NE PAS dire « choisissez votre rayon d'intervention ». Le champ existe et s'enregistre, mais aucun code de répartition ne le lit : la portée réelle est 5 km, +5 km, maximum 20 km. Dire « on vous propose ce qui est près de vous, jusqu'à 20 km ».
- NE PAS dire « vos badges vous apportent plus de missions ». Aucune des neuf dimensions du score de matching ne les lit. Ce qui apporte des missions : la note, le taux d'acceptation, la proximité.
- NE PAS dire « recevez plusieurs missions à la fois » : la requête candidate exclut quiconque a déjà une offre en cours. C'est un argument d'EXCLUSIVITÉ, pas de volume.
- NE PAS dire « acceptez ou refusez en 20 secondes » pour toutes les missions. Les 20 s (30 s sur quatre métiers lourds) valent pour l'IMMÉDIAT ; un rendez-vous planifié laisse 30 minutes.
- NE PAS promettre formations, académie, quêtes, objectifs récompensés, carte de chaleur de la demande. Les routes répondent, mais les données ne sont semées que par un seeder de démonstration absent du profil de production, et AUCUN écran n'appelle ces points hors de la tournée du jour.
- NE PAS promettre un catalogue de récompenses de fidélité : aucun seeder ne crée de LoyaltyReward. La page « Récompenses » est vide tant qu'un administrateur n'en saisit pas.
- NE PAS ÉCRIRE « notifications WhatsApp ». Le mot n'apparaît que comme LIBELLÉ d'un écran d'administration et dans un commentaire. Les canaux déclarés sont e-mail, SMS, push, in-app, webhook.
- NE PAS écrire « WebSocket temps réel ». La diffusion retombe sur 'null' par défaut ; le suivi fonctionne par sondage (15 s / 30 s). « Position en temps réel » est défendable, « WebSocket » ne l'est pas sans serveur Reverb configuré.
- NE PAS écrire « SMS et notifications push » sans réserve : les deux fournisseurs par défaut sont 'mock'. Le code est branché, mais un déploiement sans variables d'environnement n'envoie rien de réel.
- NE PAS écrire « appelez votre prestataire sans donner votre numéro ». L'appel masqué est COUPÉ par défaut (MASKED_CALLS_ENABLED=false, fournisseur 'mock'). Le câblage Twilio Proxy existe, l'abonnement non.
- NE PAS promettre les appels audio/vidéo dans les canaux d'équipe : sans clé LiveKit, rien n'est proposé à l'utilisateur.
- NE PAS construire une promesse d'« IA » sur le devis par photo. Ses prix viennent d'un barème écrit EN DUR dans l'invite (« Nettoyage 25-35€/h, Peinture 25-40€/m²… »), sans rapport avec trade_zone_pricing ni avec le moteur de prix ; aucune commande n'en sort ; il exige une clé Anthropic sinon il renvoie null en silence ; et il est derrière la connexion client, donc inutilisable comme accroche publique.
- NE PAS dire que le moteur d'automatisation « tourne ». Il est COUPÉ par défaut (config/features.php:70) et la commande sort immédiatement si le drapeau est faux. Formulation juste : « la plateforme sait automatiser, et l'exploitant décide quand ».
- NE PAS promettre les 5 alertes métier comme une surveillance active : seules 3 ont un appelant réel ; webhook_backlog et stuck_mission_holding_funds sont déclarées mais personne ne les émet.
- NE PAS dire « le catalogue est disponible en 6 langues ». Seul le champ `name` des secteurs et métiers est traduit (110 lignes). Les accroches de secteur et les descriptions restent en français partout — alors que les vues les demandent bien traduites. Et les pages publiques /services n'appellent JAMAIS translate() : elles sont monolingues françaises, même pour un visiteur néerlandophone.
- NE PAS ADDITIONNER LES DEUX CATALOGUES. Les 45 « services » de ServiceCatalog sont un niveau HÉRITÉ, exploité par l'administration et l'API B2B, pas par le parcours de commande. Écrire « 16 métiers et 45 services » laisserait croire à 61 offres.
- NE PAS CONFONDRE LES DEUX LOCATIONS. « Nos locations » = la plateforme loue SA flotte. « Location entre membres » = deux membres se louent leurs biens. Tables, commissions (15 % vs 25 %) et parcours sont distincts, et un test d'architecture échoue si l'un emprunte à l'autre.
- NE PAS promettre une assurance de location entre membres ni de la télématique véhicule : les deux sont à false avec le pilote 'demo', et le commentaire du code dit explicitement qu'aucun contrat n'est souscrit. C'est le pire risque de cette page.
- NE PAS présenter la location entre membres comme mise en avant : ses catalogues (/louer, /sejours) sont publics mais AUCUN lien public n'y mène. Seule « Nos locations » a un lien dans le parcours de commande.
- NE PAS parler d'un cycle « brouillon / publié » du catalogue : les colonnes published_at sont écrites mais ne gardent rien, seul is_active filtre l'affichage public.
- NE PAS présenter le catalogue comme figé ni comme un développement — l'inverse est l'argument fort et il est vrai.
- NE PAS promettre Peppol, Factur-X, ZUGFeRD, UBL ni facturation électronique normée. Aucune occurrence de Peppol hors des deux gabarits marketing ; le constructeur Factur-X n'a aucun appelant hors tests et dit lui-même que le XML embarqué n'est pas fait. Ce qui existe et peut être annoncé : CSV et FEC.
- NE PAS promettre une facture mensuelle consolidée AUTOMATIQUE. B2BMonthlyInvoiceService n'a pour appelants que deux boutons d'un écran d'administration, et aucune entrée de planificateur. Sans clic d'un opérateur, aucune facture mensuelle ne part.
- NE PAS dire « circuit d'approbation des dépenses activable par l'entreprise ». Le drapeau qui l'allume n'est ÉCRIT par personne — seul un seeder de démonstration le pose ; aucun écran ne le bascule. Et il n'y a pas de seuil de montant : c'est un booléen sur toute la société, qui s'annule pour quiconque porte bookings.approve. Pire : deux circuits d'approbation coexistent sans se parler, et l'un crée sa ligne d'approbation APRÈS que la mission a déjà été envoyée au dispatch. L'approbation ne bloque rien.
- NE PAS dire que le budget bloque la dépense : le service ne fait qu'alerter, une fois par palier ; la réservation passe même à 200 % du plafond.
- NE PAS dire que l'entreprise gère ses contrats. Côté société cliente, l'écran est en LECTURE SEULE : contrat, grille tarifaire et remise ne s'écrivent que depuis l'administration de la plateforme. Et un contrat-cadre qui ne désigne pas de société prestataire ne remise rien.
- NE PAS promettre « webhooks en libre-service pour l'entreprise ». L'enregistrement d'un point de livraison, la rotation du secret, le test et le rejeu sont réservés à l'administration. Formulation honnête : « nous branchons vos webhooks », pas « vous les branchez ».
- NE PAS dire « API publique entièrement documentée ». La documentation générée décrit 31 points d'entrée alors que l'application en expose 567 — et elle porte encore l'ancien nom du produit et un domaine d'exemple.
- NE PAS vendre les abonnements comme un engagement d'ENTREPRISE : subscriptions_v2 est rattachée à user_id, sans colonne d'organisation. Un abonnement appartient à une personne et ne se transmet pas quand elle quitte la société.
- NE PAS écrire « signature électronique qualifiée » ni « valeur probante eIDAS ». C'est un tracé de pad capturé en PNG base64, avec un faisceau de preuve (empreinte, IP hachée, navigateur, horodatage). Aucun certificat, aucun prestataire de confiance, aucune mention eIDAS dans le dépôt. Et l'écran « signatures sur place » planifie un rendez-vous PHYSIQUE : il n'émet aucune signature électronique et ne produit aucun document.
- NE PAS dire « registre financier immuable ». Ni provider_wallet_transactions ni accounting_entries ne portent de garde d'immuabilité : aucun updating/deleting bloqué, aucun déclencheur en base. Ce qui se dit sans exagérer : clé d'idempotence unique, solde après opération, refus des écritures déséquilibrées et des périodes closes.
- NE PAS vendre « chaque action est auditée » au niveau de l'audit v2 : ce registre n'est PAS alimenté par défaut (miroir désactivé, 7 modèles seulement portent le trait). Le journal réellement nourri est activity_logs.
- NE PAS dire « suppression de vos données » : c'est une ANONYMISATION. La ligne utilisateur est conservée pour ne pas casser les liens comptables, et les pièces financières sont gardées 10 ans. Dire « vos données personnelles sont anonymisées, les pièces comptables restent conservées comme la loi l'exige ».
- NE PAS affirmer qu'un visiteur non connecté est invité à consentir aux cookies dès l'accueil : le bandeau n'est posé que sur la coquille AUTHENTIFIÉE ; la coquille invité n'a qu'un lien « Cookies » en pied de page.
- NE PAS écrire « disponible sur l'App Store et Google Play ». Les fichiers de soumission portent encore des valeurs de gabarit côté client, et aucune section de soumission côté prestataire. Dire « applications natives client et prestataire », jamais « téléchargez-les ». Et ne pas renvoyer le prestataire vers « l'app Brio Provider » : le bloc de téléchargement disparaît faute de liens semés — il peut en revanche s'inscrire depuis /register.
- NE PAS parler de marque blanche, de revente ni de multi-tenant : les tables subsistent en base, mais il n'existe ni configuration, ni modèle, ni route. C'est un module retiré, pas une offre.
- NE PAS annoncer un prix de service tant que trades.default_hourly_rate n'est pas alimenté : c'est la colonne que lisent les pages publiques, et aucun seeder ne l'écrit. Les prix réels vivent dans base_price_cents, que ces pages ne lisent pas.
- AVANT D'ÉCRIRE UNE LIGNE DE COPY SUR UNE PAGE VITRINE : les attributs :seoTitle et :seoDescription passés à <x-guest-layout> sont IGNORÉS (GuestLayout ne déclare que $cta), et @push('head') n'est jamais rendu (le gabarit n'a qu'un @stack('scripts')). Tant que ce n'est pas corrigé, tout titre, toute méta-description et tout JSON-LD écrits pour /services, /pricing ou /blog sont jetés.
- AVANT D'AJOUTER UNE SECTION À L'ACCUEIL : trois des quatre ancres de la navigation (#fonctionnement, #confiance, #b2b) n'existent pas, et le pied de page reprend #b2b. L'entrée « Entreprises » de la barre ne mène nulle part.
- NE PAS PUBLIER DE DISCOURS SUR /pricing NI /blog SANS LES RELIER : /pricing n'a AUCUN lien entrant (ni navigation, ni pied de page, ni accueil, ni sitemap) et /blog est un état vide. Le sitemap, lui, émet des URL /providers/{slug-de-métier} qui sont des 404 — les vraies pages métier sont /services/{métier} et n'y figurent pas.
- NE PAS OUBLIER LE PRESTATAIRE : il n'est adressé nulle part sur la vitrine — une seule ligne de FAQ, un témoignage sans appel à l'action, aucune page, aucun bouton. C'est le trou le plus large du site, alors que /register propose déjà deux types de compte prestataire.
- NE PAS construire la nouvelle vitrine en français écrit en dur : les cinq gabarits actuels ne contiennent AUCUN appel de traduction, alors que la page annonce six langues.

## Contradictions entre auditeurs, et l'arbitrage

- NOMBRE DE MÉTIERS — un auditeur dit 12, un autre 15, un autre 16, un autre « 9 questionnés ». ARBITRÉ EN LISANT LES SEEDERS : 16 métiers distincts. TradeSeeder.php pose 12 slugs ; OrderEngineCatalogSeeder en réécrit 6 et en crée 3 neufs (nettoyage-fin-chantier:549, vitrerie:698, elagage:817) ; CourseCatalogSeeder crée le 16e (course-vtc). Les « 9 questionnés » et « 12 canoniques » sont des sous-ensembles réels, à ne jamais présenter comme le total.
- SECTEUR MOBILITÉ — un auditeur affirme que le secteur « mobilite » est référencé mais créé par aucun seeder, donc que « déménagement » reste sans secteur et incommandable. RÉFUTÉ : CourseCatalogSeeder.php:32 fait un firstOrCreate sur le slug « mobilite », et ReferencePlatformSeeder.php:34-38 l'appelle AVANT TradeSectorLinkSeeder. Le rattachement passe. Il y a bien 6 secteurs, et « déménagement » a un secteur.
- MÉTIERS EN INTERVENTION IMMÉDIATE — 3 ou 4 selon l'auditeur. ARBITRÉ : 4. La colonne allows_asap a pour défaut FALSE (migration 2026_08_01_000100:71), et quatre seeders seulement l'activent : plumbing, electrical, nettoyage, course-vtc. Celui qui annonçait 3 avait oublié la course.
- FACTURATION À L'HEURE — un auditeur affirme « 1 métier sur 16 est facturé à l'heure aujourd'hui (nettoyage) », un autre affirme qu'aucun seeder n'active le drapeau. ARBITRÉ PAR GREP : hourly_billing n'apparaît dans AUCUN seeder ni aucune migration de données ; son défaut est false. Le premier auditeur a confondu pricing_unit=PER_HOUR (une unité de tarification, OrderEngineCatalogSeeder:629) avec hourly_billing (le drapeau du sélecteur d'heures). La facturation au temps est une CAPACITÉ que l'administrateur arme, zéro métier livré avec.
- REMISE DE GROUPAGE — deux réglages homonymes et divergents. ARBITRÉ : PricingEngine::bundleDiscountPercent lit order_engine.bundle_discount_percent (5/8/12 %), et rien d'autre. config/bundles.php:11 (10 %) appartient à l'ancien module de demande de devis multi-métiers (MultiTradeBundleService), un parcours distinct. La carte de démonstration de l'accueil affiche −8 % sur QUATRE lots : le barème du parcours donnerait −12 %.
- CODE À SIX CHIFFRES — un auditeur dit 20 minutes et 5 tentatives, un autre 10 minutes. ARBITRÉ : LES DEUX ONT RAISON, ce sont deux dispositifs différents. MissionVerificationCodeService (démarrage et fin de mission) : 20 min, 5 tentatives, haché. PresenceCodeService (confirmation de présence à l'arrivée) : 10 min, 5 tentatives. Ne jamais les fusionner dans une phrase de vitrine.
- PUBLICATION DES AVIS À 14 JOURS — un auditeur la présente comme un fait, un autre comme du code sans appelant. ARBITRÉ PAR GREP : publishExpiredPending() (RatingService.php:106) n'est appelé QUE par tests/Feature/Rating/RatingServiceTest.php:148, et aucune ligne d'app/Console/Kernel.php ne le planifie. Le double aveugle est vrai ; la publication forcée à 14 jours ne l'est pas — un avis déposé par une seule partie reste en attente indéfiniment.
- CLÔTURE SANS CODE — un auditeur écrit « la mission ne se clôt qu'avec l'accord du client », un autre dit l'inverse. ARBITRÉ EN LISANT MissionLifecycleService.php:452-457 : le commentaire du code dit lui-même « Clôturer sans code de fin reste possible », la position étant vérifiée dans tous les cas. Le code à six chiffres verrouille le DÉMARRAGE, pas la fin — et la capture du paiement suit la clôture.
- UN SEUL MOTEUR DE PRIX ? — non, deux, et ils ne suivent pas les mêmes règles. PricingEngine (app/Services/OrderEngine/) sert le parcours web ; TradePricingEngine (app/Services/Pricing/) sert l'API d'estimation (routes/api/client.php:52) et les devis prestataires. Une promesse de « prix identique partout » n'est pas démontrable par le code.
- DISPONIBILITÉS DU PRESTATAIRE — un auditeur les présente comme ce qui décide des missions reçues, un autre dit qu'elles ne sont pas lues. ARBITRÉ PAR GREP sur CandidateFinder.php : aucune occurrence d'availability_slots ni de disponibilites, ni dans immediate() ni dans scheduled(). Il existe deux notions distinctes : availability_slots (éditées par le prestataire, exportées en iCal, ignorées du dispatch) et disponibilites (lue par les séries récurrentes et la reprogrammation client).
- RAYON D'INTERVENTION — le champ existe, va de 1 à 200 km et s'enregistre (MaPresence.php:164). VÉRIFIÉ PAR GREP : ses seuls lecteurs sont l'écran lui-même et la sérialisation de l'API de présence ; aucun code de répartition ne le lit. La portée réelle est fixée par config/dispatch.php:79-83 — 5 km, +5 km, maximum 20 km.
- ASSURANCE — un auditeur la classe « module complet », un autre « injoignable ». ARBITRÉ PAR GREP sur InsuranceService : ses seuls appelants sont deux contrôleurs d'API, un job de webhook, le service provider, et le runner d'annulation. AUCUN composant Livewire, aucun écran mobile, aucune étape du tunnel de commande. Le module est modélisé de bout en bout et n'a aucune porte de souscription — et le fournisseur par défaut est « mock » (config/insurance.php:5).
- TITRES SEO — vérifié : app/View/Components/GuestLayout.php ne déclare QUE $cta. Les attributs :seoTitle et :seoDescription passés à <x-guest-layout> partent dans le sac d'attributs et sont ignorés ; toute page vitrine sort le titre par défaut de l'accueil. Tant que ce composant n'est pas corrigé, tout titre et toute méta-description écrits pour /services, /pricing ou /blog sont jetés.
