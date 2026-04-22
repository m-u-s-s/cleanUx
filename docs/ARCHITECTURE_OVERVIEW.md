# Architecture générale CleanUx

## 1. Vision

CleanUx est un **OS opérationnel pour entreprise de nettoyage**. L’application pilote :
- la demande,
- la réservation,
- la planification,
- l’exécution terrain,
- la qualité,
- la preuve,
- la supervision multi-zone et multi-pays.

Le projet reste un monolithe Laravel, mais la structure métier est découpée par domaines clairs.

---

## 2. Domaines principaux

### 2.1 Référentiel géographique
Tables et modèles clés :
- `countries`
- `regions`
- `provinces`
- `communes`
- `postal_codes`
- `service_zones`
- `zone_service_rules`
- `employee_zone_assignments`

Rôle :
- résoudre le contexte géographique d’une demande,
- savoir si une adresse est réservable,
- limiter les services autorisés,
- affecter les employés à une couverture opérationnelle.

### 2.2 Réservation
Agrégat principal : `RendezVous`

`RendezVous` porte :
- la réservation,
- le contexte client,
- le service demandé,
- la date / heure,
- les snapshots de zone et de pricing,
- la récurrence,
- les informations de confort client.

Le rendez-vous reste la source d’entrée du flux.

### 2.3 Exécution terrain
Agrégat principal : `Mission`

Objets clés :
- `Mission`
- `MissionAssignment`
- `MissionVerificationCode`
- `MissionTrackingSession`
- `MissionTrackingPoint`
- `MissionChecklist`
- `MissionIncident`
- `MissionQualityReview`
- `MissionReport`
- `MissionEvent`
- `MissionClientAction`

Rôle :
- traduire une réservation confirmée en mission terrain,
- piloter le déroulement réel,
- enregistrer la preuve d’exécution,
- consolider la qualité.

### 2.4 Organisation / entreprise
Objets clés :
- `OrganizationAccount`
- `OrganizationSite`

Rôle :
- gérer les comptes entreprise,
- résoudre un site,
- supporter le multi-sites,
- rattacher des utilisateurs et de la facturation.

### 2.5 Finance
Objets clés :
- `FinanceQuote`
- `FinanceInvoice`
- `FinancePayment`
- `FinanceReminder`

Rôle :
- synchroniser devis / facture / paiement à partir des rendez-vous,
- conserver des snapshots stables,
- supporter les exports et relances.

### 2.6 Qualité / audit / ops
Objets clés :
- `ActivityLog`
- notifications
- qualité mission
- incidents
- synchronisations externes

Rôle :
- auditabilité,
- traçabilité,
- support,
- supervision production.

---

## 3. Pattern central : `RendezVous` → `Mission`

### 3.1 Séparation fonctionnelle
Le découpage cible est le suivant :

- `RendezVous` = **booking / promesse commerciale / contexte structuré**
- `Mission` = **terrain / exécution / preuve / qualité**

### 3.2 Synchronisation
La synchronisation est pilotée par :
- observer de rendez-vous,
- services missions,
- génération de mission lors des transitions métier pertinentes.

### 3.3 Pourquoi ce découpage
Ce modèle permet de :
- garder un booking stable,
- faire évoluer le terrain sans gonfler `RendezVous`,
- mieux supporter la qualité, les incidents, les rapports et le tracking.

---

## 4. Services métier majeurs

### Booking / disponibilité
- `BookingEstimatorService`
- `EmployeeAvailabilityService`
- `ZoneCoverageService`
- `CreateBookingAction`
- `CreateRecurringSeriesAction`

### Mission
- `MissionLifecycleService`
- `MissionAssignmentStatusService`
- `MissionVerificationCodeService`
- `MissionFromRendezVousSyncService`
- `MissionTrackingService`
- `MissionChecklistService`
- `MissionQualityService`

### Intégrations / ops
- `GoogleCalendarSyncService`
- finance sync
- heartbeat / health checks

---

## 5. Portails

### Admin
Routes `/admin`

Modules principaux :
- dashboard
- calendrier
- missions
- utilisateurs
- zones
- services
- entreprises
- finance
- analytics
- qualité
- audit logs
- modules
- pays

### Client
Routes `/dashboard/client`

Parcours principaux :
- prise de rendez-vous
- mes rendez-vous
- historique
- profil
- favoris employés
- séries récurrentes
- finance
- suivi mission

### Employé
Routes `/dashboard/employe`

Parcours principaux :
- dashboard
- missions
- historique
- disponibilités
- agenda
- tracking / actions mission

---

## 6. Stabilisation récente

Les correctifs récents ont renforcé :
- le workspace mission employé,
- le panneau mission côté client,
- le centre pays admin,
- la suppression des faux positifs Livewire,
- le découpage des gros composants booking et dashboard,
- l’allègement de `RendezVous`,
- le découpage de `MissionLifecycleService`.

---

## 7. Dette technique restante

À poursuivre :
- sortir encore du comportement terrain de `RendezVous`,
- centraliser davantage les constantes métier,
- compléter les tests de workflow mission profond,
- renforcer le monitoring prod,
- poursuivre le découpage des vues volumineuses restantes.
