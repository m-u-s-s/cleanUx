# T2 — PROVIDER · carte de périmètre

Reconnaissance en LECTURE SEULE, mesurée le 2026-08-19 sur `main` (fcae050f).
Aucun fichier de code modifié, aucune suite lancée.

**État de la mesure : PARTIELLE.** Le web (routes + gardes API) est mesuré et cité.
Le natif et la joignabilité Blade sont incomplets — voir « NON COUVERT » en fin de document.
Tout ce qui suit est cité en `fichier:ligne` ; ce qui ne l'est pas est marqué *(non vérifié)*.

---

## 1. Surface réelle

| Zone | Compte mesuré | Source |
|---|---|---|
| `app/Livewire/Provider/` | **17** (16 à plat + `Provider/Onboarding/ProviderOnboardingWizard.php`) | `find app/Livewire/Provider -name '*.php'` |
| `app/Livewire/ProviderCompany/` | **15** | idem |
| `app/Livewire/Employe/` | **19** | idem |
| **Total web** | **51** | |
| `routes/employe.php` | 207 l. | |
| `routes/missions.php` | 94 l. | |
| `routes/company-dashboards.php` (part provider) | l. 95-156 | |
| `routes/api/provider.php` | 681 l. | |
| `mobile/provider/` | 71 écrans annoncés (charte) — **non revérifié** | |

---

## 2. Points d'entrée web — route → composant

### 2.1 Prestataire / salarié — `routes/employe.php`
Groupe : `Route::middleware(['role:employe', 'provider.approved'])->prefix('dashboard/employe')` — `routes/employe.php:46`

| Domaine | URL | Composant | Ligne |
|---|---|---|---|
| Accueil | `/dashboard/employe` | `EmployeDashboard` | `routes/employe.php:51` |
| Couverture métier/zone | `/metiers-zones` | `Provider\TradesAndZones` | `:62` |
| Répertoire modules | `/modules` | `Shared\ModulesDirectory` | `:67` |
| Avis | `/avis` | `Provider\ProviderRatingsPage` | `:72` |
| Portefeuille | `/portefeuille` | `Provider\ProviderWalletPage` | `:76` |
| Litiges | `/litiges` | `Provider\ProviderDisputesPage` | `:80` |
| Devis chantiers | `/devis-chantiers` | `Provider\BundleQuoteRequests` | `:84` |
| KYC identité | `/verification` | `Provider\ProviderKycPage` | `:88` |
| Dossier conduite | `/conduite` | `Provider\ProviderDrivingDossier` | `:100` |
| Missions (liste) | `/missions` | `Employe\MissionsEmploye` | `:106` |
| Noter le client | `/missions/{bookingId}/evaluer-client` | `Employe\EmployeeRateClient` | `:110` |
| Badges | `/badges` | `Provider\ProviderBadgesPage` | `:115` |
| Revenus | `/revenus` | `Provider\ProviderEarningsDashboard` | `:120` |
| SOS / sécurité | `/securite` | `Provider\SafetyPanel` | `:135` |
| Carte de demande | `/demande` | `Provider\DemandHeatmap` | `:145` |
| Mission terrain | `/missions/{mission}` | `Employe\MissionFieldPage` (+`can:update,mission`) | `:148` |
| Disponibilités | `/disponibilites` | `Employe\DisponibilitesEmploye` | `:154` |
| Planning | `/planning` | `Employe\PlanningEmploye` | `:158` |
| Historique | `/historique` | `Employe\HistoriqueEmploye` | `:162` |
| Incident | `/incident` | `Employe\SignalerIncident` | `:166` |
| Équipe terrain | `/equipe` | `Employe\EquipeTerrain` | `:170` |
| Coordination chantier | `/coordination` | `Employe\CoordinationChantier` | `:174` |
| Centre chef d'équipe | `/chef-equipe` | `Employe\TeamLeadOperationsCenter` (+`field.team.lead`) | `:177` |
| Stripe Connect (3 routes) | `/stripe-connect/*` | `StripeConnectController` | `:188-195` |
| Feedbacks | `/feedbacks` | `FeedbacksEmploye` | `:200` |
| Validation multi-RDV | `/validation-multiple-rdv` | `Employe\ValidationMultipleRdv` | `:204` |

**Routes exclues de `provider.approved`** (`withoutMiddleware`) : dashboard `:52`, métiers-zones `:63`,
KYC `:89`, conduite `:101`, les 3 Stripe Connect `:187`. Justification documentée sur place
(`routes/employe.php:31-45`) : ce sont les routes par lesquelles on COMPLÈTE le dossier.

### 2.2 Gestes de terrain — `routes/missions.php`
Groupe : `Route::middleware(['role:employe', 'face.verified'])` — `routes/missions.php:20`

`offline-sync` `:21` · bascule d'item de checklist `:24` · `start` `:27` · `en-route` `:30` ·
`arrived` `:33` · `finish` `:36` · redirection QR `:39` · tracking start/push/stop `:49-56`.
Hors groupe : `missions.show` dispatcher de rôle `:60`, rapport PDF `:76`, tracking live `:92`
— tous en `can:view,mission`.

### 2.3 Société prestataire — `routes/company-dashboards.php`
Groupe : `['auth', 'verified', 'active.account', 'org.type:provider']`, préfixe
`dashboard/entreprise-prestataire` — `routes/company-dashboards.php:95`

Dashboard `:100` · modules `:104` · canaux `:108` · tâches `:109` · dispatch `:110` · équipe `:111` ·
rôles-permissions `:117` · équipes terrain `:118` · implantations `:124` · sites `:126` ·
planning `:133` · heures `:137` · consommables `:140` · devis `:147` · recrutement `:151` ·
qualité-matériel `:155`.

**Les 15 composants `ProviderCompany/` ont chacun une route.** Aucun orphelin de ce côté.

---

## 3. TROUS DE GARDE — priorité 1

### R1 — `/api/provider/company/*` : aucune garde de TYPE d'organisation
`routes/api/provider.php:480` — le groupe ne porte que `auth:sanctum`.
Le pendant web porte `org.type:provider` (`routes/company-dashboards.php:95`).
La résolution d'organisation, `app/Support/Organizations/ResolvesActiveOrganization.php:42-64`,
vérifie **l'appartenance active** (`:51-57`) mais **jamais le type** de l'organisation :

```
$organisation = OrganizationAccount::query()->find($organisationId);   // :59
abort_if($organisation === null, 403);                                  // :61
return $organisation;                                                   // :63
```

`EnsureOrganizationType` existe (`app/Http/Middleware/EnsureOrganizationType.php`, alias posé
`app/Http/Kernel.php:127`) et n'est référencé QUE dans `routes/company-dashboards.php:39` et `:95`
— donc **sur le web seulement**. C'est exactement le défaut connu du dépôt
« gardes web absentes de l'API ».

**Ce que ça casse** : un membre d'une organisation CLIENTE qui détient une permission au nom
partagé (`missions.view_all`, `team.view`, `sites.view_all`) atteint les points d'API de l'espace
société PRESTATAIRE avec sa propre organisation comme périmètre. Le périmètre reste le sien
(pas d'IDOR inter-sociétés), mais la frontière T1/T2 n'est plus tenue par le code.
**À arbitrer par T4** — c'est une frontière partagée, pas une correction unilatérale.

### R2 — Cinq écritures de canal sans aucune garde d'organisation
`app/Http/Controllers/Api/Provider/CompanyController.php` — méthodes sans `exige()`,
sans `organisationActive()`, sans `authorize()` :

| Méthode | Ligne | Route |
|---|---|---|
| `channelMembers` | `:1352` | `routes/api/provider.php:602` |
| `leaveChannel` | `:1413` | `:605` |
| `markChannelRead` | `:1422` | `:606` |
| `channelMessages` | `:1447` | `:608` |
| `postChannelMessage` | `:1518` | `:609` |
| `sendVoiceNote` | `:1549` | `:612` |
| `endCall` | `:1690` | `:624` |

`addChannelMember` `:1373` et `startCall` `:1622` / `callToken` `:1665` portent un `abort_*` mais
**pas** `organisationActive()`. Le commentaire de route (`routes/api/provider.php:591-592`) affirme
que « lecture ET écriture passent par ChannelPolicy » — à vérifier méthode par méthode : la
politique n'apparaît explicitement que sur `removeChannelMember` (`:1397`, `->can('kickMember')`).

**Ce que ça casse** : lecture/écriture d'un fil d'équipe d'une autre société si l'identifiant de
canal est deviné et que `ChannelPolicy` ne couvre pas le cas. **Non confirmé par un test** — c'est
une piste de haute priorité, pas un verdict.

### R3 — `updateMemberRole`, `assignMission`, `assignMissionToTeam`, `missionHelpers` : pas de `exige()`
`CompanyController.php:473`, `:861`, `:909`, `:961` — ces quatre écritures n'ont pas d'appel
`exige()` et s'appuient sur des `abort_*` internes + `OrganizationMemberAdministration`.
Les routes correspondantes (`routes/api/provider.php:531`, `:580`, `:582`, `:584`) ne portent
aucun `org.permission`. Le commentaire `routes/api/provider.php:521-529` assume ce choix pour les
membres (règles dépendant de la CIBLE) — **c'est argumenté, à ne pas rouvrir comme un manque**.
En revanche `assignMission` / `assignMissionToTeam` / `missionHelpers` ne sont couvertes par aucun
commentaire équivalent : à confirmer.

### R4 — `members`, `fieldTeams`, `tasks`, `channels`, `availability` en lecture sans permission fine
`CompanyController.php:433`, `:667`, `:738`, `:1279`, `:1069` — seulement `organisationActive()`.
Les routes `/members` `:518` et `/field-teams` `:540` portent `org.permission:team.view` ;
`/tasks` `:555`, `/channels` `:593`, `/agencies` `:513` n'en portent aucun côté route.
`agencies()` `:317` rattrape avec `exige('agencies.view')` ; `tasks()` `:738` et `channels()` `:1279`
**ne rattrapent pas**. Un `worker` lit donc la liste des tâches et des canaux de sa société.

### R5 — `provider/safety` hors garde de rôle — DÉLIBÉRÉ, ne pas corriger
`routes/api/provider.php:84` : `Route::middleware('auth:sanctum')->prefix('provider/safety')`.
Justification écrite sur place (`:41-54`) : un bouton d'urgence qui répond 403 est pire que le
risque d'une alerte de trop. **Décision produit assumée.** À consigner comme telle pour T4, pas
comme un trou.

### R6 — `provider/payouts` sans `role:employe` — couvert par le contrôleur
`routes/api/provider.php:415` : `['auth:sanctum', 'token.grace']` seulement.
Rattrapé dans `app/Http/Controllers/Api/Provider/ProviderPayoutsController.php:32` et `:74`
(`$this->abortIfNotProvider($user)`). **Pas un trou**, mais une garde de rôle qui vit au mauvais
étage : si une 3e méthode est ajoutée au contrôleur sans l'appel, elle est nue.

### R7 — Le web n'impose ni `verified` ni `active.account` au prestataire individuel
`routes/employe.php:46` porte `['role:employe', 'provider.approved']`.
`routes/company-dashboards.php:95` porte `['auth', 'verified', 'active.account', 'org.type:provider']`.
Le salarié / indépendant du groupe `employe.` échappe donc à `active.account` et à `verified`,
que la société prestataire subit. **Asymétrie mesurée, cause non établie** — peut être portée par
le groupe parent dans `routes/web.php` *(non vérifié)*.

### R8 — `face.verified` couvre les gestes, pas la lecture — DÉLIBÉRÉ
`routes/missions.php:20` et `routes/api/provider.php:104`. Justification écrite
(`routes/missions.php:10-19`) : consulter ses revenus n'envoie personne chez un client.
Cohérent web ↔ API — **c'est la seule garde que j'ai vérifiée symétrique des deux côtés.**

---

## 4. TROUS DE JOIGNABILITÉ

### 4.1 Composants Livewire provider SANS route — 14 sur 51

Aucune de ces classes n'apparaît dans `routes/employe.php`, `routes/missions.php` ni
`routes/company-dashboards.php` :

**`app/Livewire/Provider/` (6)**
- `FaceCheckPage.php` — le contrôle facial n'a **aucune route web**. Le module existe côté API
  (`routes/api/provider.php:234-242`, 8 points) et n'a pas de porte navigateur.
- `MissionOfferPage.php` — l'écran d'acceptation d'offre. Pas de route.
- `OfferWatcher.php` — vraisemblablement un composant imbriqué *(à confirmer : grep `<livewire:` )*.
- `ProviderDossierBanner.php` — bandeau, imbriqué attendu *(non vérifié)*.
- `ProviderDrivingBanner.php` — idem *(non vérifié)*.
- `Onboarding/ProviderOnboardingWizard.php` — l'assistant d'inscription prestataire.
  L'API a 10 points d'onboarding (`routes/api/provider.php:388-410`). **Pas de route web.**

**`app/Livewire/Employe/` (8)**
- `FeedbackStats.php`, `GoogleAgendaEmploye.php`, `MesRendezVous.php`, `MissionActions.php`,
  `MissionExecutionBoard.php`, `MissionFieldTools.php`, `MissionIncidentBoard.php`,
  `MissionRouteTracking.php`

Les cinq `Mission*` sont très probablement imbriqués dans `MissionFieldPage`
(`routes/employe.php:148`) — **c'est le cas attendu et sain**. `MesRendezVous` et
`GoogleAgendaEmploye` n'ont pas de conteneur évident : ce sont les deux vrais suspects
d'orphelinat. **Mesure interrompue avant confirmation** — voir NON COUVERT.

**`app/Livewire/ProviderCompany/` : 0 orphelin.** Les 15 ont une route.

### 4.2 Le motif `if (class_exists(...))` masque la disparition d'une route
`routes/employe.php` enveloppe **19 de ses 26 routes** dans `if (class_exists(X::class))`
(`:71, :75, :79, :83, :87, :99, :105, :109, :114, :119, :147, :153, :157, :161, :165, :169, :173,
:186, :199, :203`). Si une classe est renommée, la route **disparaît silencieusement** et
`Route::has()` rend `false` sans qu'aucun test ne tombe. Deux routes s'en excluent volontairement
et le disent : `/securite` (`:130-133`) et `/demande` (`:145`).
**C'est la mécanique qui produit la famille « module complet et injoignable » de ce dépôt.**

### 4.3 Écrans natifs
**NON MESURÉ.** Un agent d'exploration a été lancé sur `mobile/provider/` (hors `src/admin/`)
et n'a pas rendu son résultat avant la clôture de ce lot. Voir NON COUVERT.

---

## 5. PARITÉ WEB ↔ NATIF

Mesures partielles, par domaine, en confrontant `routes/employe.php` à `routes/api/provider.php` :

| Domaine | Web | API/natif | Écart |
|---|---|---|---|
| Contrôle facial | **aucune route** (`FaceCheckPage` orphelin) | 8 points `:234-242` | **natif seul** |
| Onboarding prestataire | **aucune route** (`ProviderOnboardingWizard` orphelin) | 10 points `:388-410` | **natif seul** |
| Offres ASAP | pas de route dédiée | `:190-192` + `provider/offers/current` `:126` | **natif seul** |
| Inspection qualité | pas de route dédiée | 6 points `:195-200` | **natif seul** |
| Assignations (boîte de réception) | pas de route | `:138-142` | **natif seul** |
| Croissance / quêtes / route du jour | `/demande` seul (`:145`) | 9 points `:70-81` | **natif dominant** |
| Flotte prestataire | pas de route employé | `:271-274` | **natif seul** |
| Présence (v1 + v2) | pas de route | `:112-116` et `:182-187` | **natif seul** |
| Disponibilités | `/disponibilites` `:154` | 8 points `:203-210` | parité |
| Portefeuille / revenus | `/portefeuille` `:76`, `/revenus` `:120` | `:213-215`, `:416-417` | parité |
| Badges | `/badges` `:115` | `:178-179` | parité |
| Litiges | `/litiges` `:80` | `:218-219` | parité |
| Avis | `/avis` `:72` | `:149-151` | parité |
| SOS | `/securite` `:135` | `:84-88` | parité |
| Coordination / équipe terrain | `/equipe` `:170`, `/coordination` `:174` | — | **web seul** |
| Validation multi-RDV | `/validation-multiple-rdv` `:204` | — | **web seul** |
| Feedbacks | `/feedbacks` `:200` | — | **web seul** |
| Devis chantiers | `/devis-chantiers` `:84` | — | **web seul** |
| Dossier conduite | `/conduite` `:100` | `:409-410` (partiel) | web dominant |

**Le déséquilibre penche NETTEMENT du côté natif** : au moins 8 domaines complets n'ont aucune
porte web, dont deux qui sont des étapes obligatoires du parcours (onboarding, contrôle facial).
Un prestataire sans smartphone ne peut pas s'inscrire ni passer son contrôle facial.
**C'est le constat le plus lourd de ce périmètre.**

---

## 6. FRONTIÈRES PARTAGÉES — arbitrage T4 obligatoire

### Avec T1 (client)
1. **`app/Enums/OrganizationRole.php:101-116`** — `forProviderCompany()` rend **les onze** rôles,
   dont `manager`, `site_manager`, `requester` qui sont aussi ceux de `forClientCompany()`
   (`:72-82`). Le recouvrement est assumé et documenté (`:95-97`). **T2 ne touche pas ce fichier.**
2. **`routes/company-dashboards.php`** — un seul fichier, deux publics (client `:39-88`,
   provider `:95-156`). Toute modification traverse T1.
3. **`app/Support/Organizations/ResolvesActiveOrganization.php`** — trait **partagé** avec le
   contrôleur société CLIENTE (dit explicitement `:12`, `:66-67` du `CompanyController`).
   Y ajouter la vérification de type (R1) **impacte T1 directement**. Arbitrage T4 requis.
4. **Mission ↔ réservation** — `routes/missions.php:60-74` route selon le rôle du lecteur ;
   `booking_id` fait foi. `EmployeeRateClient` (`routes/employe.php:110`) prend un `{bookingId}`
   là où tout le reste du groupe prend un `{mission}` : **deux notions, un identifiant**.
5. **Annulation / no-show prestataire** — `routes/api/provider.php:382-383`
   (`ProviderCancellationController`). L'argent traverse T1 + T2 + T3.
6. **Reprogrammation** — `CompanyController::rescheduleMission` `:1009` utilise
   `BookingRescheduleService`, décrit comme « strictement client/admin » avant
   (`routes/api/provider.php:586-587`). Service partagé T1/T2/T3.
7. **Canaux, messages, appels** — `routes/api/provider.php:593-624` : un émetteur, un destinataire,
   deux écrans. `ChannelPolicy` est commune.

### Avec T3 (admin)
8. **`routes/api/provider.php:462-464`** — une route **admin** vit dans le fichier de routes
   provider (`api.admin.onboarding.document.file`, gardée `['auth','role:admin','enforce_2fa']`).
   Elle sert `ProviderOnboardingController::downloadDocument`. **Fichier T2, route T3.**
9. **`provider.approved`** — `app/Http/Middleware/EnsureProviderIsApproved.php` : c'est l'admin qui
   approuve, le provider qui subit. Le middleware ne vise que les comptes portant
   `self_registered_at` (`routes/employe.php:36-37`) — les prestataires antérieurs traversent
   sans condition. **Décision T3 à confirmer.**
10. **Catalogue, tarifs, drapeaux** — `TradesAndZones` (`routes/employe.php:62`) écrit la couverture
    métier/zone que le dispatch lit ; l'admin ouvre ou ferme la zone. `trade_zone_pricing` est
    source unique, absence de ligne = fermé.
11. **`mobile/provider/src/admin/`** — la console admin native vit **dans l'application provider**.
    Frontière physique T2/T3 dans un même paquet npm.
12. **`mobile/shared/`** — code partagé par les deux applications natives (T1 + T2 + T3).

---

## 7. LES RISQUES LES PLUS SÉRIEUX (classés)

| # | Risque | Preuve | Ce que ça casse sur le terrain |
|---|---|---|---|
| 1 | **Onboarding prestataire sans porte web** | `Provider/Onboarding/ProviderOnboardingWizard.php` absent de toutes les routes ; API `routes/api/provider.php:388-410` | Un prestataire sans smartphone compatible **ne peut pas s'inscrire**. Le web promet un dossier à compléter (`routes/employe.php:39-44`) et n'en offre pas l'assistant. |
| 2 | **Contrôle facial sans porte web** | `Provider/FaceCheckPage.php` sans route ; API `:234-242` | `face.verified` **bloque** les gestes de terrain web (`routes/missions.php:20`) et le web n'offre aucun écran pour lever le blocage. Prestataire enfermé dehors : il ne peut ni démarrer, ni arriver, ni clôturer. |
| 3 | **`org.type:provider` absent de l'API société** | `routes/api/provider.php:480` vs `routes/company-dashboards.php:95` ; `ResolvesActiveOrganization.php:59-63` | La frontière client/provider n'est tenue que par le web. Trou d'authentification du type connu du dépôt. |
| 4 | **7 méthodes de canal sans garde d'organisation** | `CompanyController.php:1352, :1413, :1422, :1447, :1518, :1549, :1690` | Fils d'équipe et notes vocales d'une autre société potentiellement lisibles/écrivables. **Non confirmé par test.** |
| 5 | **19 routes sur 26 derrière `class_exists`** | `routes/employe.php:71,75,79,83,87,99,105,109,114,119,147,153,157,161,165,169,173,186,199,203` | Un renommage fait disparaître une route en silence. C'est la mécanique qui a produit les 7 modules injoignables du dépôt. |
| 6 | **8 domaines natif-seul** | tableau §5 | Parité TOTALE exigée par le produit ; présence, ASAP, inspection, flotte, assignations n'existent pas au navigateur. |
| 7 | **`tasks()` et `channels()` lisibles par un `worker`** | `CompanyController.php:738`, `:1279` (pas d'`exige()`), routes `:555`, `:593` sans `org.permission` | Un exécutant voit le carnet de tâches et les fils de sa société. |
| 8 | **Asymétrie `verified` / `active.account`** | `routes/employe.php:46` vs `routes/company-dashboards.php:95` | Un compte suspendu ou non vérifié garde potentiellement l'accès au tableau de bord prestataire individuel. **Cause non établie.** |

---

## 8. NON COUVERT — à reprendre

Zones de mon périmètre que je n'ai **pas** mesurées. Aucune conclusion ne doit en être tirée.

1. **`mobile/provider/` en entier (71 écrans).** Arbre de navigateurs, écrans orphelins, cibles
   `navigate('X')` mortes, gating de rôle dans la navigation. Un agent avait été lancé et n'a pas
   rendu. **C'est la moitié manquante du périmètre.**
2. **Confirmation des 14 composants Livewire sans route.** Il faut grepper `<livewire:` et
   `@livewire(` dans `resources/views/` pour séparer les composants **imbriqués** (sains) des
   **orphelins** (défauts). Suspects prioritaires : `MesRendezVous`, `GoogleAgendaEmploye`,
   `FeedbackStats`.
3. **Méthodes publiques Livewire qu'aucune Blade n'appelle** — le piège
   « sélecteur d'heures absent des vues ». Non commencé sur les 51 composants.
4. **`#[Locked]` sur les propriétés publiques.** Non vérifié : une propriété publique est une garde
   réversible par `$set`. À passer sur les 51 composants, en priorité `MissionFieldPage`,
   `TeamLeadOperationsCenter`, `RolePermissionsMatrix`, `DispatchCenter`.
5. **`boot{NomDuComposant}` inerte.** Non greppé.
6. **`ChannelPolicy`** — R2 ne sera un verdict qu'après lecture de la politique.
7. **`PermissionService`** (`app/Services/PermissionService.php`, 614 l.) — les grants par défaut
   par rôle, en particulier ce qu'un `owner` d'organisation CLIENTE obtient. C'est ce qui
   transforme R1 en fuite réelle ou en simple défaut de défense en profondeur.
8. **La part provider de `routes/api/` hors `provider.php`** : `v2-shared.php`, `realtime.php`,
   `channels.php`. Non lues.
9. **Séparation indépendant ↔ salarié de société.** `assignment_status = 'assigned'` est ambigu ;
   il faut discriminer sur `provider_organization_id`. Non mesuré.
10. **`OnSiteVerifier`** — le contournement du contrôle de présence faute de coordonnées.
    Non ouvert.
11. **Les trois checklists de mission.** `mission_checklists` seule bloque la clôture ;
    `routes/api/provider.php:368-369` et `routes/missions.php:24` touchent une checklist —
    laquelle n'est **pas vérifié**.
12. **Design / mode sombre / mouvement réduit** sur les vues Blade provider. Non ouvert.
