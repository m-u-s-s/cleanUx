# SP2 — Sélection du type de prestataire côté client (3 paliers) — Design Spec

**Date :** 2026-06-01
**Statut :** Design approuvé (avant plan d'implémentation)
**Programme :** « 4 relations client×prestataire », sous-projet **2 de 4**
**Branche prévue :** `feat/relations-sp2-client-selection` (off `main`)

---

## Contexte & objectif

SP1 (livré+mergé) a posé le socle : l'éligibilité prestataire accepte déjà un paramètre
`providerType` (`independent`/`company`/`any`, défaut `any`) prêt à recevoir une préférence client ;
le matching est trade-aware ; la société prestataire est tracée booking→mission.

SP2 donne au **client** le contrôle sur **qui** réalise la prestation, via **3 paliers** :

1. **Auto-match** *(tous)* — le client choisit un **type** (`independent`/`company`/`any`) ; le système
   assigne le meilleur prestataire éligible de ce type (style Uber).
2. **Re-réserver un favori** *(tous)* — re-book 1-clic d'un prestataire déjà utilisé (module
   `booking_favorites` existant).
3. **Choisir un NOUVEAU prestataire précis** *(réservé au PACK PREMIUM)* — recherche + sélection d'un
   prestataire précis (indépendant ou worker) via `BrowseProviders`.

**Objectif :** livrer la fonctionnalité **de bout en bout sur les deux surfaces (web + mobile)** :
champ de préférence, logique des 3 paliers + gating premium, comportement « presta précis indispo »,
UI web (`PrendreRendezVous`) + UI mobile (wizard `BookingStep*`), et **unification du portail société
`BookingHub` sur l'action canonique `CreateBookingAction`** (qui aujourd'hui perd le `service_catalog_id`).

### Décisions produit (validées)

- **Premium = `CustomerProfile::isPremium()`** (`plan_type === 'premium' && plan_status === 'active'`,
  déjà en place). Pas de plan au niveau `OrganizationAccount` → **identique pour particulier et société**
  (on regarde le `customerProfile` de l'utilisateur agissant).
- **Préférence de type** : `independent` / `company` / `any`, **défaut `any`** (sans friction ; la
  plupart des clients ne choisissent pas). En SP2, le palier « société » matche un **worker** d'une
  société ; choisir une **société comme entité réservable** (Bolt-like) = **SP3**.
- **Presta précis indisponible sur le créneau** : X dispo → assigné à X ; **X indispo → proposer les
  créneaux disponibles de X** (réserver plus tard avec X) **OU**, si le client est pressé, **repli
  auto-match du même type**. Décision exposée au client.
- **Favori vs choix premium** : un *favori* = prestataire déjà utilisé (accessible à tous) ; *choisir un
  nouveau* prestataire via la recherche = **premium**. Les deux posent `preferred_provider_user_id` ;
  seul le gating d'accès à la **découverte** diffère.
- **Périmètre** : un seul SP2 complet (backend + UI web + UI mobile + refacto BookingHub).

---

## Architecture & composants

Sept unités bornées.

### 1. Donnée : préférence + presta précis

- **Nouvelle colonne** `bookings.provider_type_preference` (string, défaut `'any'`, valeurs
  `independent`/`company`/`any`) + cast/constante. Migration idempotente.
- **Réutilisation** de `preferred_provider_user_id` (existe déjà, posé par le re-book de favori) pour
  le presta précis (favori OU choix premium).
- `Booking` : ajouter `provider_type_preference` au `$fillable` + un helper `prefersProviderType()`.

### 2. `CreateBookingAction` — l'entrée unique

`CreateBookingAction::execute()` reçoit (en plus de l'existant) `provider_type_preference` et un
`preferred_provider_user_id` optionnel, et les **persiste** sur la réservation. C'est le **seul**
chemin de création (web, mobile, portail société) — il garantit zone/marché/prix/catalog + la
sélection. *Dépend de :* l'existant `CreateBookingAction`.

### 3. `ProviderSelectionResolver` — paliers + gating *(nouveau service)*

Service unique qui, à partir de l'intention du client (type choisi, favori choisi, presta recherché)
et de son statut premium, **valide le palier et résout** ce qui est persisté :

- palier 1 (auto) : retourne `['type' => <pref>, 'preferred_provider_user_id' => null]` ;
- palier 2 (favori) : vérifie que le `preferred_provider_user_id` provient bien d'un
  `booking_favorites` du client → autorisé pour tous ;
- palier 3 (premium pick) : `abort_unless($user->customerProfile?->isPremium())` si le presta n'est
  pas un favori du client (découverte d'un **nouveau** presta). Vérifie que le presta choisi est
  éligible (actif/vérifié, couvre la zone, exerce le métier — via l'éligibilité SP1).

*Dépend de :* `CustomerProfile::isPremium`, `booking_favorites`, l'éligibilité SP1.

### 4. `PreferredProviderResolver` — comportement « indispo » *(nouveau service)*

Quand `preferred_provider_user_id` est posé, au moment du matching/dispatch :

- presta X **disponible** sur le créneau (`EmployeeAvailabilityService::employeeIsAvailableForSlot`)
  → **assigné à X** ;
- X **indisponible** → retourne un résultat structuré
  `['status' => 'unavailable', 'alternative_slots' => [...créneaux de X...]]` (créneaux dispo de X via
  l'availability) ; l'appelant (UI) propose alors : **réserver un créneau de X**, ou (si pressé)
  **repli auto-match du même type** (`provider_type_preference`).

Le **câblage matching** : si un presta précis est confirmé dispo → on l'assigne directement ; sinon le
matcher SP1 tourne avec le `providerType` de la préférence. *Dépend de :* `EmployeeAvailabilityService`
(SP1), l'availability (créneaux).

### 5. UI Web — `PrendreRendezVous`

Le formulaire de réservation web gagne une **étape/section « Prestataire »** :

- sélecteur de **type** (Indépendant / Société / Peu importe — défaut Peu importe) ;
- section **« Mes favoris »** : liste des `booking_favorites` du client → re-book 1-clic
  (`preferred_provider_user_id`) ;
- **(premium)** bouton **« Choisir un prestataire »** → ouvre/embarque `BrowseProviders` filtré
  (zone + métier + type) ; sélection pose le presta. Pour un non-premium : ce bouton est masqué/disabled
  avec un libellé d'upsell ;
- **flux indispo** : à la confirmation, si X indispo, afficher les créneaux de X + un bouton « je suis
  pressé : meilleur dispo ». *Dépend de :* `BrowseProviders`, `booking_favorites`, le resolver.

### 6. UI Mobile — wizard `BookingStep*`

Le wizard Expo (`BookingStep1Service` → `…4Scheduling`) gagne une étape **« Prestataire »** (ou une
section dans une étape existante) avec **les mêmes 3 paliers + le flux indispo**, via l'API exposée par
`CreateBookingAction`/les resolvers. Parité totale avec le web. *Dépend de :* l'API SP2 + les écrans
mobiles existants (`mobile/client/src/screens/booking/`).

### 7. Unification `BookingHub` (portail société)

`app/Livewire/ClientCompany/BookingHub.php` est **refactoré pour passer par `CreateBookingAction`**
(au lieu de son `Booking::create` ad-hoc qui perd le `service_catalog_id` — bug audit). Il hérite ainsi
zone/prix/mission/dispatch **et** la sélection de type/presta (les 3 paliers, gating premium inclus pour
une société premium). *Dépend de :* `CreateBookingAction`, le resolver.

---

## Flux de données (palier premium, presta indispo → repli)

1. Client premium choisit *type=société*, recherche et sélectionne le presta X (worker) via
   `BrowseProviders`.
2. `ProviderSelectionResolver` : premium OK + X éligible → persiste
   `provider_type_preference='company'`, `preferred_provider_user_id=X`.
3. `CreateBookingAction` crée la réservation ; `PreferredProviderResolver` : X indispo sur le créneau →
   renvoie les créneaux de X.
4. Le client est pressé → repli : le matcher SP1 tourne avec `providerType='company'` → assigne le
   meilleur worker société dispo ; la propagation SP1 trace l'org sur la mission.

*(Palier auto : pas de presta précis → matcher direct. Palier favori : presta = un favori, pas de
gating premium.)*

---

## Definition of Done

1. **Donnée** : `bookings.provider_type_preference` (migration idempotente, fillable, cast), réutilisation
   de `preferred_provider_user_id`.
2. **`CreateBookingAction`** persiste préférence + presta précis ; **seul chemin** de création (web,
   mobile, BookingHub).
3. **`ProviderSelectionResolver`** : 3 paliers résolus, gating premium appliqué (un non-premium ne peut
   pas découvrir/choisir un nouveau presta), favoris autorisés pour tous, presta choisi vérifié éligible.
4. **`PreferredProviderResolver`** : X dispo→assigné ; X indispo→créneaux de X ; repli auto-match du type
   si pressé. Câblé au matching (presta confirmé → assign direct ; sinon matcher SP1 avec le `providerType`).
5. **UI web** (`PrendreRendezVous`) : sélecteur type + favoris + (premium) recherche + flux indispo.
6. **UI mobile** (wizard) : mêmes 3 paliers + flux indispo, parité web.
7. **`BookingHub`** passe par `CreateBookingAction` (corrige la perte du `service_catalog_id`) avec la
   sélection.
8. **Tests** : backend (préférence→matching, 3 paliers + gating, indispo→créneaux/repli, BookingHub
   canonique avec service_catalog_id) + composants web (Livewire) + mobile (Jest). Chaque palier testé
   sur les deux surfaces.
9. **Gates** : suite complète verte, **PHPStan full** `[OK]`, Pint clean ; mobile typecheck + Jest verts.

---

## Limites de scope

**Dans le scope SP2 :** la préférence de type + son câblage au matching ; les 3 paliers + gating premium ;
le comportement presta-indispo ; l'UI web + mobile ; l'unification de `BookingHub` sur `CreateBookingAction`.

**Hors scope (sous-projets suivants) :**
- **SP3** — la **société comme entité réservable** (« Bolt-like ») : note/critères au niveau org,
  sélection d'une société précise dans la zone, dispatch interne société→worker. En SP2, le palier
  « société » = matcher un **worker** d'une société ; le palier premium « choix précis » porte sur un
  **individu** (indépendant ou worker), pas encore une société-entité.
- **SP4** — contrats B2B / partenaires.
- Refonte du module favoris ou de `BrowseProviders` (réutilisés tels quels) ; nouveau moteur de
  disponibilité (on réutilise `EmployeeAvailabilityService` + l'availability existante).

**Dépendances :** s'appuie sur SP1 (éligibilité `providerType`, propagation), `CreateBookingAction`,
`CustomerProfile::isPremium`, `booking_favorites`, `BrowseProviders`, `EmployeeAvailabilityService`, le
wizard mobile existant. Aucune dépendance à SP3/SP4 (ils dépendent de SP2).
