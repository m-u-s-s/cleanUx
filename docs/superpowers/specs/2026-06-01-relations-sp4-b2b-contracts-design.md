# SP4 — Contrats B2B / partenaires — Design Spec

**Date :** 2026-06-01
**Statut :** Design approuvé (avant plan d'implémentation)
**Programme :** « 4 relations client×prestataire », sous-projet **4 de 4** (dernier)
**Branche prévue :** `feat/relations-sp4-b2b-contracts` (off `main`)

---

## Contexte & objectif

SP1+SP2+SP3 (livrés+mergés) ont câblé le B2B **à la demande** : une société cliente peut réserver une
société prestataire façon Bolt (choix premium, note agrégée, dispatch scopé société, mission tracée).
SP4 ajoute la couche **CONTRACTUELLE** B2B, distincte : un **contrat-cadre** lie durablement une
`CLIENT_COMPANY` à une `PROVIDER_COMPANY`. Dès qu'un membre du client réserve (ou qu'une Work Order est
émise), le contrat est **détecté**, **applique son tarif négocié**, **route vers le partenaire**
(réutilisant la machinerie SP3), **enforce ses policies** (validité, services autorisés, PO, cost center,
approbation), et **arme un suivi SLA** actif.

Principe directeur : **aucun nouveau monde de dispatch**. Le « partenaire sous contrat » est un
`OrganizationAccount` de type `PROVIDER_COMPANY` (aligné SP1-SP3), et « router sous contrat » = poser
`assigned_provider_organization_id = partenaire` → toute la machinerie SP3 (EligibleCompaniesResolver,
matching scopé, auto-dispatch du meilleur worker, `mission.provider_organization_id`,
`PreferredCompanyResolver` pour le repli) est réutilisée telle quelle.

### Décisions produit (validées)

1. **Partenaire = `OrganizationAccount` PROVIDER_COMPANY** (pas `ServicePartner`). `OrganizationContract`
   gagne `provider_organization_id` (FK `organization_accounts`). Le monde `ServicePartner` /
   `PartnerZoneCoverage` / `MissionPartnerAssignment` reste **legacy/hors-scope** (non supprimé, non
   approfondi).
2. **Routage préféré + repli marché.** Le partenaire est auto-assigné par défaut ; si aucun worker du
   partenaire n'est dispo sur le créneau → repli sur les créneaux alternatifs du partenaire
   (`PreferredCompanyResolver` SP3) puis sur le marché ouvert. La réservation n'est **jamais bloquée** par
   l'indisponibilité du partenaire.
3. **Tarif négocié = grille unitaire + repli remise %.** Si une ligne `contract_rate_cards` existe pour le
   service → prix unitaire négocié ; sinon `negotiated_discount_percent`. Intégré **dans** `PricingEngine`
   v2 comme adjustment contrat-scopé prioritaire, tracé dans `price_quotes`.
4. **Deux voies de commande** : (a) réservation **contract-aware** (le flux booking SP1-SP3 devient
   contractuel), (b) cycle **Work Order** complet (créer → lignes → approbation → générer missions sous
   contrat, `agreed_rate` peuplé).
5. **Surfaces : admin gère + portails lecture (web).** L'opérateur crée/édite le contrat
   (`B2BOperationsCenter` admin, web). La société cliente ET la société partenaire **consultent** leur
   contrat + Work Orders + statut SLA (portails web read-only). Mobile : la réservation contract-aware
   passe par le flux booking mobile SP3 (tarif + routage appliqués côté serveur) ; **pas d'écran de gestion
   contrat mobile dédié**.
6. **Policies dures enforced + monitoring SLA actif.** IN : fenêtre de validité, services autorisés, **PO
   requis (bloquant)**, cost center, mode d'approbation (auto vs manager→finance réutilisant
   `EnterpriseBookingApproval`), snapshot SLA sur la mission, **et monitoring SLA actif** (commande
   planifiée qui détecte les dépassements et déclenche des escalades).

---

## État des lieux (échafaudage existant, à réutiliser/raviver)

Vérifié par cartographie code. **Rien à réinventer ; SP4 lie et complète.**

- **VIVANT, réutilisable** : `OrganizationContract` (relations FK, statuts, SLA fields,
  `negotiated_discount_percent`, `approval_mode`, `requires_purchase_order`, `default_cost_center`,
  `allowed_service_catalog_ids`, `effective_from/to`) ; `EnterpriseWorkOrder` + `WorkOrderLine` +
  `WorkOrderApproval` ; `EnterpriseBookingApproval` (pipeline manager→finance) ; `B2BOperationsCenter`
  (admin Livewire) ; `PricingEngine` v2 (`quote()` + ledger `price_quotes` + `PricingRule` DSL) ;
  `EnterpriseWorkOrderMissionGeneratorService` ; la chaîne dispatch SP1-SP3.
- **VIVANT mais mal intégré** : `ContractPolicyService` (applique la remise APRÈS pricing, pas dans
  `PricingEngine`) ; `EnterpriseRoutingService` (`buildContractSnapshot()` peu/pas appelé) ;
  `OrganizationContract.negotiated_discount_percent` (appliqué hors moteur).
- **MORT / à ignorer (legacy ServicePartner)** : `ServicePartner`, `PartnerZoneCoverage`,
  `MissionPartnerAssignment.agreed_rate`, `ServicePartnerLoadSnapshot`. SP4 ne s'appuie PAS dessus
  (`OrganizationContract.default_service_partner_id` est remplacé fonctionnellement par
  `provider_organization_id`).

---

## Architecture & composants

Huit unités bornées.

### 1. Modèle de données *(migrations idempotentes)*

- **`OrganizationContract`** : ajout `provider_organization_id` (FK `organization_accounts`, nullable le
  temps de la migration puis renseigné côté admin). Conserve les champs existants. Relations :
  `clientOrganization()` (= `organization_account_id`), `providerOrganization()` (nouveau),
  `rateCards()` (hasMany).
- **`contract_rate_cards`** (nouvelle) : `id`, `organization_contract_id` (FK), `service_catalog_id` (FK),
  `negotiated_unit_price_cents` (int), `currency` (string, défaut EUR), `metadata` (json nullable).
  UNIQUE(`organization_contract_id`, `service_catalog_id`). Modèle `ContractRateCard`.
- **`bookings`** : ajout `organization_contract_id` (nullable FK) — la résa placée sous ce contrat.
  `assigned_provider_organization_id` (existe déjà, SP1) posé par le routage.
- **`missions`** : ajout `organization_contract_id` (nullable FK) + `sla_response_due_at` (datetime
  nullable) + `sla_resolution_due_at` (datetime nullable). `provider_organization_id` existe déjà (SP1).
- **`contract_sla_events`** (nouvelle) : `id`, `mission_id` (FK), `organization_contract_id` (FK), `kind`
  (enum `response`|`resolution`), `due_at` (datetime), `breached_at` (datetime nullable), `escalated_at`
  (datetime nullable), `status` (enum `pending`|`met`|`breached`|`escalated`), `metadata` (json nullable).
  UNIQUE(`mission_id`, `kind`). Modèle `ContractSlaEvent`.

*Dépend de :* `OrganizationAccount`, `ServiceCatalog`/`ServiceCatalogV2`, `Mission`, `Booking`.

### 2. `ContractResolver` *(détection du contrat applicable)*

- `resolveForBooking(OrganizationAccount $clientOrg, ?int $serviceCatalogId, ?int $zoneId, string $date): ?OrganizationContract`
  retourne le contrat ACTIF applicable : `status` actif, `effective_from <= date <= effective_to` (ou
  null = ouvert), `provider_organization_id` renseigné, et `service_catalog_id` ∈
  `allowed_service_catalog_ids` (si la liste est non vide ; vide = tous services autorisés). S'il y a
  plusieurs contrats, prend le plus spécifique/récent (documenté : `orderByDesc('effective_from')`).
- `resolveForClientUser(User $client, ...)` : dérive l'org cliente du membre
  (`current_organization_id`/`OrganizationMember`) puis délègue. Soft : retourne null si le client n'est
  pas membre d'une `CLIENT_COMPANY`. **Source de vérité unique** consommée par le booking ET les WO.

*Dépend de :* `OrganizationContract`, la couche `OrganizationMember`.

### 3. `ContractPricingResolver` *(tarif négocié, dans PricingEngine)*

- Branché **dans `PricingEngine::quote()`** comme adjustment contrat-scopé **prioritaire** (avant les
  `PricingRule` globales). Entrée : le `service_code`/`service_catalog_id`, le prix de base, et le contrat
  résolu (passé via le contexte de `quote()` ou re-résolu depuis `bookingId`).
- Logique : si une `ContractRateCard` existe pour (`contract`, `service`) → **remplace** le prix de base
  par `negotiated_unit_price_cents` (× quantité/surface si applicable, en cohérence avec le DSL existant) ;
  sinon applique `negotiated_discount_percent` en réduction. L'ajustement est **tracé** dans
  `price_quotes.applied_rules` avec une étiquette explicite (`contract:rate_card` /
  `contract:discount`).
- Si pas de contrat → no-op (comportement pricing inchangé).

*Dépend de :* `PricingEngine`, `ContractRateCard`, `ContractResolver`.

### 4. `ContractRoutingService` *(routage préféré + repli)*

- Refonte ciblée d'`EnterpriseRoutingService`. `applyToBooking(Booking $rdv, OrganizationContract $contract): void`
  pose `assigned_provider_organization_id = $contract->provider_organization_id` et
  `organization_contract_id = $contract->id` sur la résa (avant le dispatch). Le dispatch SP3 reste
  inchangé : il scope déjà sur `assigned_provider_organization_id` et auto-suggère le meilleur worker du
  partenaire ; l'indispo partenaire est gérée par `PreferredCompanyResolver` (créneaux alternatifs) avec
  repli marché ouvert (décision produit 2).
- **Mutualité** : si le client a explicitement choisi une autre société (SP3) ou un presta précis (SP2),
  documenter la priorité — le contrat **prime** sur l'auto-match mais **n'écrase pas** un choix premium
  explicite du client (le client garde la liberté de choisir hors-contrat ; le contrat est le défaut). À
  trancher dans le plan : par défaut, le contrat pose le routage SI le client n'a pas explicitement choisi
  une autre org.

*Dépend de :* SP1 (colonnes), SP3 (`PreferredCompanyResolver`, dispatch scopé).

### 5. `ContractPolicyEnforcer` *(policies dures)*

- Extension de `ContractPolicyService`. `enforceForBooking(Booking $rdv, OrganizationContract $contract, array $input): void`
  valide, dans l'ordre : (a) fenêtre de validité ; (b) `service_catalog_id` ∈ services autorisés ; (c)
  **PO requis** — si `requires_purchase_order` et pas de `purchase_order_number` fourni →
  `ValidationException`/`AuthorizationException` (bloquant) ; (d) cost center — si `default_cost_center` et
  rien fourni, le défaut est appliqué (forcé) ; (e) approbation — si `approval_mode='manual'`, la résa est
  routée vers `EnterpriseBookingApproval` (pipeline manager→finance existant) au lieu d'une confirmation
  directe. Versions `enforceForWorkOrder(...)` pour le chemin WO.
- Soft-fail là où c'est non-critique ; **hard-fail** pour PO manquant et service non autorisé.

*Dépend de :* `OrganizationContract`, `EnterpriseBookingApproval`.

### 6. Cycle Work Order *(commande B2B groupée)*

- Câblage de `EnterpriseWorkOrderMissionGeneratorService` : à la génération des missions depuis une WO
  approuvée, (a) `ContractPricingResolver` peuple `agreed_rate`/`unit_price` des lignes et du devis ; (b)
  les missions générées portent `provider_organization_id = contract.provider_organization_id` +
  `organization_contract_id` + snapshot SLA ; (c) le dispatch SP3 auto-suggère les workers du partenaire.
- Approbation WO : `WorkOrderApproval` est élevé au pipeline manager→finance (réutilise le pattern
  `EnterpriseBookingApproval` — états `pending_manager`→`pending_finance`→`approved`/`rejected`). PO requis
  enforced avant approbation finale.

*Dépend de :* `EnterpriseWorkOrder`, `WorkOrderLine`, `WorkOrderApproval`, `ContractPricingResolver`,
`ContractRoutingService`.

### 7. `ContractSlaMonitor` *(monitoring SLA actif)*

- Au moment où une mission sous contrat est créée : un snapshot SLA est armé (`sla_response_due_at =
  created_at + sla_response_hours`, `sla_resolution_due_at = planned_start_at|created_at +
  sla_resolution_hours`) et deux `contract_sla_events` (`response`, `resolution`) en `status=pending`.
- Commande planifiée **`contract:scan-sla`** (cron-ready, idempotente) : marque `met` quand l'événement
  attendu est survenu (réponse = mission acceptée/assignée ; résolution = mission complétée) avant
  l'échéance, `breached` quand `now > due_at` sans satisfaction, et déclenche une **escalade**
  (`escalated_at` + notification aux responsables — réutilise le système de notifications existant) une
  seule fois par événement. Soft-fail par mission.
- Dashboard SLA = lecture de `contract_sla_events` agrégés.

*Dépend de :* `Mission`, `contract_sla_events`, le système de notifications.

### 8. UI *(admin gestion + portails lecture web ; mobile contract-aware serveur)*

- **Admin (web)** : `B2BOperationsCenter` étendu — CRUD contrat (dont `provider_organization_id` + grille
  `contract_rate_cards`), gestion WO (création/lignes/approbation), **dashboard SLA** (pending/breached/
  escalated). Routes admin existantes.
- **Portail client société (web)** : composant `ClientContractsCenter` (Livewire) — lecture des contrats
  où l'org du membre est cliente + ses Work Orders + statut SLA. Read-only.
- **Portail partenaire société (web)** : extension `DispatchCenter`/`ProviderDashboard` — la
  `PROVIDER_COMPANY` voit les contrats où elle est partenaire + les WO/missions entrantes + ses
  obligations SLA. Read-only (la réassignation worker passe par le DispatchCenter SP3 existant).
- **Mobile** : pas d'écran de gestion. La réservation mobile (SP3) devient contract-aware **côté serveur**
  via le hook contrat dans `CreateBookingFromApiAction` (tarif négocié + routage partenaire appliqués). Un
  badge léger « couvert par votre contrat » peut être exposé via la réponse API (optionnel, non bloquant).

*Dépend de :* `B2BOperationsCenter`, `DispatchCenter`, le flux booking SP3.

---

## Points d'intégration (les 3 chemins de création, comme SP1-SP3)

Un **hook contrat** unique et ordonné est inséré dans les 3 chemins, AVANT le dispatch :

1. `CreateBookingAction` (web/société)
2. `CreateBookingFromApiAction` (API/mobile)
3. `PrendreRendezVous` (Livewire web)

Séquence du hook : `ContractResolver` → (si contrat) `ContractPolicyEnforcer` → `ContractRoutingService`
→ le pricing passe par `ContractPricingResolver` dans `PricingEngine` → dispatch SP3 inchangé → mission
avec `provider_organization_id` + snapshot SLA. Si pas de contrat applicable → comportement SP1-SP3
inchangé (no-op).

Le chemin Work Order a son propre point d'entrée (génération de missions) mais réutilise les MÊMES
services (Resolver/Pricing/Routing/Policy/Sla).

---

## Flux de données (réservation contract-aware)

1. Un membre d'une `CLIENT_COMPANY` crée une réservation (web ou mobile).
2. `ContractResolver` trouve le contrat actif applicable (org cliente, service, zone, date).
3. `ContractPolicyEnforcer` valide (validité, service autorisé, PO si requis → bloquant, cost center
   défaut/forcé) et route vers approbation si `approval_mode=manual`.
4. `ContractRoutingService` pose `assigned_provider_organization_id = partenaire` +
   `organization_contract_id` sur la résa.
5. `PricingEngine` (via `ContractPricingResolver`) applique le tarif négocié (grille → sinon remise %),
   tracé dans `price_quotes`.
6. Le dispatch SP3 scope les candidats au partenaire → auto-suggère son meilleur worker dispo ; si aucun →
   `PreferredCompanyResolver` (créneaux alternatifs) puis repli marché ouvert.
7. La mission est créée avec `provider_organization_id = partenaire`, `organization_contract_id`, et le
   snapshot SLA armé (`contract_sla_events` pending).
8. `contract:scan-sla` suit les échéances, marque met/breached, escalade sur dépassement.

---

## Definition of Done

1. Migrations idempotentes : `OrganizationContract.provider_organization_id` ; table `contract_rate_cards`
   (+ modèle) ; `bookings.organization_contract_id` ; `missions.organization_contract_id` +
   `sla_response_due_at` + `sla_resolution_due_at` ; table `contract_sla_events` (+ modèle). Relations +
   fillable + casts.
2. `ContractResolver` (contrat actif applicable, fenêtre + services autorisés + org cliente ; source
   unique booking + WO).
3. `ContractPricingResolver` branché dans `PricingEngine` (grille unitaire → repli remise %, tracé
   `price_quotes`, no-op sans contrat).
4. `ContractRoutingService` (pose `assigned_provider_organization_id` + `organization_contract_id` ; repli
   partenaire indispo via SP3 ; ne pas écraser un choix premium explicite du client).
5. `ContractPolicyEnforcer` (validité, services autorisés, **PO bloquant**, cost center,
   approbation manuelle → `EnterpriseBookingApproval`).
6. Hook contrat unique dans les 3 chemins de création (`CreateBookingAction`,
   `CreateBookingFromApiAction`, `PrendreRendezVous`) ; no-op sans contrat.
7. Cycle Work Order câblé (`agreed_rate`/lignes peuplés via pricing contrat, missions générées routées +
   SLA, approbation manager→finance, PO enforced).
8. `ContractSlaMonitor` + commande `contract:scan-sla` (snapshot à la création de mission, met/breached/
   escalade idempotente, soft-fail).
9. UI : `B2BOperationsCenter` étendu (contrat + grille + WO + dashboard SLA) ; portail client web
   (`ClientContractsCenter` lecture) ; portail partenaire web (lecture contrats/WO/SLA) ; booking mobile
   contract-aware côté serveur (+ badge optionnel).
10. Tests : résolution, pricing (grille + remise, tracé), routage (+ repli), chaque policy (dont PO
    bloquant + approbation manuelle), cycle WO complet, SLA (snapshot + breach + escalade), portails
    (admin CRUD, client/partenaire lecture), booking mobile porte tarif+routage contrat.
11. Gates : suite complète verte, **PHPStan full** `[OK]` (0 suppression, vraies annotations), Pint clean ;
    mobile tsc + Jest verts.

---

## Limites de scope

**Dans le scope SP4 :** contrat-cadre `OrganizationAccount`↔`OrganizationAccount` ; grille tarifaire +
remise dans `PricingEngine` ; routage contractuel préféré + repli ; enforcement des policies dures
(validité, services autorisés, PO, cost center, approbation) ; cycle Work Order ; monitoring SLA actif
(snapshot + scan + escalade) ; UI admin + portails lecture web client/partenaire ; booking mobile
contract-aware serveur.

**Hors scope (futurs / non désirés) :**
- **Export facturation / compta** (groupement WO par contrat, factures mensuelles).
- **Commandes récurrentes** (standing orders auto-générées par cron).
- **Refonte du monde `ServicePartner`** (`ServicePartner`/`PartnerZoneCoverage`/`MissionPartnerAssignment`
  restent legacy, non supprimés, non approfondis).
- **Écrans mobiles dédiés** contrat / Work Order (la réservation mobile reste contract-aware serveur).
- **Renégociation self-service** par les sociétés (contrat créé/édité par l'admin opérateur).
- **Routage exclusif configurable** (`routing_mode`) — SP4 fige « préféré + repli » ; l'exclusivité
  paramétrable serait un ajout ultérieur.

**Dépendances :** s'appuie sur SP1 (`provider_organization_id` booking→mission, `OrganizationType`,
colonnes org/contract), SP2 (`ProviderSelectionResolver`, choix premium qui prime), SP3
(`EligibleCompaniesResolver`, dispatch scopé société, `PreferredCompanyResolver`, `mission.provider_organization_id`),
`PricingEngine` v2 (`price_quotes`), `EnterpriseBookingApproval` (pipeline manager→finance),
`EnterpriseWorkOrder`/`WorkOrderLine`/`WorkOrderApproval`, `B2BOperationsCenter`, le système de
notifications. Dernier sous-projet du programme : après SP4, les 4 relations (C2I/C2B/B2I/B2B) sont
fonctionnelles à la demande ET sous contrat.
