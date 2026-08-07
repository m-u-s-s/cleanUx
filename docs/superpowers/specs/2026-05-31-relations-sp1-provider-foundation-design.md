# SP1 — Socle prestataire : identité, matchabilité, traçabilité — Design Spec

**Date :** 2026-05-31
**Statut :** Design approuvé (avant plan d'implémentation)
**Programme :** « 4 relations client×prestataire » (C2I / C2B / B2I / B2B), sous-projet **1 de 4**
**Branche prévue :** `feat/relations-sp1-provider-foundation` (off `main`)

---

## Contexte & objectif

Brio est une marketplace Uber-like **multi-métiers** (nettoyage, peinture, babysitting, toiturier…)
où un **client** (particulier ou société multi-sites) engage un **prestataire** (indépendant ou
société multi-employés). Quatre relations doivent fonctionner : **C2I** (particulier→indépendant),
**C2B** (particulier→société), **B2I** (société→indépendant), **B2B** (société→société).

Trois audits code (2026-05-31) ont établi que **le schéma/les enums modélisent les 4 relations, mais
la couche métier ne les câble pas** :

1. **Matching aveugle au type.** `EmployeeAvailabilityService.php:14-18` filtre uniquement
   `where('role','employe')->where('is_active')` — jamais `provider_type`, `status`,
   `verification_status`. Indépendants et `company_worker` sont indistinguables. (MatchingV2 ajoute
   par-dessus un filtre **métier** correct via le pivot `trade_user` — `MatchingV2Service.php:104-112`.)
2. **Inscription cassée (décisif).** `CreateNewUser.php:81` pose `role='client'` pour tous,
   prestataires compris. `createProviderIndependent/Company` créent le `ProviderProfile` (bon
   `provider_type`, bonne org) mais ne mettent jamais `users.role='employe'` → le prestataire
   auto-inscrit est **invisible au matching**. La colonne legacy `role` n'a jamais été supprimée
   (`dropColumn` commenté dans `2026_05_27_000003`).
3. **Société prestataire jamais tracée.** `missions.provider_organization_id` existe en DB mais est
   **absent de `Mission::$fillable`** et **jamais écrit** ; le dispatch
   (`MissionDispatchService.php:204-208`) n'écrit que la personne (`lead_provider_user_id`). Les deux
   chemins booking→mission (`CreateBookingFromApiAction`, `ProcessRecurringBookings`) ne copient
   aucun champ prestataire. `bookings.assigned_provider_organization_id` est ajouté par une migration
   **en attente** (`2026_05_28_100014`). Conséquence concrète : `ProviderDashboard.php:35` filtre
   `provider_organization_id` (jamais rempli) → **dashboard société-prestataire toujours vide**.
4. **Société mal détectée.** `HasUserTypeChecks.php:68` : `isClientCompany()` renvoie `true` dès qu'il
   y a un `organization_account_id`, **sans regarder le `type` de l'org** → une société-prestataire
   est prise pour une société-cliente et mal routée (`isClientCompany` testé avant
   `isProviderCompanyWorker`).

**Objectif SP1 :** poser le **socle non négociable** — un prestataire du bon type/métier/org est
**matchable** et **tracé end-to-end** — sans encore construire le choix client riche. Après SP1 :
**C2I fonctionne réellement**, et C2B/B2I/B2B **enregistrent correctement type + société**.

### Décisions produit (issues du brainstorming, cadrent le programme — détail dans SP2/SP3/SP4)

- Le client **choisit explicitement** le type : `independent` / `company` / `any`. *(implémenté en SP2)*
- Une « société » est une **entité réservable façon Bolt** (sélectionnée dans la zone selon des
  critères), qui **dispatche** ensuite un de ses employés. *(implémenté en SP3)*
- **Paliers de sélection :** auto-match (tous) · re-réserver un **favori** (tous) · choisir un
  **nouveau** prestataire précis = **premium** (indep ou société). *(implémenté en SP2)*
- **B2B contractuel** (partenaire société sous contrat) = **SP4**.

SP1 ne construit aucun de ces parcours ; il rend la **donnée et le matching corrects et tracés** pour
qu'ils puissent s'appuyer dessus.

---

## Architecture & composants

Quatre unités bornées.

### 1. Éligibilité prestataire — source unique de vérité *(le cœur)*

Un **service d'éligibilité** unique remplace la requête de base `where('role','employe')` et compose
toutes les dimensions du multi-métiers :

- **est prestataire** actif + vérifié : jointure/`whereHas('providerProfile')` avec `provider_type`
  non nul, `status='active'`, `verification_status='verified'` ; **fin de la dépendance à la colonne
  legacy `role`** ;
- **du TYPE demandé** : paramètre `providerType ∈ {independent, company, any}` →
  `independent` ⇒ `provider_type ∈ {independent, individual}` ; `company` ⇒
  `provider_type = company_worker` ; `any` ⇒ pas de contrainte de type. *(En SP1 le paramètre vaut
  `any` par défaut puisque le choix client arrive en SP2 ; la signature et le filtre sont posés dès
  maintenant.)*
- **qui exerce le MÉTIER** : le candidat doit avoir, dans le pivot **`trade_user`**, le
  `booking.serviceCatalog.trade_id` (on **remonte** la logique déjà présente dans MatchingV2 au sein
  de l'éligibilité canonique, pour qu'elle s'applique partout, pas seulement en v2) ;
- **couvre la ZONE** + **est DISPO** : logiques existantes (`employeeCanCoverZone`, créneaux)
  conservées.

`EmployeeAvailabilityService::eligibleEmployeesQuery()` devient le point d'application de cette
éligibilité. **MatchingV2Service / AiDispatchService / SmartDispatchService délèguent déjà tous** à ce
service → **un seul point à corriger**, ils héritent automatiquement du filtre type + métier.

*Dépend de :* `provider_profiles`, `trade_user`, `service_catalogs.trade_id`, les zones.

### 2. Inscription & identité prestataire

- **Inscription corrigée** (`CreateNewUser` + le parcours d'onboarding) : un prestataire qui s'inscrit
  devient **immédiatement éligible** — `provider_type` + `status` posés correctement **et ses métiers
  rattachés dans `trade_user`** (sinon il n'est matchable sur aucun métier). On retire l'attribution
  par défaut `role='client'` pour les comptes prestataires (et on aligne `isEmploye()` profil-based
  avec l'éligibilité, qui ne lit plus `role`).
- **Désambiguïsation société** : `isClientCompany()` consulte le **`type`** de l'`OrganizationAccount`
  (`OrganizationType::canBeClient()`), pas la simple présence d'un `organization_account_id`. Le
  routage (`homeDashboardRoute()`, `assistantContextRole()`) teste **`isProviderCompanyWorker()` avant
  `isClientCompany()`**. Une société-prestataire n'est plus prise pour une société-cliente.
- **Source de vérité métiers** : on standardise sur **`trade_user`** (le pivot consommé par le
  matching). La colonne `ProviderProfile.skills` (JSON legacy) est notée comme divergente et **non
  propagée** (pas de refonte ici).

*Dépend de :* `provider_profiles`, `OrganizationAccount.type`, l'enum `OrganizationType`, `trade_user`.

### 3. Propagation booking→mission (traçabilité type + société)

- **Réservation** porte déjà (au modèle) `assigned_provider_user_id`, `assigned_provider_organization_id`,
  `provider_team_id`. **Prérequis schéma** : ces colonnes doivent **exister en DB**. La migration
  `2026_05_28_100014_add_missing_columns_to_bookings_table` (qui ajoute
  `assigned_provider_organization_id`) est **en attente** et la chaîne de migrations est **bloquée**
  par une migration cassée (`2026_05_27_000005_make_trade_id_required_on_service_catalogs` : impossible
  de passer `trade_id` en `NOT NULL` tant qu'une FK `ON DELETE SET NULL` existe). → SP1 ajoute les
  colonnes manquantes via **une migration dédiée idempotente** (gardes `Schema::hasColumn`),
  indépendante de la chaîne bloquée, **et** corrige la migration `trade_id` cassée (drop/recrée la FK
  avant le `NOT NULL`) pour débloquer le reste.
- **Mission** : ajouter `provider_organization_id` + `provider_team_id` au **`$fillable`** + une
  relation `providerOrganization()`. La création booking→mission (les deux chemins) **copie**
  `assigned_provider_user_id` → `lead_provider_user_id`, `assigned_provider_organization_id` →
  `provider_organization_id`, `provider_team_id` → `provider_team_id`. Le **dispatch** écrit l'org
  **dérivée du profil du worker matché** (`providerProfile.organization_account_id` pour un
  `company_worker`, `null` pour un indépendant), garantissant la cohérence même si la résa n'a pas
  fixé l'org.
- `ProviderDashboard` filtre désormais une colonne **remplie** ; `DispatchCenter` est corrigé pour
  filtrer **`provider_organization_id`** (l'org **prestataire**), pas `organization_account_id`
  (l'org **cliente**).

*Dépend de :* `bookings`, `missions`, `MissionDispatchService`, les deux chemins de création de mission,
`ProviderProfile.organization_account_id`.

### 4. Tests E2E — 4 relations × multi-métiers

- **4 tests bout-en-bout** `C2I / C2B / B2I / B2B` : chacun crée une réservation pour un **métier
  précis**, matche un prestataire du **bon type** **possédant ce métier** (dans `trade_user`), et
  assert que la **mission** enregistre le bon `lead_provider_user_id` **et** le bon
  `provider_organization_id` (null pour l'indépendant, l'org pour la société).
- **Cas métier négatif** : un prestataire éligible par type/zone mais **sans le métier** demandé est
  **exclu** du matching.
- **Cas type négatif** : avec `providerType=independent`, un `company_worker` est exclu (et inversement).
- **Cas inscription** : un prestataire fraîchement inscrit (indep et company_worker) **apparaît** dans
  l'éligibilité pour ses métiers.
- **Cas désambiguïsation** : un compte société-prestataire est routé vers le dashboard prestataire
  (pas client), et `isClientCompany()` renvoie `false` pour lui.

---

## Flux de données (C2B en exemple)

1. Un particulier réserve un métier *peinture* (`service_catalog_id` → `trade_id`).
2. L'éligibilité retourne les prestataires `provider_type=company_worker` (type=company), **vérifiés/actifs**,
   ayant *peinture* dans `trade_user`, couvrant la zone, dispo.
3. Le matching (v2/ai/smart) classe et assigne un worker ; la réservation enregistre
   `assigned_provider_user_id` + `assigned_provider_organization_id` (= l'org du worker).
4. La mission générée copie `lead_provider_user_id` + `provider_organization_id` (+ team le cas échéant).
5. Le dashboard société-prestataire de cette org **voit** la mission (filtre sur la colonne remplie).

*(C2I = idem avec type=independent et `provider_organization_id` null. B2I/B2B = idem mais la
réservation porte aussi `customer_organization_id` côté client ; le portail société est unifié en SP2.)*

---

## Definition of Done

1. **Éligibilité unique** posée et consommée par v2/ai/smart : filtre **type × métier × zone × dispo ×
   actif/vérifié**, sans lire la colonne legacy `role`.
2. **Inscription** : un prestataire (indep ou company_worker) s'inscrit, obtient `provider_type` +
   `status` + ses **métiers dans `trade_user`**, et **apparaît** au matching.
3. **Désambiguïsation** : `isClientCompany()` gate sur le `type` d'org ; routage prestataire-société
   avant client-société ; couvert par test.
4. **Schéma** : colonnes booking (`assigned_provider_organization_id`, `provider_team_id`) et mission
   (`provider_organization_id`, `provider_team_id`) **présentes** (migration dédiée idempotente) ;
   migration `trade_id` cassée **corrigée** ; migrations en attente jouables.
5. **Propagation** : booking→mission copie user + org + team ; dispatch écrit l'org dérivée du profil ;
   `ProviderDashboard`/`DispatchCenter` filtrent la bonne colonne.
6. **Tests** : 4 E2E (C2I/C2B/B2I/B2B) + cas négatifs (métier, type) + inscription + désambiguïsation,
   tous verts.
7. **Gates** : suite complète verte, **PHPStan full** `[OK]`, Pint propre, 0 skip injustifié.

---

## Limites de scope

**Dans le scope SP1 :** l'éligibilité type×métier ; la correction inscription + désambiguïsation ; la
présence schéma + la propagation booking→mission + la correction des dashboards prestataire-société ;
les tests E2E des 4 relations au niveau données/matching ; le déblocage de la chaîne de migrations.

**Hors scope (sous-projets suivants) :**
- **SP2** — le choix client à 3 paliers (préférence `provider_type` sur la résa, auto/favori/premium),
  l'UI de réservation (web + mobile), le gating premium, l'unification de `BookingHub` sur l'action
  canonique de création (`CreateBookingAction`) — donc la correction de la perte du `service_catalog_id`
  côté portail société est **SP2**.
- **SP3** — la **société comme entité réservable** (note/critères au niveau org, sélection « Bolt-like »
  dans la zone, dispatch interne société→worker enrichi).
- **SP4** — les **contrats B2B / partenaires** (`EnterpriseWorkOrder`/`ServicePartner`, routage
  contractuel, tarifs négociés).
- Refonte de `ProviderProfile.skills` (JSON legacy) ; suppression effective de la colonne `role`
  (on cesse de s'en servir, on ne la drop pas ici pour ne pas casser les tests legacy).

**Dépendances :** s'appuie sur l'existant (`provider_profiles`, `trade_user`, `OrganizationAccount`,
`MatchingV2Service`, les modèles `Booking`/`Mission`). Aucune dépendance aux SP2-4 (ils dépendent de SP1).
