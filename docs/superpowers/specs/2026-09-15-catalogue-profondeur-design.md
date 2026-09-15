# Catalogue « Profondeur » dans l'application cliente — conception

- **Date** : 2026-09-15
- **Statut** : conception validée en conversation (approche A : une seule source de filtrage)
- **Maquette de référence** : https://claude.ai/code/artifact/a0becddb-6cdf-43f2-b7cb-1adcf1218a0c (page « Catalogue »)

## 1. Objectif

Remplacer, pour les modes « Intervention immédiate » et « Prendre rendez-vous », l'ouverture directe
du moteur de commande web par un écran natif de catalogue : l'iceberg en fond, une ligne de sonde
verticale dont seule la colonne de repères défile, le repère centré sélectionné, et une feuille fixe
qui montre le vrai prix plancher du métier et un bouton pour commander.

L'écran ne propose QUE ce que le moteur de commande accepte : même filtre, même zone, même calcul de
prix. Le prix annoncé est le minimum que le moteur calcule sans réponse. Ses limites connues sont
écrites : la zone inconnue (§11), et les cas où le premier devis web part d'autres données — durée
choisie, réponses enregistrées, trajet mesuré, zone du panier changée depuis le web — et peut donc
différer (« Écarts connus avec le web », en fin de document).

## 2. Parcours

1. Accueil → bouton « Réserver un service » → la feuille actuelle (`HomeActionsSheet`) s'ouvre,
   **inchangée** : trois modes, deux indicateurs, cinq raccourcis.
2. Carte « Intervention immédiate » → `navigate('Catalogue', { mode: 'asap' })`.
3. Carte « Prendre rendez-vous » → `navigate('Catalogue', { mode: 'scheduled' })`.
4. Carte « Plusieurs services » → **inchangée** : `EmbeddedModule` sur `/commander?mode=bundle`.
5. Écran `Catalogue` : titre de l'en-tête = libellé du mode (`home_actions.intervention_immediate`
   ou `home_actions.prendre_rendez_vous`, clés existantes).
6. Le client fait défiler la colonne ; le métier centré est sélectionné ; la feuille affiche son
   nom, son secteur, sa position dans la liste COMPLÈTE des métiers proposés pour ce mode
   (« Bâtiment & rénovation · 2 sur 16 » en rendez-vous, « … · 2 sur 4 » en immédiat) et son prix
   plancher.
7. Bouton « Commander » → `navigate('EmbeddedModule', { path: '/commander/{sector.slug}/{trade.slug}?mode={mode}', title: trade.name })`.
   Ce chemin est un chemin interne absolu : `WebViewAuthController::validateInternalPath` l'accepte
   déjà, et `OrderJourney::mount(?string $sector, ?string $trade)` le lit (slugs + `?mode=`).

## 3. Règles métier

### 3.1 Filtre — identique au moteur de commande

- Secteurs : `Sector::active()->ordered()`.
- Métiers : `is_active = true`, `sector_id` = le secteur, `Trade::servableEnMode($mode, $zoneId)`,
  ordre `sort_order`.
- Un secteur sans aucun métier servable n'est pas renvoyé (comme `OrderJourney::sectors()`).
- `servableEnMode` : en `scheduled` aucun filtre supplémentaire ; en `asap` le métier doit porter
  `allows_asap = true` et, **si la zone est connue**, avoir une ligne `trade_zone_pricing` active
  avec `asap_enabled = true` dans cette zone.

### 3.2 Zone du client

- `App\Services\Client\ClientPlaceService::parDefaut($client)?->service_zone_id`, soit la même
  source que `OrderJourney::preremplirDepuisLeCarnet()`.
- Aucun lieu par défaut, ou lieu sans zone → `zoneId = null` : même comportement que le web avant
  saisie d'adresse. La réponse le signale (`zone_known: false`).

### 3.3 Prix plancher

- Zone connue et ligne `trade_zone_pricing` active avec `base_rate_cents > 0` → `base_rate_cents`.
- Sinon `trades.base_price_cents > 0` → `base_price_cents`.
- Sinon `null` : l'écran affiche « Prix selon vos réponses ».
- `hourly` = `trades.hourly_billing` : le montant se lit par heure.
- Le prix est **hors taxe** (règle posée par le commit 943c3f2b1). Libellé « hors taxe », comme le
  récapitulatif web (« Sous-total hors taxe »).
- Le catalogue `service_catalogs` (`ServiceCatalog.base_price`) n'est **pas** utilisé : le moteur de
  commande ne le lit nulle part.

### 3.4 Devise

- Zone connue → `ServiceZone::deviseDeLaZone()`.
- Sinon → `config('fx.base_currency', 'EUR')`.

### 3.5 Libellés

Traduits dans la langue de la requête (`SetLocale` / `LocaleResolver`) via
`translate('name')`, `translate('short_description')`, avec traductions chargées d'avance.

## 4. API

`GET /api/client/catalogue?mode=asap|scheduled&lang=fr|nl|en|es|it|de`

- Groupe `Route::middleware(['auth:sanctum', 'verified'])->prefix('client')` de
  `routes/api/client.php` (celui de `budget`, `protection`, `order-intent`) : pas de filtre de
  rôle, un contact d'entreprise commande aussi pour lui-même.
- `mode` obligatoire, `in:asap,scheduled` ; `bundle` ou valeur inconnue → 422.
- Non authentifié → 401.
- `lang` facultatif : la langue affichée par l'application ; inconnue ou désactivée, elle est ignorée
  et la langue du compte s'applique (voir l'amendement, « Libellés »).

Réponse 200 :

```json
{
  "mode": "asap",
  "zone_known": true,
  "currency": "EUR",
  "sectors": [
    {
      "slug": "batiment-renovation",
      "name": "Bâtiment & rénovation",
      "icon": "hammer",
      "trades": [
        {
          "slug": "plomberie",
          "name": "Plomberie",
          "icon": "wrench",
          "short_description": "Fuites, débouchages, sanitaires",
          "floor_price_cents": 8500,
          "hourly": false
        }
      ]
    }
  ]
}
```

## 5. Serveur — composants

- **`App\Services\OrderEngine\CatalogueServable`** (nouveau) : la seule définition du filtre.
  - `secteurs(?string $mode, ?int $zoneId): Builder` — secteurs actifs ordonnés ayant au moins un
    métier servable ;
  - `metiers(int $sectorId, ?string $mode, ?int $zoneId): Builder` — métiers servables du secteur ;
  - `contraindreLesMetiers(Builder $query, ?string $mode, ?int $zoneId): Builder` — la contrainte
    seule, pour un `withCount` ou un `whereHas` ;
  - `zoneDuClient(User $client): ?int` — zone du dernier panier ouvert s'il porte une adresse, sinon
    du lieu par défaut ;
  - `plancher(Trade $trade, ?TradeZonePricing $ligne, string $mode): array{cents: ?int, horaire: bool}`
    — le minimum du moteur sans réponse ;
  - `devise(?int $zoneId): string`.
- **Extraits sans requête, pour que le plancher lise la même source que le web** :
  `ZonePricingResolver::contexteDeLaLigne` (depuis `pricingContext`), `HourlyRateResolver::tarifDeLaLigne`
  (depuis `tarifCatalogue`), `OrderDraftManager::dernierPanierOuvert` (depuis `resumeOrCreate`) ; les
  méthodes d'origine gardent leur signature et leur résultat.
- **`OrderJourney::sectors()` et `OrderJourney::trades()`** passent par `CatalogueServable` et
  gardent ce qui leur est propre (`withCount`, `active_providers_count`, traductions). Leur
  comportement est figé par un test **avant** la modification.
- **`App\Http\Controllers\Api\Client\CatalogueController`** (nouveau, invocable) : valide `mode`,
  résout la zone, assemble la réponse. Éviter les requêtes N+1 : traductions et lignes de zone
  chargées en une fois.

## 6. Mobile — composants

Tous les écrans et styles passent par le système de design : `useThemeColors`, `spacing`, `radius`,
`typography`, `GlassSurface`, `Screen toile`, `Button`, `Icon`, `formatMontant`. Aucune couleur ni
espacement en dur ; nuit et jour traités ; `useReducedMotion` respecté.

- **`mobile/client/src/catalogue/useCatalogue.ts`** : hook react-query, clé
  `['catalogue', mode, langue]` (requête avec `lang`), `staleTime` 5 min, types de la réponse.
- **`mobile/client/src/catalogue/iconeDuMetier.ts`** : correspondance des noms d'icônes du serveur
  vers Ionicons, avec repli `briefcase-outline` (le repli du modèle `Trade` est `briefcase`).
  Correspondance de départ, dont chaque cible est vérifiée par un test contre `Ionicons.glyphMap` :

  | serveur | Ionicons |
  |---|---|
  | hammer | hammer-outline |
  | sparkles | sparkles-outline |
  | tree, leaf | leaf-outline |
  | users, user-group | people-outline |
  | shield-check | shield-checkmark-outline |
  | car | car-outline |
  | paint-roller | brush-outline |
  | broom | trash-bin-outline |
  | wrench | build-outline |
  | bolt | flash-outline |
  | window | grid-outline |
  | home | home-outline |
  | truck | cube-outline |
  | arrow-up | arrow-up-outline |
  | pencil-square | create-outline |
  | (vide ou inconnu) | briefcase-outline |

- **`mobile/client/src/screens/catalogue/CatalogueScreen.tsx`** : `Screen toile`, états
  chargement / erreur / vide, compose `LigneDeSonde` et `FeuilleDuMetier`.
- **`LigneDeSonde.tsx`** : `ScrollView` verticale, `snapToInterval = 88`, `decelerationRate = 'fast'`,
  marges haute et basse = (hauteur visible − 88) / 2 mesurées par `onLayout` pour que le premier et
  le dernier repère puissent se centrer. Sélection = `Math.round(y / 88)` bornée, mise à jour pendant
  `onScroll` (`scrollEventThrottle = 16`) seulement quand l'index change. Un tap sur un repère appelle
  `scrollTo({ y: index * 88, animated: !mouvementReduit })` et le sélectionne. Trait pointillé
  vertical au centre ; bande de lueur fixe au niveau du repère central, couleur `theme.glow`
  (transparente en jour, comme le thème le veut).
- **`RepereDeSonde.tsx`** : nœud sur la ligne (sélectionné : plein, halo animé désactivé en
  mouvement réduit), fil vers la case, case de verre (nom + prix court). Côté : les métiers d'un
  secteur de rang pair à droite, impair à gauche ; l'étiquette du secteur sur le premier métier du
  secteur, du côté opposé à ses cases.
- **`FeuilleDuMetier.tsx`** : panneau `GlassSurface strong` fixe en bas (pas de geste de glissement),
  icône, nom, « secteur · rang sur total », prix plancher, bouton principal « Commander ».
- **Navigation** : `RootStackParamList.Catalogue: { mode: 'asap' | 'scheduled' }`, écran déclaré
  dans la pile personnelle de `RootNavigator` (en-tête visible, titre selon le mode).
- **`HomeActionsSheet`** : seules les cartes `asap` et `scheduled` changent de destination.
- **i18n** : nouvelles clés `catalogue.*` dans les six catalogues (fr, nl, en, es, it, de), jetons
  identiques d'une langue à l'autre :
  - `catalogue.des_montant` — « dès :montant hors taxe »
  - `catalogue.des_montant_par_heure` — « dès :montant/h hors taxe »
  - `catalogue.prix_selon_vos_reponses` — « Prix selon vos réponses »
  - `catalogue.commander` — « Commander »
  - `catalogue.position` — « :secteur · :rang sur :total »
  - `catalogue.repere_accessible` — « :metier, :prix »
  - `catalogue.aucun_metier_immediat` — « Aucun métier n'accepte l'intervention immédiate pour votre adresse. »
  - `catalogue.prendre_rendez_vous_plutot` — « Prendre rendez-vous »
  - `catalogue.chargement_impossible` — « Le catalogue n'a pas pu être chargé. »

## 7. États

- Chargement : squelettes (`Skeleton`) à la place des repères et de la feuille.
- Erreur : `ErrorState` avec « Réessayer » (`refetch`).
- Mode immédiat sans métier servable : message `catalogue.aucun_metier_immediat` et bouton
  `catalogue.prendre_rendez_vous_plutot` → `navigation.replace('Catalogue', { mode: 'scheduled' })`.

## 8. Accessibilité

- Chaque repère : `accessibilityRole="button"`, `accessibilityState={{ selected }}`,
  `accessibilityLabel` = `catalogue.repere_accessible`.
- L'ordre de lecture suit l'ordre du catalogue ; la feuille n'est pas masquée aux lecteurs d'écran.
- Mouvement réduit : pas d'animation de défilement programmé ni de halo.
- Cibles tactiles ≥ 44 pt (repère entier de 88 pt, bouton principal).

## 9. Tests

Tout test de refus a son témoin positif.

### Serveur (PHPUnit, `tests/Feature/Api/Client/` et `tests/Feature/OrderEngine/`)

1. **Caractérisation `OrderJourney`** (écrit et vert AVANT le refactor) : en `asap` avec zone, les
   secteurs et métiers proposés ; en `scheduled`, idem.
2. `asap` exclut un métier `allows_asap = false` — témoin : un métier `allows_asap = true` du même
   secteur est inclus.
3. `asap` avec zone connue exclut un métier dont la ligne de zone a `asap_enabled = false` — témoin :
   ligne `asap_enabled = true` incluse.
4. Un secteur sans métier servable n'apparaît pas — témoin : il apparaît dès qu'un métier le devient.
5. Prix plancher : ligne de zone `base_rate_cents` prioritaire ; repli `base_price_cents` ; `null`
   quand les deux manquent ou valent 0.
6. Devise de la zone ; repli `fx.base_currency` sans zone.
7. `mode=bundle` → 422 ; sans jeton → 401 — témoin : jeton client valide → 200.
8. **Parité** : pour un même client, mode et zone, les slugs renvoyés par l'API égalent ceux
   d'`OrderJourney`.
9. Budget de requêtes : pas de N+1 (nombre de requêtes borné, indépendant du nombre de métiers).

### Mobile (jest-expo, `mobile/client/__tests__/`)

1. `CatalogueScreen` rend les métiers renvoyés, avec l'étiquette de secteur au premier métier.
2. Alternance des côtés par secteur — témoin : deux métiers d'un même secteur du même côté.
3. Défilement simulé de 3 × 88 → la feuille affiche le 4ᵉ métier.
4. Tap sur un repère → sélection + `scrollTo` appelé ; en mouvement réduit `animated: false`.
5. « Commander » → `navigate('EmbeddedModule', { path: '/commander/{secteur}/{metier}?mode=asap', ... })`.
6. Prix : montant + « /h » quand `hourly` ; « Prix selon vos réponses » quand `null`.
7. État vide en `asap` → bouton qui remplace l'écran par `mode: 'scheduled'`.
8. `HomeScreen.interaction.test.tsx` : `asap` et `scheduled` mènent à `Catalogue` ; `bundle`
   inchangé (ce test existant reste tel quel et doit passer).
9. `iconeDuMetier` : chaque cible existe dans `Ionicons.glyphMap` ; nom inconnu → repli.
10. Garde-fous existants verts : six langues, pas de français en dur, pas de clé orpheline, jetons
    de thème, suites complètes client et prestataire.

## 10. Hors périmètre

- Nettoyage des données de démo (libellés sans accents, métiers factices sans secteur).
- Le catalogue `service_catalogs` et ses prix.
- Le réglage Métiers/Secteurs de la maquette : l'application affiche les métiers avec les
  étiquettes de secteur.
- Le fondu en bord de colonne de la maquette : React Native n'a pas de masque CSS ; la colonne est
  bornée par l'en-tête et la feuille.
- Toute modification de « Plusieurs services », des indicateurs et des raccourcis de la feuille.
- L'espace société cliente (`ClientCompanySpace`), qui n'affiche pas `HomeActionsSheet`.

## 11. Risques

- **Refactor d'`OrderJourney`** : composant central du parcours de commande. Mitigation : test de
  caractérisation préalable, suite `OrderEngine` complète après modification.
- **Défilement aimanté Android** : `snapToInterval` et `onScroll` à valider sur l'émulateur
  (Pixel_10_Pro_XL) en plus des tests jest, qui ne simulent pas l'inertie.
- **Zone inconnue** : en `asap` sans lieu par défaut, un métier peut être proposé puis refusé par le
  moteur après saisie de l'adresse — c'est déjà le comportement du web ; la réponse expose
  `zone_known` pour que l'écran puisse le dire plus tard si besoin.

## Amendement — revue finale (2026-09-15)

Ce qui suit remplace §3.2, §3.3 et §3.5, les valeurs `floor_price_cents` et `hourly` de l'exemple §4,
et les points 5 et 8 des tests serveur de §9. Les descriptions de §1, §4, §5 et §6 sont corrigées en
place pour ne plus contredire le code.

### Pourquoi

§1 promet que l'écran « ne peut pas annoncer […] un prix que la commande contredirait ensuite ».
Mesuré sur l'appareil : la plomberie était annoncée « dès 85 € hors taxe » en natif, pendant que le
parcours web ouvert par « Commander » l'estimait à 111–127 € en immédiat (8500 × 1,30 = 11050 ;
× 1,15 = 12707). La règle de §3.3 (tarif de zone > 0, sinon prix du métier) était plus simple que le
moteur, et le contredisait. Et l'API prenait la zone du lieu par défaut, là où le web la prend du
dernier panier ouvert du client.

### Prix plancher — le minimum du moteur

- Le plancher est `PricingEngine::quoteItem($trade, collect(), [], $contexte)->minCents` : le devis
  du moteur SANS AUCUNE RÉPONSE, pour le mode et la zone du client. C'est le bas du premier devis web
  tant que le panier n'apporte ni réponses enregistrées, ni heures choisies, ni trajet mesuré ; dans
  ces cas le web part de ces données et les deux montants diffèrent (voir « Écarts connus avec le
  web »).
- Le contexte est celui du web : le mode demandé, et `ZonePricingResolver::contexteDeLaLigne` sur la
  ligne de zone ACTIVE (une ligne inactive vaut une absence de ligne, comme `lineFor`) — tarif de
  zone, coefficient, plancher et plafond de zone, tarif horaire. La majoration de l'immédiat entre
  donc dans le prix annoncé.
- Métier horaire avec un tarif horaire connu : le plancher est le prix d'UNE heure
  (`purchased_minutes = 60`), et `hourly = true` (« dès X/h »). `hourly` ne recopie plus le drapeau
  `hourly_billing` : un métier horaire sans tarif horaire retombe sur son forfait, `hourly = false`.
- Métier au devis obligatoire (`QUOTE_ONLY`), ou minimum ≤ 0 : `null` (« Prix selon vos réponses »).
- Toujours hors taxe ; `service_catalogs` n'est toujours pas lu. Les lignes de zone restent chargées
  en une requête : le calcul ne fait aucune requête par métier.

### Zone — l'ordre du web

- `OrderDraftManager::dernierPanierOuvert($client)` (le panier que `resumeOrCreate` reprend) : s'il
  porte une adresse non vide, sa `service_zone_id` fait foi — `null` compris.
- Sinon, la zone du lieu par défaut, comme avant.
- Le panier est lu, jamais créé.

### Libellés — la langue affichée

- L'application envoie la langue qu'elle affiche : `GET /api/client/catalogue?mode=…&lang=…`
  (`useCatalogue`), et la range dans la clé de cache `['catalogue', mode, langue]`.
- `LocaleResolver::resolveFromRequest` lit `?lang` en premier, puis la langue du compte (l'API n'a pas
  de session). Juste après un changement de langue, la réponse parle donc déjà la nouvelle langue, même
  si le compte n'est pas encore enregistré (`choisirLaLangue` prévient l'écran avant d'enregistrer).
- Une langue inconnue ou désactivée (`isSupported` : code exact parmi les locales activées de
  `config/i18n.php`) ne s'impose pas : la langue du compte reprend la main.
- Traductions toujours chargées d'avance, via `translate('name')` et `translate('short_description')`.

### Exemple §4 — valeurs corrigées

`GET /api/client/catalogue?mode=asap&lang=fr`, plomberie à 85 € sans ligne de zone : `floor_price_cents`
vaut `11050` (8500 × 1,30, la majoration de l'immédiat), `hourly` vaut `false`. En rendez-vous, sans
majoration, `floor_price_cents` vaut `8500`.

### Tests — remplacent §9 serveur, points 5 et 8

- **Parité avec le moteur** (`CatalogueServableTest`) : pour chaque cas, le plancher égale
  `quoteItem(...)->minCents` calculé avec `pricingContext` — (a) rendez-vous sans zone, (b) immédiat
  avec une ligne de zone active (tarif, coefficient, plancher de zone), (c) métier au devis obligatoire
  → `null`, (d) métier horaire au tarif de sa zone → une heure, (e) métier horaire sans zone → une heure
  au tarif de référence, (f) ligne inactive = absence de ligne ; témoin : le même métier donne deux
  planchers différents en immédiat et en rendez-vous (le mode atteint le moteur) ; sans aucun prix
  positif → `null`.
- **Parité avec le web** (`CatalogueApiTest`) : les slugs, ET le plancher d'un métier au forfait égal à
  `OrderJourney::quote()->minCents` pour le même client, la même zone, le même mode, sans réponse.
- **Zone** : un panier ouvert avec adresse dans une autre zone impose sa zone, comme le montage
  d'`OrderJourney` ; témoin : panier ouvert sans adresse → lieu par défaut ; témoin : panier qui n'est
  plus ouvert → ignoré, et aucun panier n'est créé.
- **Langue** : compte en français + `lang=nl` → libellé néerlandais ; témoin : `lang=xx` → langue du
  compte. Côté mobile, la requête qui suit un changement de langue porte la nouvelle.

### Écarts connus avec le web (antérieurs à cette branche, non corrigés ici)

- **Métier horaire.** Tant que le client n'a pas choisi de durée, `OrderJourney::quote()` reçoit
  `purchased_minutes` nul (`OrderJourney.php:705`, `heuresEnMinutes` :1689-1696 ; `heuresChoisies` part
  de `null` et n'est posé que par `choisirLesHeures`) : le premier devis web est le forfait, alors que la
  même vue affiche déjà le tarif « de l'heure » et une durée par défaut (`hours.blade.php:20-32`). Le
  catalogue, lui, annonce le prix d'une heure. `OrderDraftManager::reprice` (:204-216) ne transmet pas
  non plus les heures achetées.
- **Tarif horaire d'une ligne inactive.** `OrderJourney::tarifHoraireCents` (:1677-1686) passe par
  `HourlyRateResolver::tarifCatalogue`, qui ne filtre pas `is_active` : le web peut afficher un tarif
  horaire que ni son devis ni l'application n'appliquent.
- **Réponses enregistrées et trajet mesuré.** Le web recharge les réponses du panier
  (`loadAnswers`, :1761-1766) et ajoute le trajet mesuré quand la zone facture au kilomètre
  (`ZonePricingResolver::pricingContext` :66-70, `PricingEngine::quoteItem` :94-128) ; le catalogue
  calcule sans réponse et sans trajet.
- **Zone du panier changée depuis le web.** Une adresse saisie dans la vue web (`updatedAddress`), ou le
  rattachement au local d'une société (`rattacherAuLocalDeLaSociete`, :255-285, avec `?site=`), met à
  jour le panier ouvert sans prévenir l'application : le catalogue garde l'ancienne zone jusqu'à la fin
  de son cache (5 minutes).
