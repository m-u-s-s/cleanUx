# Moteur de répartition des missions — dispatch temps réel type Uber/Bolt/Heetch (web + mobile natif)

Tu travailles sur le monorepo CleanUx : marketplace multi-services (nettoyage, peinture, babysitting, toiture…), Laravel 11 + Livewire côté web, monorepo Expo/React Native sous `mobile/` (`mobile/client`, `mobile/provider`, package partagé `mobile/shared`). Base MySQL en prod, tests PHPUnit sur SQLite.

**MISSION : construire UN moteur de répartition des missions fiable et puissant, au niveau d'Uber/Bolt/Heetch, pour TOUS les rôles et sous-rôles de la plateforme (indépendant, salarié de société, société prestataire), en mode INTERVENTION IMMÉDIATE et en mode PRISE DE RENDEZ-VOUS, fonctionnant sur web ET en natif mobile — avec UN SEUL formulaire de réservation, identique partout.**

La garantie centrale : quand un client valide une réservation, la mission part de façon SÛRE vers des prestataires **proches**, **en ligne** (pour l'immédiat) et **du bon métier**. Un peintre ne reçoit JAMAIS une mission babysitting. Un prestataire de Liège ne reçoit jamais une mission à Anvers.

## Méthode de travail imposée

- **ANALYSE LE CODE EN ENTIER, surtout les parties dont tu as besoin. NE TE FIE SURTOUT PAS à `docs/` : elle est ancienne et contredit le code.** La vérité est dans `app/`, `routes/`, `database/migrations/`, `config/`, `mobile/` et la base locale `brio`.
- **Tu as le droit de modifier des modèles et des tables si ça optimise le code**, y compris de façon destructive : un `migrate:fresh` sera exécuté à la fin. Donc : modifie/consolide les migrations directement (pas de migrations de rattrapage), et garde COHÉRENTS migrations + modèles + factories + seeders pour qu'un `php artisan migrate:fresh --seed` produise une plateforme fonctionnelle et démontrable.
- **BOUCLE DE TRAVAIL OBLIGATOIRE** : travaille lot par lot ; après CHAQUE lot, exécute la batterie de vérification (en bas de ce document). Si quelque chose échoue, corrige et reboucle sur le lot. **Ne t'arrête que lorsque les 13 consignes de la checklist finale sont TOUTES cochées, que tout est installé sans erreur et que tout fonctionne** — suite de tests complète verte, PHPStan propre, `migrate:fresh --seed` propre, parcours manuels décrits réalisables.
- Un lot = une séquence de commits cohérente sur `main`. Ne pas surveiller la CI sans demande.
- La suite tourne sur SQLite, la prod sur MySQL strict : pas de SQL vendor-specific ; `lockForUpdate()` est un no-op sous SQLite — tester la logique, pas le verrou. Noms d'index ≤ 64 caractères, vérifier via `php artisan migrate --pretend` sur base vide.
- PHPStan à lancer **SANS argument de chemin**.
- Livewire ne rejoue pas `mount()` : chaque action publique revérifie ses gardes.
- Mobile : thème via fabriques `stylesFor(useThemeColors())`, zéro couleur en dur ; tests de joignabilité qui PRESSENT (`fireEvent.press` depuis le navigateur monté), jamais des tests qui lisent la source ; `usePresenceHeartbeat()` ne se monte QUE dans l'espace terrain.

## Les 13 consignes (toutes OBLIGATOIRES)

0. **TRÈS IMPORTANT — un seul formulaire de réservation.** Que la demande soit immédiate ou sur rendez-vous, le formulaire est IDENTIQUE partout, aligné sur celui de la page `/commander` (parcours du moteur de commande) : adapter les pages comme `/dashboard/client/rendez-vous/nouveau` pour qu'elles présentent LE MÊME formulaire que `/commander`, et le mobile présente le même parcours que le web.
1. Les missions IMMÉDIATES sont envoyées aux prestataires les plus PROCHES et SEULEMENT ceux EN LIGNE.
2. Le système de missions marche par LOCALISATION/POSITION (position GPS fraîche du prestataire, pas seulement des zones déclarées).
3. S'inspirer des plateformes concurrentes (Uber, Heetch, Bolt, Takeaway) pour la meilleure méthode de transmission des missions — la synthèse de leurs patrons est fournie plus bas, applique-la.
4. Si un prestataire tarde à répondre à une intervention immédiate, la demande passe à un AUTRE prestataire — obligatoirement du MÊME MÉTIER que l'intervention.
5. La mission choisit des prestataires de la ZONE et du MÉTIER adéquats (jamais un peintre pour du babysitting).
6. Les prestataires reçoivent les interventions immédiates en HOME SCREEN dans un MODAL avec **20 secondes** pour accepter ou refuser ; refus ou 20 s écoulées → la demande passe au prestataire suivant.
7. Zone et type de mission sont analysés et pris en compte DÈS LA RÉSERVATION CLIENT, pour que le prestataire retenu soit réellement parmi les plus proches.
8. Solutions si AUCUN prestataire trouvé après un long moment : proposer au client de convertir en rendez-vous planifié OU de continuer à attendre (OU annuler avec remboursement).
9. À l'INSCRIPTION prestataire, les zones et métiers affichés viennent du CATALOGUE ADMIN (`/admin/catalogue`) ; un prestataire ne reçoit QUE des missions du métier et de la zone qu'il a sélectionnés.
10. Concevoir le meilleur moteur de répartition pour TOUS les rôles et sous-rôles, en mode immédiat ET en mode rendez-vous.
11. Dans le catalogue, l'admin choisit QUELS MÉTIERS interviennent en mode immédiat et lesquels non.
12. Le moteur fonctionne sur WEB et en NATIF MOBILE (côté client ET côté prestataire).

## Synthèse concurrents — les patrons à appliquer (consignes 3 et 10)

**Uber / Bolt / Heetch (VTC)** :
- Annuaire géo-indexé des conducteurs EN LIGNE avec position GPS fraîche (ping continu) ; candidat = en ligne + bonne catégorie de véhicule (≈ métier) + dans le rayon.
- Classement d'abord par PROXIMITÉ/ETA, la note n'est qu'un départage — le client attend, la distance prime.
- **Offre SÉQUENTIELLE** : UN conducteur à la fois, modal plein écran par-dessus tout, compte à rebours visible (~15-30 s), son + vibration ; refus ou timeout → suivant automatiquement, sans intervention humaine.
- Rayon CROISSANT par vagues quand personne n'accepte ; jamais deux offres actives pour la même course ; un conducteur ne voit qu'une offre à la fois.
- Occupé automatiquement pendant la course (plus d'offres) ; disponible à nouveau à la fin.
- Course planifiée (Uber Reserve) : offre envoyée À L'AVANCE aux mieux classés, avec fenêtre de réponse longue ; rappels avant l'heure.
- Tout est tracé : chaque offre, chaque refus, chaque timeout (l'acceptance rate existe parce que chaque événement est une ligne).

**Takeaway / UberEats (livraison)** : zones d'éligibilité strictes ; en ligne = prêt à recevoir ; file d'attente d'offres ; si personne ne prend, élargissement puis proposition alternative au client.

**Ce qu'on retient pour CleanUx** : chaîne d'offres séquentielle à TTL 20 s (le patron existe déjà dans `MissionDispatchService`) + vagues à rayon croissant (le patron existe déjà dans `AsapDispatchService`) + en dernière vague un broadcast « premier qui accepte gagne » + position fraîche via le heartbeat de présence + conversion en rendez-vous comme sortie de secours. L'essentiel du travail est d'UNIFIER, de FIABILISER et de brancher les surfaces — pas de réinventer.

## État des lieux vérifié (par exploration exhaustive du code — les lignes exactes peuvent avoir bougé, re-vérifie au besoin)

**DEUX formulaires de réservation coexistent** (cœur de la consigne 0) : la page `/commander` sert le parcours du moteur de commande (`OrderJourney` Livewire : SECTEUR→MÉTIER→QUESTIONS piloté par l'admin, prix-avant-identité, sélecteur immédiat/rendez-vous, panier en base sur jeton de session via `order_drafts`, confirmation par `OrderConfirmationService`) ; et l'ancien formulaire du tableau de bord client, `/dashboard/client/rendez-vous/nouveau` (composant type `PrendreRendezVous`, sélecteur ASAP/Planifier à l'étape 4, création via `CreateBookingAction`). **Deux formulaires = deux chemins de création de booking = deux entrées de dispatch.** Vérifie les routes et composants exacts dans `routes/` avant d'y toucher.

**Parcours de commande.** `app/Services/OrderEngine/` : `PricingEngine`, `OrderDraftManager`, `BundleComposer`, `OrderConfirmationService`, `OrderPaymentPlanner`… ; écrans Livewire `OrderJourney`, `OrderConfirmation`, `AsapSearch` ; admin `QuestionnaireBuilder` + `CatalogCenter`. Une réservation par métier, devis figé à la confirmation. **L'« ASAP » actuel est une fenêtre de 2 h (`asap_deadline_at = now+2h`), pas de l'instantané littéral.** Le paiement attend l'assignation (destination charge Stripe, exige un prestataire Connect).

**⚠️ LA CHAÎNE GÉOGRAPHIQUE EST COUPÉE AU DERNIER MAILLON** (cœur des consignes 5, 7, 9) : `order_drafts` ne porte NI zone NI code postal ; le pivot `service_zone_postal_code` a 0 ligne ; `ZoneCoverageService` (complet) n'est branché que sur l'ANCIEN parcours ; `OrderEngine\PricingEngine` accepte un `zone_multiplier` que personne ne fournit (toujours 1,0). Trois chemins de prix par zone coexistaient, tous morts pour le parcours de commande ; décision propriétaire déjà prise : **`trade_zone_pricing` est LE chemin survivant — activation d'un métier et prix sont LA MÊME ligne (métier, zone)** ; éteindre ne supprime jamais la ligne. `trade_zone_settings` doit mourir.

**Catalogue admin.** `/admin/catalogue` = Pays → Zones → Catalogue (`CountryCenter`, `ZoneCenter`, `CatalogCenter` contextualisé par zone), aussi servi à la console admin mobile via `/api/admin/catalogue/*` (+ `JourneyBuilder` questions/options/publish). L'activation d'un métier se fait PAR ZONE uniquement (pas de table country_trade). Aucun drapeau « intervient en immédiat » n'existe — à créer (consigne 11).

**Dispatch immédiat existant — DEUX chemins qui coexistent, à UNIFIER** :
- Chemin « booking asap » : `CreateBookingAction` (booking_mode `asap`) → `MissionDispatchService::dispatchToNextProvider()` — chaîne SÉQUENTIELLE : `createOffer()` pose un `MissionAssignment` (`assignment_status='assigned'`, `expires_at`) + `MissionOfferNotification` + `EscalateMissionAssignmentJob` retardé ; timeout par métier dans `config/dispatch.php` (défaut **15 s** — à passer à **20 s** ; 30 s toiturier/électricité/plomberie/déménagement ; `max_escalation_depth = 5`) ; `accept()` avec `lockForUpdate()` sur la Mission, écrit `lead_provider_user_id` + `lead_employee_id` + `provider_organization_id`, annule les autres offres, synchronise le booking (`employe_id`, `status='confirme'`) ; `decline()`/`expireAndEscalate()` passent au suivant en EXCLUANT les déjà tentés ; garde KYC à l'offre ET à l'acceptation.
- Chemin « order engine asap » : `AsapDispatchService` (`open()` idempotent, `expand()` par paliers **5 km → 20 km max**, `hasTimedOut()` 180 s, `accept()` avec `lockForUpdate()` premier-accepte-gagne, `waysForward()`) + `AsapProviderNotifier` (notifie les prestataires du rayon, une fois chacun, index unique sur `asap_dispatch_notifications (request, user)`) ; tables `asap_dispatch_requests` + `asap_dispatch_notifications` ; écran client `AsapSearch` (Livewire).
Historique : le double-dispatch ASAP (offre séquentielle ET confirmation directe en parallèle) a été RÉSOLU — l'ASAP ne passe plus que par l'offre/escalade ; la confirmation directe `SmartDispatchService::assignBestEmployee()` est réservée au PLANIFIÉ.

**Dispatch planifié existant.** À la confirmation d'un rendez-vous : `SmartDispatchService::assignBestEmployee()` (assignation d'office, sans acceptation du prestataire). Chaîne de scoring avec replis : `AiDispatchService` → `MatchingV2Service` (9 dimensions pondérées `config/matching.php` : rating 25, acceptance 15, zone_proximity 15, completion 10, workload 10, client_affinity 10, response 5, trade 5, recency 5 ; audit complet dans `booking_matching_decisions` ; feature-flag + shadow mode) → `MatchingScorer` → scoring v1.

**Filtre candidats.** `EmployeeAvailabilityService::eligibleEmployeesQuery(?zoneId, providerType, ?organizationId)` : `provider_profiles.status='active'` + `verification_status='verified'` + `users.is_active` ; filtre société via `provider_profiles.organization_account_id` quand le client a choisi une société (`bookings.assigned_provider_organization_id`). Métier : pivot `trade_user` (proficiency, is_primary). **Vérifie et durcis : le filtre métier et le filtre zone doivent être INFRANCHISSABLES dans la requête candidate — c'est l'invariant « jamais un peintre en babysitting ».**

**En ligne / position.** DEUX systèmes de présence : Phase 11 binaire (`provider_profiles.is_online` — c'est ce que lit le dispatch aujourd'hui) et **Presence v2** (`provider_presence` : 4 états online/busy/on_break/offline, `current_lat/lng`, `heartbeat_at`, heartbeat 60 s, stale auto-offline 5 min via cron, `availableProviderIds()`, miroir `syncLegacyOnlineFlag()` vers is_online ; endpoints `/api/provider/presence-v2/*` ; le mobile y est déjà câblé via `mobile/provider/src/presence/hooks.ts`). **Le dispatch doit consulter Presence v2 (états + position fraîche), le miroir binaire ne suffit pas.** Transitions auto existantes : `PresenceAutoTransitioner` (booking démarré → busy ; terminé/annulé → online) via `BookingObserver`.

**Géo.** `GeoDistanceService` (haversine), `GeolocationV2\DistanceCalculator` + caches, `ServiceZone`, `PostalCode`, `EmployeeZoneAssignment`, `PartnerZoneCoverage`, `ZoneServiceRule`, API `/api/v2/geo/{autocomplete,geocode,reverse,distance}`. Les bookings portent `destination_lat/lng` + `google_place_id` + `address_components`.

**Surfaces prestataire actuelles.** Mobile (`mobile/provider`) : `AsapOffersScreen` (monté, route `AsapOffers`, `src/asap/hooks.ts` → `GET /provider/asap-offers`, accept/decline) ; `MissionInboxScreen` (`useMissionInbox` → `GET /provider/assignments/inbox`, **polling 15 s** — ne liste que les offres `assignment_status='assigned'`) ; **`AssignmentOfferScreen` : écran d'offre plein écran avec compte à rebours, COMPLET mais ORPHELIN (monté nulle part)** — base parfaite pour le modal 20 s ; `DashboardScreen` (home avec carte + `PresencePill`). **Aucune offre n'arrive en push ni en realtime : tout est du polling.** `useLiveMissionUpdates` (canal `private-mission.{id}`) est défini mais JAMAIS appelé. Realtime mobile fonctionnel par ailleurs (`mobile/shared/src/realtime/RealtimeProvider` + `useChannel`, auth Bearer `/api/broadcasting/auth`) ; canal `user.{userId}` déjà autorisé dans `routes/channels.php` ; infra push complète (`PushService::dispatchToUser`, FCM/APNs, `device_tokens`, ledger idempotent) mais enregistrement device sous `/api/client/devices/*` (nommage trompeur, à aliaser côté provider).
Écrans orphelins connus à ne pas confondre avec des surfaces vivantes : `HomeScreen`, `WalletScreen`, `SettingsScreen`, `MissionExecutionScreen`, `AssignmentOfferScreen`.

**Notification d'offre.** `MissionOfferNotification` : canaux `database` + WebPush ; `toMail` défini mais `via()` ne retourne pas mail. Pas de push mobile data-message pour ouvrir un modal.

**Pièges vérifiés du dépôt** : `config/broadcasting.php` défaut `null` (broadcasts silencieusement jetés sans variable d'env — consigne d'exploitation, ne pas changer le défaut) ; la table `missions` est LE modèle d'exécution (ne pas bâtir à côté) ; `mission_assignments` a des doublons historiques `role`/`role_on_mission` et `status`/`assignment_status` — le flux de dispatch utilise `status`/`assignment_status` + `expires_at`/`response_seconds` ; `AvailabilityService::isAvailable()` est un concept d'indépendant à créneaux publiés (INTERDIT comme filtre de dispatch immédiat) ; les tests verts ne prouvent ni la joignabilité d'un écran ni qu'une table se remplit en prod — vérifier sur données semées.

**À VÉRIFIER TOI-MÊME dans le code avant les lots concernés** (points non re-vérifiés récemment) : les routes/composants exacts de `/commander` et `/dashboard/client/rendez-vous/nouveau` ; le câblage exact de l'inscription prestataire (web + `ApiAuthController::createProviderIdentity` + onboarding mobile `mobile/shared/src/onboarding` et `trades/`) — d'où viennent les listes de métiers/zones affichées aujourd'hui ; la surface client MOBILE du parcours de commande (native ou WebView) ; l'état réel des colonnes zone sur `bookings`/`order_drafts`.

---

## Lot 0 — UN SEUL formulaire de réservation, partout (consigne 0)

Objectif : que le client réserve depuis la page publique, depuis son tableau de bord ou depuis le mobile, en immédiat ou en rendez-vous, c'est LE MÊME parcours — celui de `/commander` — et UNE SEULE entrée de création de booking pour le dispatch.

- **Vérifie d'abord** les routes et composants exacts : `/commander` (parcours `OrderJourney` du moteur de commande) et `/dashboard/client/rendez-vous/nouveau` (ancien formulaire type `PrendreRendezVous` → `CreateBookingAction`).
- **`/dashboard/client/rendez-vous/nouveau` présente désormais le parcours `/commander`** : même composant `OrderJourney` (réutilisé, pas copié), pré-rempli avec l'identité du client connecté (l'étape identité est sautée ou pré-validée — le parcours public reste prix-avant-identité, le parcours connecté saute directement aux questions), même sélecteur immédiat/rendez-vous, même récapitulatif de prix. L'ancienne route peut rediriger ou monter le composant en place — mais l'ancien formulaire DISPARAÎT.
- **Une seule entrée de création** : toute réservation (publique, dashboard, mobile, immédiate ou planifiée) passe par le pipeline du moteur de commande (`order_drafts` → confirmation → booking). Retire du parcours nominal l'ancien chemin de création dupliqué ; `CreateBookingAction` ne survit que s'il est le maillon commun appelé PAR la confirmation — pas comme deuxième porte d'entrée. Le dispatch (lots suivants) ne doit avoir QU'UNE porte amont.
- **Mobile client** : vérifie la surface actuelle du parcours de commande (native ou WebView). Exigence : le mobile présente le MÊME parcours, mêmes étapes, mêmes questions, mêmes prix que `/commander` — si c'est une WebView du parcours web, elle doit pointer sur le parcours unifié ; si c'est natif, aligne les étapes sur le même contrat API (`order_drafts`).
- Les réservations d'entreprise cliente (multi-sites, etc.) qui passeraient par d'autres écrans sont HORS périmètre de ce lot — ne casse rien, mais note-les.

**Acceptation** : ouvrir `/dashboard/client/rendez-vous/nouveau` connecté → on voit le parcours `/commander` pré-rempli, on réserve en immédiat ET en rendez-vous ; les deux réservations passent par `order_drafts` et déclenchent le même pipeline ; l'ancien formulaire n'est plus atteignable ; mobile : le parcours affiche les mêmes étapes et aboutit au même booking (vérifié sur base semée) ; aucune route ne crée plus de booking hors moteur de commande sur le parcours client nominal.

## Lot 1 — Réparer la chaîne géographique et le catalogue (consignes 2, 5, 7, 11)

Objectif : dès la réservation, le booking porte un MÉTIER, une ZONE et une POSITION exploitables par le dispatch ; l'admin contrôle par zone quels métiers existent, à quel prix, et lesquels font de l'immédiat.

- **`trade_zone_pricing` devient la source unique** lue par `OrderEngine\PricingEngine` (activation + prix par (métier, zone)) ; supprimer `trade_zone_settings` et l'ancien chemin `TradePricingEngine` s'il est mort. Un seeder sème la grille complète (chaque métier actif × chaque zone active) pour que `migrate:fresh --seed` donne une plateforme où tout est disponible — AUCUN métier ne doit devenir indisponible par accident de données.
- **Peupler `service_zone_postal_code`** (seeder à partir des zones/communes existantes) et brancher `ZoneCoverageService` sur le parcours de commande : le code postal/l'adresse géocodée du client résout `service_zone_id` PENDANT le parcours.
- **`order_drafts` et `bookings` portent la géographie** : `service_zone_id`, `postal_code`, `trade_id` (en plus de `destination_lat/lng` déjà géocodés). Modifie les migrations existantes directement (migrate:fresh prévu). Si un booking ne peut pas résoudre sa zone → bloquer la confirmation avec un message clair, jamais un dispatch à l'aveugle.
- **Consigne 11** : colonne `asap_enabled` (boolean, défaut false) sur `trade_zone_pricing` + toggle dans `CatalogCenter` (à côté de l'activation/prix, même ligne (métier, zone)) + exposée par l'API catalogue admin (`/api/admin/catalogue/*`, la console admin mobile l'affiche aussi). Le parcours client n'offre le mode « intervention immédiate » QUE si la ligne (métier du panier, zone du client) l'autorise ; sinon seul « prise de rendez-vous » est proposé.
- Le sélecteur immédiat/planifié écrit `booking_mode` de façon fiable sur `order_drafts` → `bookings`.

**Acceptation** : `migrate:fresh --seed` → parcours de commande complet où le prix vient de `trade_zone_pricing`, la zone est résolue depuis le code postal, le mode immédiat n'apparaît que si `asap_enabled` ; test : zone non couverte → confirmation bloquée ; test : basculer `asap_enabled` dans l'admin change l'offre du parcours client sans déploiement.

## Lot 2 — L'annuaire des candidats : un seul service, des filtres infranchissables (consignes 1, 2, 5)

Objectif : une source unique répond à « qui peut recevoir cette mission, ordonné du meilleur au moins bon ».

- Créer `app/Services/Dispatch/CandidateFinder` (ou refondre `EmployeeAvailabilityService` — à toi de juger ce qui optimise, tu as le droit de restructurer) avec une requête candidate qui IMPOSE, dans le SQL même :
  1. **Métier** : jointure `trade_user` sur le `trade_id` du booking (l'invariant « jamais un peintre en babysitting » vit ICI, pas dans un if) ;
  2. **Zone/position** : pour l'IMMÉDIAT — position fraîche `provider_presence.current_lat/lng` (heartbeat < 5 min) dans le rayon de la vague courante (pré-filtre bounding box + haversine précise, index sur lat/lng) ; pour le PLANIFIÉ — zones déclarées (`EmployeeZoneAssignment`/couverture) contenant la zone du booking ;
  3. **En ligne (immédiat seulement)** : Presence v2 `status='online'` + heartbeat frais — PAS le miroir binaire seul ; un prestataire `busy`/`on_break`/`offline`/stale ne reçoit RIEN ;
  4. **Éligibilité** : profil actif + vérifié + KYC + `users.is_active` ;
  5. **Société** : si le client a choisi une société, restreindre à ses workers ; sinon indépendants + workers confondus ;
  6. **Exclusions** : les user_ids déjà tentés sur cette mission, et ceux qui ont déjà une offre active en main (un prestataire ne voit qu'UNE offre à la fois, patron Uber).
- **Ordre** : distance d'abord (l'immédiat, c'est la proximité), départagée par le score `MatchingV2Service` (réutilise le moteur 9 dimensions existant — ne le réécris pas, branche-le) ; pour le planifié, score d'abord.
- Fais consulter Presence v2 par TOUTE la chaîne (`MatchingV2Service`, `AiDispatchService`, `SmartDispatchService`, `AsapProviderNotifier`) — supprime les lectures directes de `is_online` au profit du service.

**Acceptation** : tests dédiés — un peintre en ligne à 500 m ne reçoit JAMAIS une mission babysitting ; un babysitter offline ne reçoit rien en immédiat ; un babysitter online à 3 km passe avant un à 12 km ; un prestataire avec une offre en main n'en reçoit pas une deuxième ; un worker de la société choisie passe, un worker d'une autre société non.

## Lot 3 — Inscription et profil connectés au catalogue (consigne 9)

Objectif : les métiers et zones proposés à l'inscription sortent du catalogue admin, et le dispatch ne sert QUE ce qui a été déclaré.

- **Vérifie d'abord le câblage réel** de l'inscription (web + `ApiAuthController::createProviderIdentity` + onboarding mobile). Puis :
- Les listes de MÉTIERS proposées = `trades` ACTIFS du catalogue (par secteur) ; les listes de ZONES = zones ACTIVES du catalogue (celles ayant au moins une ligne `trade_zone_pricing` active pour le métier choisi). Une seule API partagée web/mobile (ex. `GET /api/catalog/registration-options?country=`), consommée par les écrans register web ET l'onboarding natif `mobile/provider`.
- L'inscription écrit `trade_user` (métiers, avec is_primary) et les zones (`EmployeeZoneAssignment` ou la table de couverture que tu retiens — unifie s'il y a doublon). Un prestataire peut MODIFIER ses métiers/zones après coup (écran profil web + écran natif), avec les mêmes listes catalogue.
- Le `CandidateFinder` (Lot 2) lit exactement ces déclarations : changer ses métiers/zones change immédiatement ce qu'on reçoit.
- Si l'admin désactive un métier ou une zone au catalogue, les prestataires concernés ne reçoivent plus de missions de ce couple (métier, zone) — sans casser leur compte.

**Acceptation** : test — un métier ajouté au catalogue apparaît à l'inscription sans déploiement ; un inscrit « peintre, zone Liège » ne reçoit que peinture-Liège ; il ajoute « Bruxelles » dans son profil → il devient candidat à Bruxelles ; l'admin désactive peinture-Liège → plus d'offres pour ce couple. Mobile : l'onboarding natif affiche les mêmes listes que le web (test qui presse).

## Lot 4 — Le moteur d'offres unifié : séquentiel 20 s + vagues + broadcast final (consignes 1, 3, 4, 5, 6, 10)

Objectif : UN orchestrateur pour l'immédiat, qui unifie les deux chemins actuels (`MissionDispatchService` séquentiel et `AsapDispatchService` par rayons) au lieu de les laisser coexister.

- **Architecture cible** : `asap_dispatch_requests` reste l'objet « recherche en cours » (état, vague, rayon, deadline) ; `MissionAssignment` reste l'objet « offre individuelle » (expires_at, response_seconds, statuts). Un orchestrateur `DispatchEngine` pilote : à la confirmation d'un booking immédiat → ouvrir la recherche → boucler les vagues.
- **Déroulé d'une recherche immédiate** (patron Uber adapté) :
  1. Vague 1 : candidats du `CandidateFinder` dans le rayon initial (config), ordonnés distance+score ; offre SÉQUENTIELLE au premier : **TTL 20 secondes** (`config/dispatch.php` : passe le défaut de 15 à 20 s ; garde l'override par métier) ; refus ou timeout → suivant, en excluant les tentés (le patron `expireAndEscalate`/`EscalateMissionAssignmentJob` existe — fiabilise-le : le job retardé doit survivre à un restart de queue, l'idempotence doit être prouvée par test) ;
  2. Épuisement de la vague → ÉLARGIR le rayon (paliers config, ex. 5 → 10 → 20 km, patron `expand()` existant) et recommencer ;
  3. Dernière vague (rayon max atteint) → **BROADCAST premier-accepte-gagne** à tous les candidats restants du rayon max (patron `AsapDispatchService::accept()` avec `lockForUpdate()` — le verrou existe, garde-le) ;
  4. Deadline globale de recherche (config, ex. 5 min — remplace l'ambiguïté de la fenêtre 2 h actuelle : `asap_deadline_at` devient la deadline de RECHERCHE, l'intervention elle-même suit) → sortie « aucun prestataire » (Lot 6).
- **Transmission d'une offre — triple canal, fiable** :
  1. **Realtime** : event broadcast sur `user.{userId}` (canal déjà autorisé) avec payload complet de l'offre (mission, adresse approx, prix, TTL, expires_at serveur) ;
  2. **Push data-message haute priorité** via `PushService::dispatchToUser` (catégorie transactionnelle) pour réveiller l'app en arrière-plan ;
  3. **Repli polling** : l'inbox existante (`GET /provider/assignments/inbox`) reste la source de vérité si push et socket ratent.
  Le compte à rebours affiché se calcule sur `expires_at` SERVEUR (jamais un timer client seul).
- À l'acceptation : mission assignée (verrou), booking synchronisé, presence → `busy` (`PresenceAutoTransitioner` existant), les autres offres/notifications de la recherche sont annulées PARTOUT (realtime « offer_cancelled » pour fermer les modals des perdants du broadcast).
- **Audit total** (patron concurrent : chaque événement une ligne) : chaque offre, refus, timeout, vague, élargissement est tracé (réutilise `mission_assignments` + `asap_dispatch_notifications` + `booking_matching_decisions` ; ajoute ce qui manque). L'admin doit pouvoir rejouer l'histoire d'une recherche (`AiDispatchCenter`/`MatchingInsightsCenter` existants — branche-les sur le nouveau flux).
- **Mode rendez-vous** (consigne 10) : à la confirmation d'un planifié, remplace l'assignation d'office silencieuse par une OFFRE avec TTL long (config, ex. 30 min, patron Uber Reserve) envoyée au meilleur candidat (score d'abord), avec escalade ; à défaut d'acceptation avant échéance (config), assignation d'office au meilleur (comportement actuel en repli) + notification. Sociétés : si le client a choisi une société, l'offre va à la société (ses dispatchers sont notifiés) et l'assignation interne suit le chantier société existant ; sinon workers et indépendants concourent individuellement.

**Acceptation** : tests bout en bout — booking immédiat confirmé → offre au plus proche en ligne du bon métier ; pas de réponse en 20 s → offre au suivant (jamais le même deux fois, TOUJOURS le même métier) ; vagues élargies tracées ; broadcast final : deux accepts simultanés → un seul gagnant (verrou), le perdant reçoit « offre annulée » ; acceptation → booking confirmé + presence busy + autres modals fermés ; planifié → offre TTL long puis repli assignation d'office. Idempotence du job d'escalade prouvée.

## Lot 5 — Surfaces prestataire : modal 20 s sur le home, web et natif (consignes 6, 12)

- **Mobile (`mobile/provider`)** : le modal d'offre s'affiche PAR-DESSUS le `DashboardScreen` (home de l'espace terrain) dès qu'une offre arrive (realtime prioritaire, push en réveil, polling en repli) : plein écran, compte à rebours circulaire 20 s calé sur `expires_at` serveur, son + vibration, métier + distance + adresse approximative + rémunération, boutons Accepter / Refuser. **Ressuscite `AssignmentOfferScreen` (orphelin complet avec compte à rebours) comme base, monte-le réellement** (modal sur la pile terrain), et supprime les écrans orphelins morts restants si tu restructures. Accepter → navigation directe vers le détail mission ; refuser/timeout → fermeture + le serveur escalade. L'offre doit AUSSI apparaître dans `MissionInboxScreen` tant qu'elle est active (source de vérité polling). Brancher enfin `useLiveMissionUpdates`.
- **Web prestataire** : même flux en Livewire — un composant global sur le dashboard prestataire (echo sur `user.{id}` + repli `wire:poll` court) affiche le même modal 20 s avec accept/refus. Parité de comportement avec le mobile.
- **Client — écran d'attente** (web `AsapSearch` + surface client mobile, natif ou WebView selon l'existant que tu vérifieras) : état temps réel de la recherche (vague, « recherche d'un prestataire près de chez vous »), annulation possible, et transitions propres vers Lot 6.
- Ajouter les alias `POST /api/provider/devices/*` vers le contrôleur d'enregistrement device existant, et enregistrer le token push côté app prestataire au login.

**Acceptation (tests qui PRESSENT)** : une offre simulée (émission realtime mockée) fait apparaître le modal sur le Dashboard ; presser Accepter appelle l'endpoint et navigue vers la mission ; presser Refuser ferme et appelle decline ; à expiration serveur le modal se ferme seul ; un prestataire sans le bon métier ne reçoit jamais le modal. Web : le modal apparaît et fonctionne (test Livewire). Aucun écran orphelin ajouté — joignabilité prouvée en pressant.

## Lot 6 — Aucun prestataire trouvé : les sorties de secours (consigne 8)

- Quand la recherche immédiate atteint sa deadline globale sans acceptation (`waysForward()` existant à brancher réellement) : l'écran client (web + mobile) propose TROIS choix :
  1. **Continuer à attendre** : relance une recherche (nouvelles vagues, prestataires redevenus éligibles inclus, exclusions remises à zéro sauf refus explicites récents) ;
  2. **Convertir en rendez-vous** : bascule `booking_mode` en planifié, choix d'un créneau, et le flux planifié du Lot 4 prend le relais — sans re-saisir la commande ni repayer ;
  3. **Annuler** : annulation propre + remboursement/libération du paiement selon le flux Stripe existant (le paiement attend l'assignation : vérifie qu'aucune capture n'a eu lieu avant assignation, sinon rembourse).
- Notifications client à chaque étape (recherche relancée, convertie, annulée). L'admin voit les recherches échouées (compteur + liste dans le centre de dispatch).

**Acceptation** : tests — timeout global → les trois choix s'affichent (web et mobile) ; « attendre » relance et peut aboutir ; « convertir » crée le RDV sans double paiement ; « annuler » ne laisse ni mission fantôme, ni offre active, ni argent capturé.

## Lot 7 — Réglages, observabilité et cohérence finale (consignes 10, 12)

- **Toute la mécanique en config** (`config/dispatch.php` consolidé) : TTL offre immédiat (20 s défaut, par métier), TTL offre planifié, rayons des vagues, deadline globale, taille de la dernière vague, fraîcheur de position exigée. Aucun nombre magique en dur.
- **Centre de dispatch admin** à jour : recherches en cours et historiques, chaîne d'offres par mission (qui, quand, refus/timeout, distances), simulateur (« pour ce booking, qui serait candidat et dans quel ordre » — le patron du tab Simuler de `MatchingInsightsCenter` existe), compteurs no-candidate. Console admin mobile : les mêmes données via descripteur/rapport (le moteur `app/Admin/Console` existe).
- **Seeders de démonstration** : après `migrate:fresh --seed`, un scénario complet est jouable — des prestataires semés en ligne avec positions autour d'une adresse de démo, métiers/zones catalogue remplis, `asap_enabled` sur au moins un métier — pour dérouler à la main : commande immédiate → modal 20 s → refus → escalade → acceptation → mission.
- Nettoie ce que l'unification rend mort (anciens chemins de dispatch court-circuités, ancien formulaire de réservation, écrans orphelins remplacés) — tu as le droit de supprimer, `migrate:fresh` est prévu.

**Acceptation** : `migrate:fresh --seed` puis le scénario de démo se déroule sans toucher au code ; changer le TTL en config change le compte à rebours réel ; le centre admin raconte l'histoire complète d'une recherche.

---

## BOUCLE DE VÉRIFICATION (à exécuter après CHAQUE lot — ne t'arrête que quand tout est vert)

1. `php artisan test` — suite COMPLÈTE, zéro échec.
2. `vendor/bin/phpstan` (ou la commande du projet) SANS argument de chemin — zéro erreur.
3. Base jetable : `php artisan migrate:fresh --seed` — zéro erreur, puis vérifier en base que les tables clés du lot sont PEUPLÉES (le piège classique du dépôt : un module complet dont personne ne crée les lignes).
4. Mobile : `tsc` + jest sur `mobile/provider` (et `mobile/client` si touché) — zéro échec ; les tests de joignabilité PRESSENT.
5. Le parcours manuel du lot (décrit dans son Acceptation) est réalisable de bout en bout sur la base fraîchement semée.
6. Cocher la checklist ci-dessous ; toute case non cochée = on reboucle.

## CHECKLIST FINALE — les 13 consignes (l'arrêt n'est autorisé que tout coché)

- [ ] 0. UN SEUL formulaire de réservation : `/dashboard/client/rendez-vous/nouveau` présente le parcours `/commander`, identique en immédiat et en rendez-vous, identique web et mobile
- [ ] 1. Immédiat → prestataires les plus proches ET en ligne uniquement (Presence v2 + position fraîche)
- [ ] 2. Dispatch par localisation/position GPS réelle (pas seulement zones déclarées)
- [ ] 3. Patrons Uber/Bolt/Heetch/Takeaway appliqués (séquentiel + TTL + vagues + broadcast final + audit)
- [ ] 4. Timeout → passage au suivant, TOUJOURS du même métier
- [ ] 5. Zone + métier adéquats garantis dans la requête candidate (jamais un peintre en babysitting)
- [ ] 6. Modal home screen 20 s accept/refus, refus ou timeout → prestataire suivant (mobile ET web)
- [ ] 7. Zone et type analysés dès la réservation (chaîne order_drafts → bookings réparée)
- [ ] 8. Sans prestataire : attendre encore / convertir en RDV / annuler-rembourser
- [ ] 9. Inscription : métiers et zones issus du catalogue admin ; le prestataire ne reçoit que ce qu'il a sélectionné
- [ ] 10. Moteur unifié pour tous rôles/sous-rôles, immédiat ET rendez-vous
- [ ] 11. L'admin choisit au catalogue quels métiers font de l'immédiat (`asap_enabled` par métier×zone)
- [ ] 12. Fonctionne sur web ET natif mobile, côté client et côté prestataire
