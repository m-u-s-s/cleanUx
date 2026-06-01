# SP3 — Société prestataire comme entité réservable (Bolt-like) — Design Spec

**Date :** 2026-06-01
**Statut :** Design approuvé (avant plan d'implémentation)
**Programme :** « 4 relations client×prestataire », sous-projet **3 de 4**
**Branche prévue :** `feat/relations-sp3-company-entity` (off `main`)

---

## Contexte & objectif

SP1+SP2 (livrés+mergés) ont posé : la traçabilité `provider_organization_id` booking→mission, la
préférence de type (`independent`/`company`/`any`), le palier premium « choisir un prestataire précis »
(individu via `BrowseProviders`), le gating premium (`CustomerProfile::isPremium`), et le matching
type-aware.

SP3 fait de la **société prestataire une ENTITÉ réservable** (façon Bolt) : un client premium peut
**choisir une société précise** dans sa zone selon sa **note**, et **la société dispatche** ensuite un
de ses employés. Aujourd'hui le palier premium « company » de SP2 ne sait choisir qu'un **worker
individuel** ; SP3 ajoute le choix d'une **société** (l'org), avec note au niveau société et un dispatch
interne société→worker.

### Décisions produit (validées)

- **Note société = agrégée des workers.** `note_société = Σ(rating_avg × rating_count) / Σ(rating_count)`
  sur les workers de l'org. Stockée/cachée sur `OrganizationAccount` (`rating_avg`, `rating_count`).
  **Pas de nouveau flux d'avis** — on réutilise `ProviderProfile.rating_avg`/`rating_count` (existants).
- **Auto-suggestion + réassignation société.** Quand une société X est choisie : la réservation porte
  `assigned_provider_organization_id = X`, et le système **pré-assigne le meilleur worker dispo de X**
  (le client a un nom tout de suite, expérience Bolt) ; la mission porte `provider_organization_id = X`.
  La société **garde la main** : son `DispatchCenter` (existant) peut **réassigner** un autre worker.
  La réservation n'est **jamais bloquée**.
- **Société sans worker dispo sur le créneau** : parité avec le flux indispo SP2 — proposer les créneaux
  où X a un worker dispo, **ou** repli « je suis pressé » (auto-match du type, n'importe quelle société).
- **Choix d'une société précise = PREMIUM** (`CustomerProfile::isPremium`, comme SP2). Le backend reste
  la frontière de sécurité.
- **Périmètre** : un seul SP3 complet (backend + UI web + UI mobile).

---

## Architecture & composants

Six unités bornées.

### 1. Note au niveau société *(agrégation)*

- `OrganizationAccount` gagne `rating_avg` (decimal) + `rating_count` (int) — migration idempotente +
  fillable/cast.
- `OrganizationRatingAggregator` (nouveau service) : `recompute(OrganizationAccount $org): void` calcule
  la moyenne pondérée des `ProviderProfile.rating_avg`/`rating_count` des workers de l'org
  (`OrganizationAccount::hasMany(User)` → leurs `providerProfile`), persiste `rating_avg`/`rating_count`
  sur l'org. Déclencheurs : (a) appel soft quand une note worker change (là où `ProviderProfile.rating_*`
  est mis à jour — hook léger, soft-fail) ; (b) commande `php artisan organizations:recompute-ratings`
  (cron-ready) pour le rafraîchissement de masse. *Dépend de :* `ProviderProfile.rating_*`, la relation
  org→workers.

### 2. Éligibilité société *(qui est réservable)*

- `EligibleCompaniesResolver` (nouveau service) : `forBooking(Booking $rdv): Collection<OrganizationAccount>`
  retourne les orgs `type = PROVIDER_COMPANY` ayant **≥1 worker éligible** pour la réservation (actif/
  vérifié, exerçant le métier `trade_user`, couvrant la zone, type `company_worker`) — réutilise
  l'éligibilité SP1 (`EmployeeAvailabilityService`) en regroupant par `provider_profiles.organization_account_id`.
  Trié par `rating_avg` société décroissant. Sert au **browse** (liste des sociétés) et au **check**
  (« la société choisie est-elle éligible ? »). *Dépend de :* l'éligibilité SP1, `OrganizationAccount`.

### 3. Matching scopé société + auto-suggestion

- L'éligibilité SP1 gagne un filtre org optionnel :
  `EmployeeAvailabilityService::eligibleEmployeesQuery(?int $zoneId, string $providerType='any', ?int $organizationId=null)`
  (et `sortedEligibleEmployeesForZone(..., ?int $organizationId=null)`) — `whereHas('providerProfile',
  fn => $q->where('organization_account_id', $organizationId))` quand fourni.
- Le matcher (web `SmartDispatchService::assignBestEmployee`, mobile `AiDispatchService::rankEmployees`) :
  si `booking.assigned_provider_organization_id` est posé → restreint les candidats aux workers de cette
  org, prend le meilleur dispo → `mission.lead_provider_user_id` + `provider_organization_id` = l'org.
  (Comme SP2 : un presta précis confirmé court-circuite ; ici c'est une org qui scope.)
- **Indispo société** : si aucun worker de X n'est dispo sur le créneau → renvoie un résultat structuré
  (créneaux où X a un worker, via le pattern `PreferredProviderResolver` étendu au niveau org dans un
  `PreferredCompanyResolver`) ; l'UI propose les créneaux **ou** le repli « pressé » (vide
  `assigned_provider_organization_id` → auto-match du type). *Dépend de :* SP1 (éligibilité), SP2 (pattern
  indispo).

### 4. Sélection + réservation d'une société *(gating premium)*

- `ProviderSelectionResolver` (SP2) étendu : accepte `assigned_provider_organization_id` en entrée ; le
  choix d'une **société** est gaté premium (sauf si déjà « favori » — hors scope SP3, donc premium requis),
  et valide que l'org est une `PROVIDER_COMPANY` **éligible** (via `EligibleCompaniesResolver`). Sortie
  enrichie : `{provider_type_preference, preferred_provider_user_id, assigned_provider_organization_id}`.
- `CreateBookingAction` (web/société) ET `CreateBookingFromApiAction` (mobile) persistent
  `assigned_provider_organization_id` depuis `$data` (la colonne `bookings.assigned_provider_organization_id`
  existe déjà — SP1). *Dépend de :* SP2 `ProviderSelectionResolver`, les 2 actions de création.

### 5. UI parité web + mobile *(browse-sociétés)*

- **Web** : un composant `BrowseCompanies` (Livewire, façon `BrowseProviders` mais pour les orgs) liste
  les sociétés éligibles de la zone avec **note société** + nb de prestataires ; embarqué dans le picker
  de `PrendreRendezVous` quand `provider_type_preference='company'` + premium (event `companySelected` →
  pose `assignedProviderOrganizationId`). `provider_type='independent'` garde le picker worker SP2.
- **Mobile** : un écran `BookingCompanySearchScreen` (dans la stack booking, comme `BookingProviderSearch`
  de SP2) liste les sociétés éligibles → sélection pose `assignedProviderOrganizationId` dans le contexte
  wizard. Gate premium (`is_premium`).
- **Endpoint API** : `GET /client/companies` (ou similaire) renvoyant les sociétés éligibles (id, nom,
  note, nb prestataires) pour le mobile + le web si besoin.
- Le `DispatchCenter` société (existant) gère la réassignation — **rien à construire**, on vérifie qu'il
  fonctionne sur les missions auto-suggérées (`provider_organization_id` rempli par SP3).

### 6. Tests *(parité)*

Backend : agrégation note (pondérée, recalcul), éligibilité société (orgs avec ≥1 worker éligible, tri
note), matching scopé org (auto-suggère un worker DE l'org, exclut les autres), indispo société (créneaux/
repli), sélection + gating premium (non-premium refusé de choisir une société ; org non-éligible refusée),
réservation pose `assigned_provider_organization_id` + mission `provider_organization_id`, réassignation
via DispatchCenter intacte. Composants web (Livewire) + mobile (Jest). Chaque palier testé web ET mobile.

---

## Flux de données (C2B/B2B premium, société choisie)

1. Client premium, `provider_type_preference='company'`, browse les sociétés éligibles de la zone (triées
   par note), choisit X.
2. `ProviderSelectionResolver` : premium OK + X = `PROVIDER_COMPANY` éligible → persiste
   `assigned_provider_organization_id = X`.
3. `CreateBookingAction`/`CreateBookingFromApiAction` crée la réservation ; le matcher scope les candidats
   aux workers de X, prend le meilleur dispo → mission `lead_provider_user_id` + `provider_organization_id=X`.
4. La société X voit la mission dans son `DispatchCenter` et peut **réassigner**.
5. *(X sans worker dispo → créneaux de X / repli « pressé ».)*

---

## Definition of Done

1. `OrganizationAccount.rating_avg`/`rating_count` (migration) + `OrganizationRatingAggregator`
   (pondéré) + commande de recompute + hook soft sur changement de note worker.
2. `EligibleCompaniesResolver` (orgs PROVIDER_COMPANY avec ≥1 worker éligible, trié note).
3. Éligibilité SP1 avec filtre org optionnel ; matcher (web + mobile) scope sur
   `assigned_provider_organization_id` → auto-suggère le meilleur worker de l'org ;
   `provider_organization_id` posé sur la mission.
4. Indispo société (créneaux / repli pressé), parité SP2.
5. `ProviderSelectionResolver` étendu (gating premium + validation org éligible) ;
   `CreateBookingAction` + `CreateBookingFromApiAction` persistent l'org choisie.
6. UI web (`BrowseCompanies` embarqué) + mobile (`BookingCompanySearchScreen`) + endpoint sociétés ;
   note société affichée. DispatchCenter réassignation vérifié.
7. Tests backend + web + mobile, chaque palier sur les 2 surfaces.
8. Gates : suite complète verte, **PHPStan full** `[OK]`, Pint clean ; mobile tsc + Jest verts.

---

## Limites de scope

**Dans le scope SP3 :** note société agrégée ; éligibilité société ; matching scopé org + auto-suggestion ;
indispo société ; sélection société + gating premium ; UI browse-sociétés web + mobile + endpoint ;
persistance `assigned_provider_organization_id` sur les 2 chemins de création.

**Hors scope (sous-projets suivants / non désirés) :**
- **SP4** — contrats B2B / partenaires (routage contractuel, tarifs négociés, work orders).
- Un **nouveau flux d'avis société** (on agrège les notes worker existantes).
- **Favoris société** (le choix société reste premium en SP3 ; un palier « favori société » serait un
  ajout ultérieur).
- Refonte du `DispatchCenter` société (réutilisé tel quel pour la réassignation).
- Le mode « assignation manuelle » par société (on a choisi auto-suggestion ; un mode configurable serait
  un ajout ultérieur).

**Dépendances :** s'appuie sur SP1 (éligibilité, `provider_organization_id`, `OrganizationType`,
`isProviderCompanyWorker`), SP2 (`ProviderSelectionResolver`, `PreferredProviderResolver`, le picker
web/mobile, `is_premium`), `OrganizationAccount::hasMany(User)`, `ProviderProfile.rating_*`, le
`DispatchCenter` société existant. Aucune dépendance à SP4.
