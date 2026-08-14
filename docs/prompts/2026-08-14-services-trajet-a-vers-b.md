# Services de trajet — point A → point B, façon Uber / Bolt / Heetch (catalogue, parcours mission, KYC)

Tu travailles sur le monorepo CleanUx : marketplace multi-services (nettoyage, peinture, babysitting, toiture…), Laravel 11 + Livewire côté web, monorepo Expo/React Native sous `mobile/` (`mobile/client`, `mobile/provider`, package partagé `mobile/shared`). Base MySQL en prod, tests PHPUnit sur SQLite.

**MISSION : AJOUTER à la plateforme les services qui emmènent quelqu'un d'un point A à un point B — taxi, VTC, dépannage-remorquage, transport — sans rien retirer ni modifier de ce qui existe.**

La plateforme est multi-métiers mais **mono-adresse de bout en bout** : `order_drafts.address/lat/lng` → `bookings.address/destination_lat/destination_lng` → mission. Un métier de trajet y est donc mal servi : le parcours mission suppose un lieu unique, exige deux codes à six chiffres, et la clôture est refusée si le prestataire n'est pas à 250 m du **point de départ**.

## Méthode de travail imposée

- **ANALYSE LE CODE EN ENTIER, surtout les parties dont tu as besoin. NE TE FIE SURTOUT PAS à `docs/` : elle est ancienne et contredit le code.** La vérité est dans `app/`, `routes/`, `database/migrations/`, `config/`, `mobile/`.
- **LA CONSIGNE DOMINANTE EST : NE RIEN CASSER.** On AJOUTE des options. Le cycle actuel commande → acceptation → en route → sur place → codes → clôture → avis reste **strictement intact** pour tous les autres métiers, et le catalogue actuel ne change pas de comportement. Aucune colonne, table, route, méthode ni statut existant n'est supprimé ou renommé ; toute colonne ajoutée est nullable ou à défaut neutre.
- **BOUCLE DE TRAVAIL OBLIGATOIRE** : travaille lot par lot ; après CHAQUE lot, exécute la batterie de vérification (en bas de ce document). Si quelque chose échoue, corrige et reboucle sur le lot. **Ne t'arrête que lorsque les 9 consignes de la checklist finale sont TOUTES cochées** — suite de tests complète verte, PHPStan propre, `migrate:fresh --seed` propre, mobile vert, parcours manuels réalisables.
- Un lot = une séquence de commits cohérente sur `main`. Ne pas surveiller la CI sans demande.
- La suite tourne sur SQLite, la prod sur MySQL strict : pas de SQL vendor-specific ; `lockForUpdate()` est un no-op sous SQLite. Noms d'index ≤ 64 caractères. Attention : sous SQLite, un identifiant inconnu devient une **chaîne littérale** — une colonne mal nommée ne fait pas échouer le test, elle le fait passer pour une mauvaise raison.
- PHPStan à lancer **SANS argument de chemin**.
- Livewire ne rejoue pas `mount()` : chaque action publique revérifie ses gardes ; toute propriété publique servant de garde porte `#[Locked]`.
- Encadre chaque run d'un `git status` et **n'édite aucun fichier pendant qu'une suite tourne** — le dépôt a déjà connu 1560 échecs pour un fichier corrompu en session.
- **Un test d'interdiction exige un contrôle positif.** Sans témoin qui prouve que le chemin fonctionne quand il doit fonctionner, un test « ceci est refusé » passe au vert en mesurant une panne.

## Les 9 consignes (toutes OBLIGATOIRES)

1. Dans le catalogue, l'admin peut poser des questions de **LOCALISATION** dans le parcours d'un métier. Deux d'entre elles — un départ et une arrivée — décrivent un trajet.
2. Quand un parcours porte ces deux localisations, **le système comprend tout seul que c'est un trajet** et **calcule la distance et l'itinéraire dès la commande**.
3. Un métier de trajet déroule un **SECOND parcours mission**, inspiré d'Uber/Bolt/Heetch : le prestataire accepte, arrive chez le client, **aucun code de début**, la mission démarre quand le client monte, la carte affiche la route vers le point B, **aucun code de fin**, la course se termine à l'arrivée.
4. À la fin de la course, le prestataire repasse de **occupé** à **en ligne**.
5. Le parcours mission actuel et le catalogue des autres métiers ne changent PAS. Deux parcours mission distincts coexistent, et ils ne peuvent pas se croiser.
6. À l'inscription, un prestataire qui choisit un métier à deux localisations doit fournir son **permis de conduire** dans le KYC.
7. L'admin peut cocher **« règles taxi »** sur un métier ; le prestataire doit alors prouver que son **véhicule a moins de 4 ans**.
8. Un prestataire dont le dossier est incomplet ne reçoit pas les missions **du métier concerné**, et continue de recevoir celles des autres métiers.
9. La tarification à la distance est une **option par métier** : soit le forfait actuel, soit le prix au kilomètre.

## Synthèse concurrents — les patrons à appliquer

**Uber / Bolt / Heetch (VTC)** :
- Deux adresses saisies AVANT le prix : départ (souvent la position du téléphone) et arrivée. Le prix est annoncé sur la base de la distance et de la durée estimées, jamais découvert après.
- Le conducteur accepte, se rend au point de prise en charge, **signale son arrivée**, puis **attend** — deux à cinq minutes selon la ville — avant de pouvoir déclarer le passager absent et facturer des frais d'annulation.
- **Aucun code, aucun QR** : la course démarre d'un geste du conducteur quand le passager est à bord, et se termine d'un geste à destination. La preuve n'est pas un code, c'est la trace GPS.
- La carte du conducteur affiche l'itinéraire vers le point de dépose ; le passager suit la même course en temps réel.
- Le conducteur est occupé pendant la course, redevient disponible à la seconde où elle se termine.
- **Documents du conducteur** : permis de conduire recto-verso en cours de validité, avec date d'expiration saisie ; **certificat d'immatriculation (carte grise)** qui porte la date de première immatriculation ; **attestation d'assurance** du véhicule. Le véhicule est **déclaré** (marque, modèle, plaque, année) et l'âge est **calculé**, pas jugé à l'œil ; les documents expirés bloquent automatiquement le compte tant qu'ils ne sont pas renouvelés.

**Ce qu'on retient pour CleanUx** : deux points saisis dans le parcours de commande existant, un prix distance en option, un second cycle de vie mission sans code, une seconde session de suivi vers le point B, et des exigences documentaires **dérivées du métier déclaré** — mécanisme qui existe DÉJÀ dans `ProviderDocumentRequirements` et qu'il suffit d'étendre.

## État des lieux vérifié (exploration exhaustive du code — les lignes exactes peuvent avoir bougé, re-vérifie au besoin)

**Catalogue.** `trades` cumule ses colonnes sur cinq migrations. Flags de comportement existants : `is_active`, `requires_certification`, `requires_insurance_proof`, `is_b2b_default`, `is_personal_default`, `requires_quote_by_default`, `requires_site_visit`, `allows_scheduled`, `allows_asap`, `allows_bundle`. **Aucun flag de véhicule ni de transport.** Le formulaire métier est partagé par le trait `app/Support/Livewire/Concerns/Admin/ManagesTradeForm.php` (21 champs éditables) et le partiel `resources/views/livewire/admin/partials/trade-form-fields.blade.php`. Écrans : `/admin/catalogue` → `CountryCenter` → `ZoneCenter` → `CatalogCenter` (`/admin/catalogue/{country}/{zone}`), et `QuestionnaireBuilder` sur `/admin/parcours/{trade}`. L'activation et le prix d'un métier dans une zone sont **la même ligne** `trade_zone_pricing` — **absence de ligne = fermé** (trait de test `tests/Feature/Dispatch/Concerns/OuvreLeCatalogue.php`).

**Questions.** Tables `questions`, `question_options`, `question_conditions`, `question_steps`, révisions dans `trade_form_revisions`. Les types vivent dans `app/Support/Domain/QuestionType.php` — une **classe de constantes**, pas un enum PHP ; la colonne `questions.type` est un `string(30)` libre. Treize types, dont **`address`** — rendu par `resources/views/livewire/order-engine/questions/address.blade.php`, qui est **un simple `<input type="text">`** : pas d'autocomplétion, pas de géocodage, pas de lat/lng, aucun impact tarifaire. **Il n'existe aucun type `location`, `position`, `map` ni `geo`.** Un second système legacy subsiste (`trades.booking_form_schema` + `TradeFormSchema`, six types) servi par `/api/client/trades/{trade}/form-fields` et consommé par personne côté mobile — ne t'en occupe pas, ne le casse pas.

**Commande.** `App\Livewire\OrderEngine\OrderJourney` (≈1740 lignes) sert le web ET le mobile client : il n'existe **aucun écran de commande natif**, l'app cliente ouvre une WebView sur `/commander?mode=…` (voir `mobile/client/src/screens/components/HomeActionsSheet.tsx`). L'adresse vit au niveau du panier (`order_drafts.address`, `lat`, `lng`, `postal_code`, `service_zone_id`), résolue par `updatedAddress()` → `GeocodingService::geocode()` → `ZonePricingResolver::resolveZone()` ; les méthodes `addressSuggestions()`, `chooseAddressSuggestion()`, `useMyPosition()`, `resolveGeographyFromCoordinates()` existent déjà et sont à réutiliser. Les réponses sont stockées dans `order_draft_answers` (une ligne par question visible, `answer_value` JSON), puis recopiées dénormalisées dans `bookings.trade_form_answers`. `OrderConfirmationService::confirm()` crée **une réservation par métier** puis appelle `DispatchEngine::dispatchBooking()` — **porte amont unique du dispatch**.

**Prix.** `app/Services/OrderEngine/PricingEngine.php` est la source unique : base (zone sinon métier) + modificateurs + valeurs numériques × coefficients, puis multiplicateurs × coefficient de mode × coefficient de zone, arrondi UNIQUE, puis plancher/plafond de zone. **La distance n'entre nulle part dans un prix.** `service_zones.travel_surcharge` est un forfait de zone lu seulement par le moteur legacy. À noter : `config/pricing_v2.php` connaît déjà une variable `distance_km` et un ajustement `per_unit_cents`, mais ce moteur-là n'est pas celui du parcours de commande.

**Mission.** `App\Support\Domain\MissionStatus` : `planned|assigned|en_route|arrived|started|paused|completed|cancelled`, avec des gardes de transition explicites (`canSetEnRoute`, `canSetArrived`, `canStart`, `canFinish`). `App\Support\Domain\BookingStatus` : `en_attente|confirme|en_route|sur_place|termine|annule|refuse`. `MissionLifecycleService` orchestre : `setEnRoute()` (ouvre une session de suivi, notifie, SMS) → `setArrived()` qui **génère le code de début ET le code de fin d'un coup**, les envoie par SMS et par notification → `validateStartCode()` → `validateEndCode()` → `completeMission()` : idempotent, exige la **checklist obligatoire** complète, croise la position via `OnSiteVerifier`, capture le paiement Stripe, crée la ligne de versement, passe la réservation à `termine` (ce qui débloque l'avis client), produit le rapport et remplit le pointage. Un second mécanisme de code existe pour la présence : `PresenceCodeService` sur `trip_tracking_sessions`.

**⚠️ TROIS PIÈGES VÉRIFIÉS QUI CASSERAIENT LA COURSE EN SILENCE :**
1. **`OnSiteVerifier` compare la position au point A.** `MissionLifecycleService::verifyOnSite()` passe `mission.destination_lat/lng`, c'est-à-dire l'adresse d'intervention — le point de PRISE EN CHARGE. Clôturer une course au point B serait refusé pour éloignement, avec le message « Vous semblez être à 8,3 km du lieu de l'intervention ». Le lieu attendu du geste doit devenir un paramètre explicite.
2. **`setArrived()` émet les deux codes avant tout le reste.** Il faut brancher AVANT de générer, pas nettoyer après : un SMS parti ne se rattrape pas, et le module SMS plafonne à cinq messages par heure et par numéro (au-delà, `sms_messages.status = rate_limited`, en silence).
3. **`CancellationFeeCalculator::isNoShow()` exige `scheduled_date`**, absent des commandes immédiates : il rend donc **toujours `false`**, et aucun prestataire ne peut aujourd'hui déclarer un client absent sur une course.

**Suivi.** Deux systèmes coexistent, aucun n'a été supprimé : `trip_tracking_sessions` / `trip_tracking_points` (v2 — statuts `enroute|arrived|in_mission|ended|cancelled`, snapshot de destination, geofence 150 m avec auto-transition, ETA, broadcast `mission.position` et `mission.eta` sur `private-mission.{id}`) et `mission_tracking_sessions` / `mission_positions` (legacy web). **Le client suit déjà son prestataire** : API `/api/client/bookings/{booking}/tracking` et `/trail`, écrans web Leaflet (`ClientLiveTrackingMap`, `MissionLiveTracking`) et mobile `react-native-maps` (`MissionTrackingScreen`). **Il n'existe AUCUN service de routing avec géométrie** : `GeocodingService::distance()` (Mock/Google/Mapbox — Mapbox tape déjà Directions Matrix) et `EtaService` (Google Distance Matrix, repli haversine à 30 km/h) ne rendent que distance et durée, jamais une polyligne. **L'écran de trajet du prestataire mobile n'a pas de carte** : `mobile/provider/src/screens/TrackingScreen.tsx` affiche un placeholder textuel, commentaire à l'appui.

**Présence.** `provider_presence` (v2 : `online|busy|on_break|offline`, position, heartbeat 60 s, stale 5 min). `occupé` est posé par `DispatchEngine::onAccepted()` **à l'acceptation** ; le retour `en ligne` passe par `BookingObserver` → `PresenceAutoTransitioner::bookingEnded()` quand la réservation devient `termine`, en résolvant l'intervenant par `Booking::intervenant()` (la MISSION passe devant la réservation). **La consigne 4 fonctionne donc déjà** — ton travail est de la PROUVER sur le parcours course, pas de la réécrire.

**KYC — le point d'extension exact.** La vraie table de documents est **`provider_onboarding_documents`** (la table `kyc_documents` mentionnée dans `docs/DATABASE_SCHEMA.md` **n'existe pas**). Ses types sont des constantes sur `App\Models\ProviderOnboardingDocument` : `identity_card`, `passport`, `residence_permit`, `tax_id`, `insurance`, `diploma`, `criminal_record`, `other`. **Les exigences sont DÉRIVÉES DES MÉTIERS DÉCLARÉS** par `app/Services/Onboarding/ProviderDocumentRequirements::for(User)` : `trades.requires_insurance_proof` → assurance, `trades.requires_certification` → diplôme, `config('onboarding_documents.criminal_record_trades')` → casier. Les métiers viennent du pivot `trade_user`. L'étape `document_upload` du parcours Onboarding v2 lit `requiredTypesFor()` via `DocumentUploadValidator` : **ajouter un type suffit à bloquer le parcours d'inscription**. Colonnes `expires_at` (DATE) et `metadata` (JSON) : elles **existent, sont castées, et ne sont JAMAIS écrites** — `isExpired()` n'est appelé nulle part, un document d'identité périmé reste « approved » pour toujours. `config/kyc.php:requirements_by_country` est du **config mort**, lu par personne : ne t'appuie pas dessus.

**Verrou de dispatch.** `CandidateFinder::baseQuery()` impose dans le SQL même : jointure `trade_user` sur le métier de la réservation, `users.is_active`, `provider_profiles.status = 'active'`, **`verification_status = 'verified'`**, type de prestataire, société choisie, exclusion de qui a déjà une offre vivante, et catalogue ouvert. `verification_status` n'est écrit `verified` que si `can_mark_verified` (`ProviderDossierSummary` : aucun bloquant et aucun document en attente). **L'ACCEPTATION d'une offre, elle, ne revérifie rien** aujourd'hui. Angle mort connu : un prestataire peut être `active` (application pleinement accessible) sans être `verified` — il ne reçoit alors AUCUNE offre, sans que rien ne le lui dise.

**Flotte.** `fleet_vehicles` porte déjà `brand`, `model`, `year`, `plate` (unique), `vehicle_type`, `registered_country`, **`registered_at` (DATE)**, `insurance_expires_at`, `control_technique_expires_at`, `current_provider_id`, `organization_account_id`. `fleet_certifications` accepte déjà le type **`driver_license`** avec `expires_at` et `document_path` — **mais `document_path` n'est écrit par aucun code, et il n'existe aucune saisie côté prestataire** : la seule voie de création est un admin via API. `CertificationExpiryScanner` (cron quotidien 05:00) sait faire basculer `active → expiring_soon → expired` et **réactive** en cas de renouvellement. **La flotte n'est reliée ni au dispatch ni à l'onboarding** : un permis expiré n'empêche rien. Il n'existe ni VIN, ni carte grise, ni photo de véhicule.

**À VÉRIFIER TOI-MÊME avant les lots concernés** : l'idempotence de `TripTrackingService::startSession()` par réservation (une course a besoin de DEUX sessions successives) ; le comportement exact de `AdminOnboardingDocumentsCenter` face à un type de document qu'il ne connaît pas ; et le fait que `resources/views/tracking/shared.blade.php` (lien de suivi public signé) n'a **aucune carte**.

---

## Lot 1 — Catalogue : la question de localisation et la case « règles taxi » (consignes 1, 7)

Objectif : l'admin peut décrire un trajet dans le parcours d'un métier, et déclarer qu'un métier obéit aux règles taxi — sans qu'aucun métier existant ne change.

- **Nouveau type de question `location`** : `QuestionType::LOCATION = 'location'` ajouté à `all()`. **Le type `address` reste strictement inchangé** — ne le « modernise » pas, des métiers en production l'utilisent.
- Migration : `questions.location_role` (string 20, nullable), valeurs `pickup` ou `dropoff`. Nulle pour tout autre type.
- `QuestionnaireBuilder` (`/admin/parcours/{trade}`) : quand le type est `location`, l'administrateur choisit le rôle ; on refuse deux `pickup` actifs ou deux `dropoff` actifs sur un même métier, avec un message clair. Parité API obligatoire : `Api\Admin\JourneyBuilderController` accepte le type et le rôle (le constructeur de parcours existe aussi en natif dans `mobile/provider/src/admin/catalogue/JourneyBuilderScreen.tsx`).
- **Nouveau partiel** `resources/views/livewire/order-engine/questions/location.blade.php` : autocomplétion, géocodage et « utiliser ma position », en réutilisant `GeocodingService` et les méthodes déjà écrites dans `OrderJourney`. Mapping ajouté à `QuestionRenderer::partial()`.
- **Contrat de réponse** — c'est un contrat, pas un détail : `['label' => string, 'lat' => float, 'lng' => float, 'postal_code' => ?string, 'place_id' => ?string]`. `PricingEngine::questionImpact()` doit l'ignorer comme il ignore `address` ; `QuestionnaireValidator::checkWayOut()` doit l'exempter de la porte de sortie, comme `photo` et `address`.
- Migration : **`trades.taxi_rules`** (boolean, défaut **false**), plus `trades.route_rules_since` et `trades.taxi_rules_since` (timestamps nullables). Ces deux dates sont posées au moment où la règle devient vraie pour le métier, et elles **font courir la période de grâce** du lot 7 : sans elles, activer une règle couperait le service de tous les prestataires du métier le jour même. Champ exposé dans `ManagesTradeForm`, dans `trade-form-fields.blade.php` et dans `App\Admin\Resources\TradeResource` (console admin mobile).
- **Source unique** `app/Support/Domain/TradeRouteRules.php` : `estUnTrajet(Trade): bool` = le métier porte une question `location` ACTIVE de rôle `pickup` ET une de rôle `dropoff`. Plus `questionDepart()`, `questionArrivee()`, et un scope `Trade::scopeTrajet()`. **Aucune colonne booléenne dénormalisée** : le défaut dominant de ce dépôt est « deux notions pour un même événement », et une colonne cache maintenue à la main finirait par contredire les questions.

**Acceptation** : un métier existant est rendu et tarifé à l'identique (test témoin) ; un métier neuf portant deux questions `location` est reconnu comme trajet ; deux `pickup` sont refusés ; l'admin coche « règles taxi » et la date d'activation est enregistrée ; le constructeur de parcours mobile affiche et enregistre le nouveau type (test qui PRESSE).

## Lot 2 — La commande : deux points, distance et itinéraire (consigne 2)

Objectif : commander un trajet capture deux positions géocodées, et le client voit la distance et la durée avant de payer.

- Migrations : `order_drafts` et `bookings` reçoivent `dropoff_address`, `dropoff_lat` (decimal 10,7), `dropoff_lng`, `dropoff_postal_code`, `dropoff_place_id`, `route_distance_m`, `route_duration_s`, `route_source` — **toutes nullables**.
- **Le point A alimente les colonnes d'adresse qui existent déjà.** La réponse `pickup` écrit `order_drafts.address/lat/lng/postal_code/service_zone_id` par le chemin actuel : la zone, le catalogue, la preuve de disponibilité, le dispatch de proximité et la geofence continuent alors de fonctionner **sans une ligne de modification**. Le point B, lui, va dans les colonnes `dropoff_*`. Et il faut l'écrire noir sur blanc dans les commentaires : **`destination_lat/lng` reste le point A**, c'est-à-dire le lieu de l'intervention — le nom trompe, c'est exactement le genre d'écart qui produit un bug qu'on ne voit pas.
- Sur un métier de trajet, l'étape d'adresse générique du parcours n'est plus posée : la question `pickup` EST l'adresse. Ne demande pas deux fois la même chose au client.
- **Nouveau `app/Services/Geo/RoutingService.php`**, agnostique comme `GeolocationV2` : distance et durée via `GeocodingService::distance()` (Mock/Google/Mapbox déjà en place), géométrie (polyligne) via Directions quand le fournisseur sait la donner, **repli ligne droite** sinon. `route_source` dit lequel a répondu. **Soft-fail intégral** : un routing indisponible ne doit jamais empêcher une commande d'aboutir.
- `OrderConfirmationService::createBooking()` recopie `dropoff_*` et `route_*` sur la réservation ; `blockers()` refuse la confirmation d'un métier de trajet dont le point B n'est pas résolu, avec un message qui dit quoi faire.

**Acceptation** : commander un métier de trajet demande deux localisations, affiche distance et durée avant paiement, et crée une réservation portant les deux points et la route ; commander un métier ordinaire suit exactement le parcours d'aujourd'hui, étapes comprises ; le mobile client (WebView) voit la même chose que le web, puisque c'est le même composant.

## Lot 3 — Prix à la distance, en option par métier × zone (consigne 9)

Objectif : l'admin choisit, métier par métier et zone par zone, entre le forfait actuel et le prix au kilomètre.

- Migration `trade_zone_pricing` : `distance_pricing_enabled` (boolean, défaut **false**), `pickup_fee_cents`, `price_per_km_cents`, `price_per_minute_cents`, `included_km`.
- `ZonePricingResolver::pricingContext()` les expose ; `PricingEngine::quoteItem()` ajoute la composante distance **seulement** si le drapeau est vrai ET qu'une route existe. L'arrondi unique et les bornes min/max de zone restent exactement où ils sont.
- Fourchette d'estimation : quand la route est connue, la distance n'ajoute pas d'incertitude ; quand le routing a échoué, retombe sur les règles d'écart existantes et dis-le au client plutôt que d'annoncer une fausse précision.
- Admin : champs dans `TradeZonePricingManager` (`/admin/trades/{trade}/pricing`), sur la ligne de `CatalogCenter`, dans l'API catalogue admin et dans l'écran mobile correspondant.

**Acceptation** : **test de devis témoin — la sortie de `PricingEngine` est identique au centime** pour tous les métiers dont la distance n'est pas activée ; un métier de trajet avec la distance activée facture prise en charge + kilomètres (+ minutes le cas échéant) et l'affiche AVANT confirmation ; basculer le drapeau depuis l'admin change le prix sans déploiement.

**Tranché après coup (2026-08-14) — le trajet et les multiplicateurs.** La première implémentation
versait la distance dans la somme du service, donc elle rencontrait TOUS les multiplicateurs, y
compris ceux qui n'expriment que notre ignorance. La règle retenue sépare les deux notions :

- le trajet prend les **multiplicateurs de prix** — majoration de l'immédiat, coefficient de zone,
  multiplicateurs venus des réponses. C'est le modèle Heetch/Bolt/Uber : une course de nuit majore
  ses kilomètres, pas seulement sa prise en charge ;
- le trajet ne prend **aucun élargissement d'incertitude** — ni les +15 % du questionnaire raccourci
  de l'immédiat, ni le repli d'un « je ne sais pas » sans borne. Une distance mesurée n'a pas de
  fourchette, et le contraire s'affichait : une course de 20 km était annoncée « entre 34,45 € et
  39,62 € » quand les deux bornes portaient les mêmes kilomètres.

C'est exactement ce que demandait déjà le tiret « fourchette d'estimation » ci-dessus ; le code en
avait dérivé. Plancher et plafond de zone portent sur le total **trajet compris** : ce plancher
existe pour couvrir un déplacement, l'appliquer avant le trajet le facturerait deux fois.

## Lot 4 — Le second parcours mission : la course (consignes 3, 4, 5)

Objectif : une course se déroule sans aucun code, et le parcours terrain actuel n'est jamais emprunté par une course — ni l'inverse.

- **Discriminant** : `Booking::estUneCourse()` = `dropoff_lat` ET `dropoff_lng` renseignés. Une seule notion, stockée là où elle est lue, décidée à la confirmation et figée avec la réservation. Ne redemande pas au catalogue ce que la réservation sait déjà.
- **Nouveau `app/Services/Missions/RideLifecycleService.php`.** Il RÉUTILISE les primitives de `MissionLifecycleService` — gardes d'assignation, historique, et surtout `completeMission()` pour l'argent, la capture, le versement, le passage de la réservation à `termine` et le déblocage de l'avis. **Ne duplique aucune logique d'argent.**
- `setArrived()` : sur une course, **aucun code de début ni de fin n'est généré ni envoyé**. Le prestataire signale simplement qu'il est au point de prise en charge. Branche AVANT la génération (piège 2 ci-dessus).
- `demarrerLaCourse()` : `arrived → started`, sans code, quand le client monte. La réservation avance comme aujourd'hui (`sur_place`) — **n'invente pas de nouveau statut de réservation**, rien n'est prêt à le lire.
- `terminerLaCourse()` : `started → completed` au point B, sans code. **`OnSiteVerifier` doit recevoir le point B comme lieu attendu du geste** : ajoute un paramètre de destination explicite à `verifyOnSite()` plutôt qu'une seconde politique de proximité (piège 1 ci-dessus). Une course n'a pas de checklist obligatoire, mais garde le contrôle : il ne coûte rien et protège d'un gabarit mal attaché.
- Routes prestataire : `POST /api/provider/missions/{mission}/ride/start` et `/ride/complete`. Les routes `begin` et `complete` classiques répondent **409 explicite** sur une course, et les routes de course répondent 409 sur une mission classique. **Les deux parcours ne peuvent pas se croiser** — c'est la consigne 5, et elle se prouve dans les deux sens.
- Web prestataire : `app/Livewire/Employe/MissionActions.php` propose « Client à bord » puis « Terminer la course » à la place des champs de code, sur les seules courses.
- Présence : `occupé` à l'acceptation, `en ligne` à la clôture — comportement EXISTANT (`DispatchEngine::onAccepted()` et `PresenceAutoTransitioner::bookingEnded()`). Ton travail est de le **prouver** sur le parcours course par un test, pas de le réécrire.

**Acceptation** : une course se déroule de bout en bout sans qu'aucun code à six chiffres ne soit créé ni envoyé (vérifie la table, pas seulement l'écran) ; le paiement est capturé, l'avis client devient possible, le prestataire redevient `en ligne` ; **et le témoin** : une mission classique exige toujours ses deux codes et sa geofence au point A. Sans ce témoin, le test « pas de code » passerait au vert en mesurant une panne.

## Lot 5 — Le suivi : second segment et route affichée (consigne 3)

Objectif : le client suit son prestataire jusqu'à lui, puis suit la course jusqu'au point B ; les deux voient la route.

- Segment 1, l'approche vers le point A : session `trip_tracking_sessions` existante, **inchangée**.
- Segment 2, la course A → B : au démarrage, ouverture d'une **seconde session** avec destination = point B et `metadata.leg = 'ride'`. L'API `/api/client/bookings/{booking}/tracking` rendant la session ACTIVE, le client bascule tout seul. **Vérifie d'abord l'idempotence par réservation de `TripTrackingService::startSession()`** : si elle empêche une seconde session, lève-la proprement plutôt que de détourner la première.
- Geofence à l'arrivée au point B : elle PROPOSE la fin de course, elle ne clôture **jamais** toute seule — la clôture encaisse le paiement, et un encaissement ne se déclenche pas sur un relevé GPS.
- Polyligne : produite par `RoutingService`, portée par la session, servie par les endpoints de suivi, et tracée partout — Leaflet côté web client, `Polyline` de `react-native-maps` côté mobile client, et **carte enfin montée** sur `mobile/provider/src/screens/TrackingScreen.tsx`, aujourd'hui un placeholder textuel. C'est l'exigence « la carte affiche la route pour le point B ».

**Acceptation** : pendant l'approche, le client voit le prestataire venir vers lui ; dès que la course démarre, les deux cartes basculent sur la route vers B et l'ETA vise B ; sans fournisseur de directions, la ligne droite s'affiche et rien ne casse.

## Lot 6 — KYC : permis de conduire et preuves véhicule (consignes 6, 7)

Objectif : on ne conduit pas sans permis, et sous règles taxi on ne conduit pas une voiture de six ans.

- Nouveaux types sur `ProviderOnboardingDocument` : **`driving_license`**, **`vehicle_registration`** (carte grise / certificat d'immatriculation), **`vehicle_insurance`**. Libellés et contraintes de prise de vue dans `config/onboarding_documents.php`, dans le style déjà adopté par ce fichier — dire les contraintes AVANT la photo coûte moins cher que de rejeter le document trois jours plus tard.
- `ProviderDocumentRequirements::for()` : un métier de trajet déclaré → permis + assurance du véhicule ; un métier `taxi_rules` → en plus carte grise et véhicule déclaré conforme. C'est le seul endroit à toucher : l'étape `document_upload` d'Onboarding v2 en hérite sans modification.
- **`expires_at` et `metadata` enfin écrits** à l'upload : le prestataire saisit la date de validité de son permis et de son assurance. Un scanner fait expirer les documents périmés, sur le modèle éprouvé de `CertificationExpiryScanner` (qui sait aussi RÉACTIVER après renouvellement).
- **Déclaration du véhicule** côté prestataire, web et natif, en réutilisant `fleet_vehicles` (`current_provider_id`, et `organization_account_id` pour une société) : marque, modèle, plaque, type, **date de première immatriculation**. L'âge est **calculé** depuis `registered_at`, jamais saisi. `config/fleet_v2.php` : `taxi_rules.max_vehicle_age_years = 4`, surchargeable par pays — les villes n'ont pas toutes la même règle.
- Onboarding v2 : validateur `VehicleDeclarationValidator`, ajouté au parcours semé. **Il passe trivialement quand aucun métier `taxi_rules` n'est déclaré** — un parcours qui bloquerait un jardinier sur une déclaration de véhicule serait pire que le trou qu'on comble.
- Revue admin : les nouveaux types dans `AdminOnboardingDocumentsCenter` (vérifie qu'il n'a pas de liste blanche en dur), plus un encart véhicule affichant l'âge calculé et le verdict.

**Acceptation** : s'inscrire sur un métier de trajet réclame le permis ; ajouter « règles taxi » réclame en plus carte grise, assurance et véhicule déclaré ; un véhicule de six ans est refusé avec un motif explicite ; **et le témoin** : un prestataire d'un métier non concerné ne voit AUCUN document supplémentaire, ni sur le web ni sur le natif.

## Lot 7 — Le verrou : ne dispatcher que ceux qui sont en règle (consigne 8)

Objectif : sans permis, on perd les courses — et seulement les courses.

- `CandidateFinder::baseQuery()` : clause d'éligibilité **par métier de la réservation**, écrite **dans le SQL**, qui exclut qui n'a pas les documents approuvés et non expirés, ou pas de véhicule conforme, quand le métier l'exige. L'invariant vit dans la requête, jamais dans un `if` qu'un repli désarmera le jour où il vide la liste — c'est exactement ce qui était arrivé aux filtres de métier.
- **Même contrôle à l'acceptation** (`MissionDispatchService::guardAcceptable()`), qui aujourd'hui ne revérifie rien : une offre partie reste acceptable indéfiniment.
- **Période de grâce** : `config('onboarding_documents.trade_requirements_grace_days', 30)`, comptée depuis `trades.route_rules_since` / `taxi_rules_since`. Un prestataire déjà inscrit continue de recevoir ses missions pendant le délai, et est réclamé pendant tout ce temps.
- **Le prestataire sait pourquoi** : bandeau explicite web et mobile listant ce qui manque et la date butoir. L'angle mort connu de ce dépôt est le compte actif mais jamais `verified`, qui ne reçoit plus rien sans qu'on le lui dise — ne le reproduis pas.

**Acceptation** : un prestataire peintre + taxi sans permis reçoit la peinture et **jamais** la course ; il dépose son permis, l'admin approuve, il devient candidat au métier de course ; passé la grâce, un dossier incomplet cesse de recevoir le métier concerné, **et lui seul**.

## Lot 8 — Règles taxi en course : attente au point A, client absent (consigne 7)

- `config/cancellation.php` : `no_show.ride_grace_minutes = 5`.
- `CancellationFeeCalculator::isNoShow()` : sur une course, le décompte part de **l'arrivée au point A**, pas de `scheduled_date` — absent en immédiat, ce qui rend le no-show impossible aujourd'hui (piège 3). Branche ajoutée ; le comportement classique reste identique.
- Écran prestataire : compte à rebours de cinq minutes après l'arrivée, puis « Client absent », branché sur `POST /api/provider/missions/{mission}/no-show` **qui existe déjà**. L'annulation après acceptation utilise `POST /api/provider/missions/{mission}/cancel`, également existant.

**Acceptation** : arrivé au point A, le prestataire attend cinq minutes puis peut déclarer l'absence ; les frais suivent la politique existante ; il ne reste ni mission fantôme, ni offre active, ni argent capturé à tort.

## Lot 9 — Démonstration, parité mobile et cohérence finale

- Seeder ajoutant un **métier de démonstration « course »** — deux questions `location`, `taxi_rules`, tarification à la distance, ligne `trade_zone_pricing` active — pour qu'un `migrate:fresh --seed` donne un scénario jouable à la main. **Ajout pur : aucun métier existant n'est modifié.**
- Console admin mobile à parité : type `location` et rôle dans `JourneyBuilderScreen`, `taxi_rules` et tarifs distance dans `CatalogZoneTradesScreen`.
- Passe finale : rien d'orphelin, rien de mort, batterie complète.

**Acceptation** : après `migrate:fresh --seed`, dérouler à la main — commander une course, voir la distance et le prix, accepter côté prestataire, arriver, démarrer sans code, suivre la route vers B, terminer sans code, constater le retour `en ligne` et l'avis client possible.

---

## BOUCLE DE VÉRIFICATION (après CHAQUE lot — ne t'arrête que quand tout est vert)

1. `git status` avant et après. Aucune édition de fichier pendant qu'une suite tourne.
2. `php artisan test` — suite **COMPLÈTE**, zéro échec. Ne jamais rediriger une suite vers `tail`.
3. `vendor/bin/phpstan` **SANS argument de chemin** — zéro erreur.
4. `php artisan migrate:fresh --seed` — zéro erreur, puis vérifier **EN BASE** que les tables du lot sont réellement peuplées : le piège classique de ce dépôt est un module complet dont personne ne crée les lignes. Noms d'index ≤ 64 caractères.
5. Mobile : `tsc` + `jest` sur `mobile/provider` et `mobile/client` — zéro échec ; les tests de joignabilité **PRESSENT** les boutons depuis le navigateur monté, ils ne lisent jamais la source.
6. Non-régression ciblée, à chaque lot : **une mission classique complète** (deux codes exigés, geofence au point A, checklist bloquante) et **un devis témoin inchangé au centime**.
7. Le parcours manuel du lot, déroulé de bout en bout sur la base fraîchement semée.

## CHECKLIST FINALE — les 9 consignes (l'arrêt n'est autorisé que tout coché)

- [ ] 1. L'admin peut poser deux questions de localisation (départ + arrivée) dans le parcours d'un métier
- [ ] 2. Deux localisations ⇒ le système comprend « trajet » et calcule distance + itinéraire dès la commande
- [ ] 3. Parcours course : acceptation → arrivée → **aucun code** → client à bord → carte vers B → **aucun code** → fin
- [ ] 4. Fin de course ⇒ le prestataire repasse `occupé` → `en ligne`
- [ ] 5. Le parcours mission classique et le catalogue des autres métiers sont **inchangés**, et les deux parcours ne se croisent jamais (témoins verts dans les deux sens)
- [ ] 6. Inscription sur un métier de trajet ⇒ permis de conduire exigé dans le KYC
- [ ] 7. Case « règles taxi » ⇒ véhicule de moins de 4 ans prouvé (véhicule déclaré + carte grise + assurance), attente de 5 min et client absent
- [ ] 8. Un dossier incomplet ne reçoit pas les missions **du métier concerné**, et reçoit toujours celles des autres
- [ ] 9. Tarification à la distance disponible **en option** par métier × zone, forfait inchangé par défaut
