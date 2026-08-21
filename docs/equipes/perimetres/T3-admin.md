# T3 — ADMIN · carte du périmètre

**Mesuré le 2026-08-19 sur le code réel**, branche `main`, dépôt propre au démarrage.
Lecture seule : aucun fichier de code touché, aucune suite lancée.
Chaque affirmation porte son `fichier:ligne`. Rien n'est repris d'une documentation ni d'une
mémoire de session.

---

## 0. Le compte, d'abord

| Objet | Nombre | Source |
|---|---|---|
| Composants `app/Livewire/Admin/` | 105 | `find app/Livewire/Admin -name "*.php"` |
| Composants `app/Livewire/SuperAdmin/` | 2 | idem |
| **Total** | **107** | |
| Composants ayant leur PROPRE route de page | **94** | croisement `::class` × `routes/` |
| Composants rendus uniquement en panneau imbriqué (`<livewire:…>`) | **12** | voir §2.2 |
| Composants **injoignables par aucun chemin** | **2** | voir §2.1 — `Admin\AnalyticsCenter`, `Admin\MissionQualityAnalytics` |
| Tuiles de navigation `context => 'admin'` | **88** | `config/modules.php` |
| Modules de la console NATIVE | **86** | `config/admin_console.php` |
| Écrans natifs sur-mesure enregistrés | **0** | `mobile/provider/src/admin/nativeScreens.ts:19-21` |

Le périmètre est servi par **deux** fichiers de routes web, pas un :
- `routes/admin.php` (703 l.) — 89 routes nommées, chargé en `routes/web.php:34` ;
- `routes/missing-route-fixes-advanced.php` (328 l.) — 19 routes admin supplémentaires,
  chargé en `routes/web.php:42`, **après** le premier. Cet ordre est porteur : les gardes
  `if (! Route::has('admin.…'))` du second fichier ne se déclenchent que sur ce que le premier
  n'a pas déjà posé.
- plus `routes/integrations.php:23-24` (`admin.calendar.settings`) et
  `routes/api/provider.php:462-464` (la seule route d'administration hors des deux fichiers d'API
  admin, documentée comme telle ligne 457).

---

## 1. GARDES — ce qui sépare réellement les rôles

### 1.1 La pile web

`routes/admin.php:107` — le groupe entier :

```php
Route::middleware(['role:admin', 'enforce_2fa', 'module_gate'])->prefix('admin')->name('admin.')
```

posé **à l'intérieur** de `routes/web.php:29` qui ajoute `['auth', 'verified', 'active.account',
'phone.verified']`. `routes/missing-route-fixes-advanced.php:91` répète exactement la même triade —
le commentaire lignes 86-90 explique pourquoi : l'oublier laissait l'écran ouvert pendant que la
navigation cachait la tuile, « une porte invisible mais déverrouillée ».

- `role` → `App\Http\Middleware\CheckRole` (`app/Http/Kernel.php:123`).
  `CheckRole:20` appelle `matchesRole($role)`.
- `matchesRole('admin')` → `isAdmin()` (`app/Models/Concerns/HasUserTypeChecks.php:214`).
- `isAdmin()` (`app/Models/User.php:319-327`) est vrai pour `platform_role ∈ {admin, super_admin}`
  **et** conserve un repli hérité sur `$this->attributes['role']` (ligne 326).
- `matchesRole('super_admin')` (`HasUserTypeChecks.php:215`) compare à `roleCanonique()`, qui ne
  regarde **que** `platform_role` (`HasUserTypeChecks.php:256-258`) — **sans** le repli hérité.

> **Conséquence mesurée** : un compte dont seul l'ancien `role` vaut `super_admin`
> (`platform_role` nul) passe `role:admin` et échoue `role:super_admin`. L'asymétrie ferme plutôt
> qu'elle n'ouvre — ce n'est pas un trou, c'est un piège fonctionnel.

Défense en profondeur côté composant : le trait
`app/Support/Livewire/Concerns/EnforcesAdminAccess.php:20-24`, `bootEnforcesAdminAccess()`.
La forme est correcte — `boot{Trait}` EST appelé par Livewire, contrairement à
`boot{NomDuComposant}` qui est inerte. Le trait ne vérifie que « est-ce un administrateur »
(ligne 23) ; il ne connaît ni la capacité, ni le périmètre de zone, ni la lecture seule.

### 1.2 `admin` vs `super_admin` — le delta réel

`routes/admin.php:687-703` : un seul groupe `role:super_admin`, **deux routes** :

| Route | Composant | Ligne |
|---|---|---|
| `super-admin.dashboard` | `SuperAdmin\SuperAdminDashboard` | `routes/admin.php:692` |
| `super-admin.reglement` | `SuperAdmin\PlatformSettlement` | `routes/admin.php:702` |

C'est **tout**. Sur les 92 pages d'administration restantes, rien ne distingue les deux rôles.
Joignabilité vérifiée : `resources/views/navigation-menu.blade.php:171-172` affiche la porte si
`$roleCanonique === Role::SUPER_ADMIN`, et `livewire/super-admin/dashboard.blade.php:26-27` mène
au règlement. Les deux pages sont hors de `config/modules.php` — donc hors `module_gate` — ce qui
est cohérent avec le commentaire de `routes/admin.php:683-686`.

En sens inverse, `canAccessAdminModule` (`HasAdminCapabilities.php:121-124`) accorde **toutes** les
capacités au `super_admin` sans consulter sa liste de permissions. Un `super_admin` est donc un
`admin` total plus deux écrans.

### 1.3 La pile API — le jeton mobile porte `*`

`routes/api/admin.php:55` :

```php
Route::middleware(['auth:sanctum', 'api_admin', 'enforce_2fa'])->group(function () {
```

**Le verrou qui tient est `api_admin`, pas `api_scope`.** Chaîne de preuve :

1. `app/Http/Controllers/Api/Auth/ApiAuthController.php:159` et `:325` —
   `$user->createToken($deviceName)` **sans liste d'abilities**. Sanctum inscrit donc `['*']`.
2. `app/Http/Middleware/ApiTokensV2/EnforceTokenScope.php:77-79` —
   `if (in_array('*', $abilities, true)) { return $next($request); }`.
   Tout jeton mobile traverse n'importe quel `api_scope:…`.
3. `app/Http/Middleware/EnsureApiAdmin.php:54-60` — `if (! $user->isAdmin())` → 403
   `forbidden_not_admin`. Enregistré `api_admin` en `app/Http/Kernel.php:137`.

**Verdict : le correctif du 2026-08-03 tient.** Un provider ou un client porteur d'un jeton mobile
est refusé par `api_admin` avant d'atteindre le moindre contrôleur. La séparation 401/403 est
explicite (`EnsureApiAdmin:46-60`) et sert le client mobile.

**Trois réserves, mesurées :**

- **(a) Les paliers de scope sont décoratifs pour la console mobile.** `admin:read` /
  `admin:write` / `admin:critical` sont posés route par route
  (`routes/api/admin.php:58,63,115,147,205,312…`), mais un jeton `*` les franchit tous
  (`EnforceTokenScope.php:77`). Un administrateur ordinaire atteint donc depuis son téléphone
  `DELETE /api/admin/accounting-v2/entries/{entry}` (`routes/api/admin.php:128`),
  `DELETE /api/admin/api-tokens-v2/tokens/{token}` (`:152`) et
  `POST /api/admin/subscriptions-v2/subscriptions/{subscription}/force-cancel` (`:136`) — toutes
  déclarées `admin:critical`. Le palier ne filtre que les jetons d'intégration créés par
  `ApiTokenManager` (`app/Services/ApiTokensV2/ApiTokenManager.php:54`), qui portent, eux, une
  liste. Le commentaire de `routes/api/admin.php:36-42` le dit d'ailleurs sans détour.
- **(b) `enforce_2fa` dépend d'une variable d'environnement.**
  `app/Http/Middleware/Enforce2FA.php:36` : `if (! config('auth.enforce_2fa_for_admins', false))
  return $next($request);`. Le défaut est vrai (`config/auth.php:129`,
  `env('ENFORCE_2FA_FOR_ADMINS', true)`) — mais une seule ligne d'environnement désarme la 2FA du
  web **et** de l'API d'un coup.
- **(c) `EnforceTokenScope.php:31-58` — le chemin Mockery.** Il est refusé en production
  (ligne 38), donc fermé ; mais lignes 53-58, tout `\Throwable` **et** la sortie de boucle
  rendent `$next($request)`. En dev/test, ce middleware ne refuse jamais un mock. Un test de refus
  écrit sur `api_scope` seul mesurerait une panne, pas une garde.

### 1.4 `module_gate` — et son absence totale côté API

`app/Http/Middleware/EnforceModuleGate.php:42-48` : la capacité vient de
`config('modules.catalogue')` et se cherche **par nom de route** (`:58-77`). Les 22 capacités sont
définies en `app/Providers/AuthServiceProvider.php:38-56`, toutes déléguées à
`canAccessAdminModule()` (`HasAdminCapabilities.php:109-140`). Un module sans clé `gate` reste
ouvert (`EnforceModuleGate:44`).

> **C'est le trou le plus large du périmètre.** Les routes `/api/admin/*` n'ont **aucun nom** et
> **aucune entrée** dans `config/modules.php` ; `module_gate` n'est pas dans la pile de
> `routes/api/admin.php:55`. Et `config/admin_console.php` ne contient **aucune** clé `gate`,
> `permission` ni `capability` (0 occurrence sur 86 modules). Vérifié aussi dans les contrôleurs :
> `ResourceController.php`, `ReportController.php` et `AdminCatalogController.php` ne contiennent
> **aucun** `Gate::`, aucun `canAccessAdminModule`, aucun `isZoneScopedAdmin`.

Traduit : un administrateur dont les `permissions` ne portent que `manage-quality` reçoit un 403
sur `/admin/accounting-v2` (tuile `config/modules.php:243`, gate `manage-accounting`) et obtient
**200** sur `GET`/`POST`/`DELETE /api/admin/accounting-v2/entries` depuis la console native. Même
raisonnement pour les 21 autres capacités. C'est la famille « gardes web absentes de l'API », déjà
connue de ce dépôt, mais transposée d'un cran : ce n'est plus une garde d'authentification, c'est
la totalité du modèle d'habilitation admin qui n'existe que sur le web.

Le seul contrôle fin qui existe côté API est la lecture seule :
`ResourceController.php` — `refuseLecteurSeul()`, `isReadOnlyAdmin()` (`HasAdminCapabilities.php:201-206`).
Il ne couvre ni la capacité, ni la zone.

**Le périmètre de zone subit le même écart.** `isZoneScopedAdmin()`
(`HasAdminCapabilities.php:208-215`) n'est lu que par 6 emplacements sous `app/Livewire/Admin/`
(`AgendaHebdomadaire`, `AuditLogsCenter`, `GestionUtilisateurs`, `PlanningAdmin`), plus
`routes/admin.php:598` et `routes/missing-route-fixes-advanced.php:277`. Le moteur de console
natif ne le lit nulle part : un administrateur régional voit la plateforme entière depuis son
téléphone.

### 1.5 Propriétés Livewire publiques — gardes réversibles

6 composants sur 107 portent au moins un `#[Locked]` :
`Availability/ProviderAvailabilityDetail` (3), `CancellationV2/QuestionnaireCenter` (3),
`FaceCheck/FaceCheckCenter` (3), `MarketplaceHealthCenter` (3), `OrderEngine/CountryCenter` (3),
`OrderEngine/QuestionnaireBuilder` (2), `Rental/NosLocationsCenter` (5).

**101 composants n'en portent aucun.** Exemple relevé sans chercher :
`app/Livewire/Admin/CatalogueServices.php:65` et `:67` — `public $selectedServiceId` et
`public $selectedZoneId`, non typées, non verrouillées, sur l'écran qui règle le catalogue de
services (`admin.services`, `routes/missing-route-fixes-advanced.php:155-157`). Le navigateur peut
les retourner par `$set`. Un balayage exhaustif de ces 101 composants **n'a pas été fait** — voir
§7.

---

## 2. JOIGNABILITÉ — les trous

### 2.1 Les deux composants injoignables (défaut dominant du dépôt)

| Composant | Route | Vue | Appelants |
|---|---|---|---|
| `app/Livewire/Admin/AnalyticsCenter.php` | **aucune** | `livewire.admin.analytics-center` (`:335`) | **aucun** hors 2 fichiers de test |
| `app/Livewire/Admin/MissionQualityAnalytics.php` | **aucune** | — | uniquement `resources/views/livewire/admin/analytics-center.blade.php:58`, c'est-à-dire la vue du précédent |

Preuve du premier : `grep -rn "Admin\\\\AnalyticsCenter" app/ routes/ resources/ config/ tests/` ne
rend que `tests/Feature/AdminAnalyticsCenterCoverageTest.php:23` et
`tests/Feature/AdminAnalyticsCenterExperienceTest.php:9`. **Le nom court a déjà menti** : un
`grep "AnalyticsCenter::class" routes/` répond « routé » parce qu'il attrape
`App\Livewire\Admin\Analytics\AnalyticsCenter` — un homonyme dans un autre espace de noms,
importé en `routes/admin.php:11` et servi par `/admin/analytics-v2` (`:338`), dont la vue est
`livewire.admin.analytics.analytics-center` (`app/Livewire/Admin/Analytics/AnalyticsCenter.php:51`).
Deux classes, deux vues, un seul nom court. **C'est exactement l'inventaire vert qui cache un trou
de rendu.** Les deux tests passent : ils montent le composant directement, ce que personne ne peut
faire depuis un navigateur.

### 2.2 Les 12 composants rendus en panneau — vérifiés, tous atteignables

Aucun n'a de route ; tous ont un parent réel, et chaque parent a été remonté jusqu'à une route :

| Composant | Rendu dans | Parent routé |
|---|---|---|
| `AgendaHebdomadaire` | `livewire/admin/planning/weekly-agenda.blade.php:23` ← `planning-admin.blade.php:8` | `admin.planning` |
| `EmployeePerformance` | `livewire/admin/dashboard/supplemental-livewire.blade.php:9` ← `admin-dashboard.blade.php:34` | `admin.dashboard` |
| `FeedbackStats` | `livewire/admin/dashboard/modules-section.blade.php:25` ← `admin-dashboard.blade.php:30` | `admin.dashboard` |
| `GestionUtilisateurs` | `livewire/admin/dashboard/embedded-modules.blade.php:59` | `admin.dashboard` |
| `RhQualityScores` | `livewire/admin/dashboard/embedded-modules.blade.php:25` | `admin.dashboard` |
| `ImportCsv` | `livewire/admin/outils-admin.blade.php:19` | `admin.outils` |
| `LogsActivity` | `livewire/admin/outils-admin.blade.php:38` | `admin.outils` |
| `OutilsDeTest` | `livewire/admin/outils-admin.blade.php:42` | `admin.outils` |
| `StatsGlobale` | `livewire/admin/outils-admin.blade.php:23` | `admin.outils` |
| `ExportTools` | `livewire/admin/outils-admin.blade.php` | `admin.outils` |
| `MissionHistoryPanel` | `admin/missions/show.blade.php:125` | `admin.missions.show` |
| `TradeFormPreview` | `livewire/admin/partials/trade-form-fields.blade.php:165` | `admin.trades` |

Note : `ExportTools` est aussi cité en `routes/missing-route-fixes-advanced.php:143` comme 3ᵉ
candidat d'`admin.outils`, mais `OutilsAdmin` (1er, ligne 141) existe — donc la route ne le sert
jamais. Il n'est vivant que par le panneau.

### 2.3 Une tuile qui promet un module et rend un gabarit vide

`config/modules.php:235` déclare la tuile **Automation** (`admin.automation`, gate
`manage-automation`). La route existe (`routes/missing-route-fixes-advanced.php:198-203`) et essaie
`AutomationCenter::class` puis `AdminAutomationCenter::class`. **Les deux classes sont absentes du
dépôt.** La route retombe donc sur `$fallbackPage` (`:52-66`) qui rend
`resources/views/admin/module-a-connecter.blade.php`. Un administrateur clique une case de son menu
et arrive sur « module à connecter ». Aucune autre tuile n'est dans ce cas : les 87 autres
résolvent vers une classe présente (contrôle fait sur les 21 noms candidats des tableaux
`$livewireOrFallback`).

### 2.4 Routes admin sans tuile — toutes justifiées, sauf à vérifier

19 routes admin n'ont pas de tuile. Elles se répartissent ainsi, et chacune a été remontée à un
lien réel :

- **Détails paramétrés** liés depuis leur parent : `admin.trades.pricing`
  (`livewire/admin/trades.blade.php:144`), `admin.order-engine.builder`
  (`livewire/admin/order-engine/catalog-center.blade.php:256`), `admin.order-engine.zones`,
  `admin.order-engine.catalog.zone`, `admin.availability.provider` (`routes/admin.php:348-352`
  documente le lien), `admin.missions.show`, `admin.recurrence.edit`, `admin.rendezvous.show`,
  `admin.rendezvous.series.edit`.
- **Téléchargements et exports** : `admin.missions.export.pdf`,
  `admin.quality.export.incidents.csv`, `admin.quality.export.missions.csv`,
  `admin.feedbacks.export`, `admin.feedbacks.export.csv`, `admin.export.pdf`, `admin.export.csv`.
- **URL signées** : `admin.face-check.reference`, `admin.face-check.selfie`,
  `admin.onboarding.document.file` — `middleware('signed')`, hors menu par construction.
- `admin.modules.directory` — lié en `resources/views/navigation-menu.blade.php:57`.

**Aucun trou de menu sur le web**, donc, en dehors de §2.3.

### 2.5 Collisions de noms de route à surveiller

`admin.feedbacks.export` est déclarée **trois** fois : `routes/admin.php:580`,
`missing-route-fixes-advanced.php:121` (sans `->name()`, donc anonyme) et `:244`. L'ordre de
chargement (`web.php:34` avant `:42`) fait que `Route::has()` est vrai aux lignes 120 et 243, et
que ces deux blocs sont sautés. Le comportement est correct **aujourd'hui**, et il dépend
entièrement de l'ordre de deux `require`. `admin.missions` est également déclarée deux fois dans
`routes/admin.php` (`:131` et `:135`), sur les deux branches d'un `if/else` — celle-là est saine.

---

## 3. PARITÉ WEB ↔ NATIF

### 3.1 L'architecture native n'est pas un miroir d'écrans

`mobile/provider/src/admin/` — 31 fichiers. Il n'y a **pas** un écran par module : il y a un
**moteur**.

- Navigateur : `mobile/provider/src/admin/AdminNavigator.tsx:41-74` — **4 onglets seulement** :
  `AdminHome` (`:42`), `AdminDirectory` (`:51`), `AdminCatalog` → `CatalogCountriesScreen` (`:60`),
  `AdminProfile` (`:69`).
- Point d'entrée : `mobile/provider/src/navigation/RootNavigator.tsx:360` —
  `<Stack.Screen name="AdminSpace" component={AdminNavigator} />`, monté quand
  `resolveSpace()` rend `'admin'` (`mobile/provider/src/admin/space.ts:119-121`).
- L'annuaire ouvre chaque module par `AdminDirectoryScreen.tsx:81-111` : écran sur-mesure si
  `NATIVE_ADMIN_SCREENS[module.key]`, sinon `AdminReport` si `coverage === 'report'`, sinon la
  liste générique `AdminResourceList`.
- Le serveur décrit les 86 modules dans `config/admin_console.php`, servis par
  `AdminCatalogController` (`routes/api/admin.php:247`) et rendus par le moteur
  `ResourceController` (`:308-319`) et `ReportController` (`:306`).

**`NATIVE_ADMIN_SCREENS` est VIDE** — `mobile/provider/src/admin/nativeScreens.ts:19-21`, commentaire
« Renseigné domaine par domaine (sous-projet C, tâches 2 à 5). » Donc **aucun** module n'ouvre
d'écran sur-mesure ; tout passe par la liste générique ou le rapport.

Répartition mesurée de `config/admin_console.php` : **73 `descriptor`, 12 `report`, 1 `pending`**.
Le `pending` est `face-check` (`config/admin_console.php:174`), affiché mais désactivé
(`AdminDirectoryScreen.tsx:152` : `const disponible = module.coverage !== 'pending'`).

### 3.2 Écarts web → natif

| Domaine | Web | Natif | Écart |
|---|---|---|---|
| Vérification faciale | `admin.face-check.center`, `routes/admin.php:366-368`, `can:manage-face-check` | `coverage: 'pending'`, `config/admin_console.php:174` | tuile grisée ; **le seul module de conformité RGPD art. 9 n'est pas exploitable en mobilité** |
| Registre de règlement (super_admin) | `super-admin.reglement`, `routes/admin.php:702` | **absent** | voir §3.3 |
| 22 capacités `manage-*` | `module_gate` sur chaque route | **aucune** | §1.4 |
| Périmètre de zone (`managed_service_zone_id`) | 6 composants Livewire | **aucun** | §1.4 |
| Constructeur de parcours | `admin.order-engine.builder` (Livewire) | `catalogue/JourneyBuilderScreen.tsx` + `routes/api/admin.php:289-298` | **parité réelle** — les deux appellent `QuestionnaireValidator` / `TradeFormPublisher` (commentaire `routes/api/admin.php:284-288`) |
| Catalogue géographique | `CountryCenter` → `ZoneCenter` → `CatalogCenter` | `CatalogCountriesScreen` → `CatalogZonesScreen` → `CatalogZoneTradesScreen` | **parité réelle**, l'onglet 3 du navigateur |

### 3.3 Le super administrateur natif est un écran d'information

`RootNavigator.tsx:326-353` : quand `space === 'superAdmin'`, la fonction **retourne tôt** avec une
pile de 5 écrans — `SuperAdminSpace`, `Modules`, `Appearance`, `Language`,
`NotificationPreferences`. `AdminNavigator` n'y est **pas monté**.

`SuperAdminHomeScreen.tsx` liste les six rôles et les sous-rôles société (`:66-94`) ; ses seules
actions sont `choose('admin')` (`:115`), `choose('provider')` (`:122`), `navigate('Modules')`
(`:132`) et `navigate('Appearance')` (`:138`).

Deux constats :
1. La porte vers la console **existe** (`choose('admin')`) — le super administrateur n'est pas
   enfermé. `space.ts:106-113` le renvoie sur `superAdmin` tant qu'il n'a rien choisi
   explicitement, ce qui est une décision assumée et commentée (`space.ts:95-105`).
2. En revanche **`PlatformSettlement` — la seule capacité exclusive du super administrateur — n'a
   aucun équivalent natif**, ni écran, ni entrée dans `config/admin_console.php`, ni route
   `/api/super-admin/*` (aucune n'existe). Le sixième rôle est, en mobilité, un cinquième rôle avec
   un écran d'accueil différent.

---

## 4. LEVIERS ADMIN — ce que T1 et T2 subissent

Ce sont les points d'arbitrage T4. Chacun est un écran admin qui **ouvre ou ferme** une capacité en
aval, sans que le consommateur puisse s'en défendre.

| # | Levier | Écran admin | Ce qui le lit en aval | Effet d'une fermeture |
|---|---|---|---|---|
| L1 | **Ouverture métier × zone** (`trade_zone_pricing`) | `Admin\OrderEngine\CatalogCenter` (`routes/admin.php:663`) et `Api\Admin\ZoneCatalogController::toggle` (`routes/api/admin.php:273`) | `OrderJourney`, `PricingEngine`, `ZonePricingResolver`, `HourlyRateResolver`, `TradePricingEngine`, `SurgePricingEngine`, `RegistrationOptionsService`, `Trade`, `ServiceZone` | **T1** : le service disparaît du parcours de commande. **T2** : le prestataire n'est plus candidat. Absence de ligne = fermé, pas « par défaut ». |
| L2 | **Tarifs et grille km/min** | `TradeZonePricingManager` (`routes/admin.php:623`) ; API `updateDistancePricing` (`routes/api/admin.php:282`) | mêmes moteurs que L1 | **T1** : le prix affiché change sans préavis. **T2** : la rémunération change. Les 5 réglages sont écrits en un seul appel — commentaire `routes/api/admin.php:276-281`. |
| L3 | **ASAP par zone** | `toggleAsap` (`routes/api/admin.php:275`) | moteur de répartition | **T1** : plus de course immédiate. **T2** : plus d'offres ASAP. |
| L4 | **Questionnaire de commande** | `QuestionnaireBuilder` (`routes/admin.php:667`) / `JourneyBuilderController` (`routes/api/admin.php:289-298`) | `OrderJourney`, moteur tarifaire | **T1** : les questions posées et le prix construit changent. Une publication est un changement de contrat client. |
| L5 | **Drapeaux de fonctionnalité** | `FeatureFlagsManager` (`routes/admin.php:514`) — écrit `feature_flag_overrides` (`:34-45`, `:62-88`) et surcharge `config/features.php` (`:103`) | tout le dépôt | **T1 et T2** : un écran entier apparaît ou disparaît à chaud, journalisé (`ActivityLogger::log('feature_flag.updated')`, `:80`) mais sans revue. |
| L6 | **Modules de la plateforme** | `PlatformModulesCenter` (`routes/missing-route-fixes-advanced.php:206`, `can:manage-modules`) | `config/modules.php` → navigation ET `module_gate` | **T1 et T2** : la même déclaration cache la tuile et ferme l'écran (`EnforceModuleGate:28-30`). |
| L7 | **Commissions** | pas d'écran dédié identifié ; `Admin\Payments\StripeHardeningCenter`, `BusinessDashboard` | `CommissionService`, `MissionLifecycleService`, `PayoutAnnouncementService`, `ProviderProfile`, `HasProviderFeatures` | **T2** : la part reversée. Le point d'écriture admin **n'a pas été localisé** — voir §7. |
| L8 | **Annulation : politiques, questionnaire, dérogations** | `CancellationsCenter` (`routes/admin.php:424`), `QuestionnaireCenter` (`:437`), API `adminOverride` (`routes/api/admin.php:201`) | frais d'annulation client et clawback prestataire | **T1 + T2 + T3** : c'est la frontière argent des trois périmètres. |
| L9 | **Zones de service** | `GestionZones` (`routes/admin.php:126`) | tout le matching géographique | **T1** : adresse hors zone = pas de service. **T2** : périmètre de travail. |
| L10 | **Approbation d'inscription prestataire** | `ProviderRegistrationsCenter` (`routes/admin.php:629`) | middleware `provider.approved` | **T2** : un compte créé reste bloqué tant qu'un admin n'a rien fait. |
| L11 | **KYC / KYB / Risk / Insurance** | `routes/admin.php:243, 502, 382, 394` + API `routes/api/admin.php:63-67, 103-112, 235-238` | accès au travail et au paiement | **T2** : suspension de compte. **T1** : hold sur un paiement. |
| L12 | **Catalogue de services et métiers** | `Trades` (`routes/admin.php:621`), `CatalogueServices` (`routes/missing-route-fixes-advanced.php:155`) | parcours de commande, inscription prestataire | **T1 + T2** : ce qui existe, tout simplement. |

**Les leviers L1 à L4 et L8 sont manipulables depuis la console native SANS aucune vérification de
capacité ni de zone** (§1.4). C'est le point que T4 doit trancher en premier : le modèle
d'habilitation admin n'existe que d'un côté.

---

## 5. CARTE DES POINTS D'ENTRÉE PAR DOMAINE

Format : `nom de route` → composant (`routes/admin.php:ligne`) · tuile `config/modules.php:ligne` ·
module natif `config/admin_console.php`.

**Catalogue géographique & métiers** — `admin.order-engine.catalog` → `OrderEngine\CountryCenter`
(`:658`, tuile `:328`) · `admin.order-engine.zones` → `ZoneCenter` (`:661`) ·
`admin.order-engine.catalog.zone` → `CatalogCenter` (`:664`) · `admin.order-engine.builder` →
`QuestionnaireBuilder` (`:667`) · `admin.trades` → `Trades` (`:621`, tuile `:331`) ·
`admin.zones` → `GestionZones` (`:126`, tuile `:269`) · `admin.countries` →
`CountryOperationsCenter` (`missing-route-fixes:215`, tuile `:323`) · `admin.services` →
`CatalogueServices` (`missing-route-fixes:155`, `can:manage-services`, tuile `:265`).
Natif : onglet `AdminCatalog` → `CatalogCountriesScreen` / `CatalogZonesScreen` /
`CatalogZoneTradesScreen` / `JourneyBuilderScreen`.

**Tarification** — `admin.trades.pricing` → `TradeZonePricingManager` (`:623`) ·
`admin.pricing-v2.center` → `PricingV2\PricingCenter` (`:449`, tuile `:254`).
API : `routes/api/admin.php:75-77` (lecture des devis), `:282` (grille distance).

**Comptabilité & argent** — `admin.accounting-v2.center` → `AccountingV2\AccountingCenter`
(`:491`, tuile `:243`, gate `manage-accounting`) · `admin.finance` → `FinanceCenter`
(`missing-route-fixes:132`, tuile `:253`) · `admin.business.dashboard` (`:548`) ·
`admin.b2b.monthly-invoices` (`:557`) · `admin.customer.credits` (`:207`) · `admin.tips.center`
(`:267`) · `admin.fx.center` (`:400`) · `admin.subscriptions-v2.center` (`:485`) ·
`admin.stripe.hardening` (`:225`) · `admin.stripe-connect.providers` (`:531`) ·
`super-admin.reglement` → `PlatformSettlement` (`:702`).
API : `routes/api/admin.php:115-129` (écritures, clôture, réouverture, export, suppression).

**KYC / KYB / conformité** — `admin.kyc.center` (`:243`) · `admin.kyb-v2.center` (`:502`) ·
`admin.gdpr.center` (`:249`) · `admin.insurance.center` (`:394`) · `admin.face-check.center`
(`:366-368`, `can:manage-face-check`, + deux routes `signed` `:373`/`:377`).
API : `routes/api/admin.php:103-112` (KYB), `:235-238` (assurance).

**Litiges & qualité** — `admin.disputes.center` (`:237`) · `admin.quality.center` (`:418`) ·
`admin.ratings.moderation` (`:213`) · `admin.safety.center` (`:305`) · `admin.badges.center`
(`:310`) · `admin.nps.center` (`:290`) · `admin.feedbacks` (`missing-route-fixes:113`).
API : `routes/api/admin.php:188-191`.

**Fraude / risque** — `admin.risk.center` (`:382`) · API `routes/api/admin.php:63-67`.

**Journal d'audit** — `admin.audit.center` (`:406`, tuile `:290`) · `admin.audit.logs` →
`AuditLogsCenter` (`missing-route-fixes:148`, tuile `:339`). **Deux écrans, deux notions** — à
vérifier qu'ils ne décrivent pas le même objet (§7).
API : `routes/api/admin.php:179-185`.

**Drapeaux & plateforme** — `admin.feature-flags.manager` (`:514`) · `admin.modules` →
`PlatformModulesCenter` (`missing-route-fixes:206`, `can:manage-modules`) ·
`admin.modules.directory` → `Shared\ModulesDirectory` (`:123`) · `admin.platform.readiness`
(`:552`) · `admin.api-tokens-v2.center` (`:473`) · `admin.webhooks-v2.center` (`:461`) ·
`admin.geolocation-v2.center` (`:467`) · `admin.translations.center` (`:231`).

**Supervision / répartition** — `admin.dispatch.center` → `DispatchCenter` (`:544`) ·
`admin.ai.dispatch` (`:535`) · `admin.marketplace.health` (`:302`, non conditionnée par
`class_exists`) · `admin.presence.center` (`:279`) · `admin.trip-tracking.center` (`:273`) ·
`admin.availability.center` (`:345`) + `admin.availability.provider` (`:356`) ·
`admin.matching.insights` (`:219`) · `admin.alerts` (`:199`) · `admin.orchestration`
(`missing-route-fixes:192`) · `admin.missions` (`:131`/`:135`) + `admin.missions.show`
(`:141`, `can:view,mission`).

**Utilisateurs & sociétés** — `admin.utilisateurs.manage` → `UtilisateursAdmin` (`:189`) ·
`admin.entreprises` (`:127`) · `admin.sites` (`:566`) · `admin.enterprise.approvals` (`:562`) ·
`admin.teams.partners` (`missing-route-fixes:175`, `can:manage-entreprises`) ·
`admin.premium.clients` (`missing-route-fixes:160`) · `admin.b2b.operations`
(`missing-route-fixes:168`) · `admin.international` (`missing-route-fixes:185`) ·
`admin.providers.registrations` (`:629`) · `admin.onboarding.providers` (`:633`) ·
`admin.onboarding.documents` (`:636`) + `admin.onboarding.document.file` (`:640`, `signed`) ·
`admin.onboarding-v2.center` (`:443`).

**Contenus & communication** — `admin.emails` → `ProductEmailsCenter`
(`missing-route-fixes:220`) · `admin.chat-v2.center` (`:479`) · `admin.sms.center` (`:321`) ·
`admin.push.center` (`:327`) · `admin.realtime.center` (`:333`) ·
`admin.notification-preferences.center` (`:412`) · `admin.marketing.center` (`:388`) ·
`admin.promotions.codes` / `.campaigns` / `.referrals` (`:520`, `:523`, `:526`) ·
`admin.loyalty.center` (`:255`) + `admin.loyalty.rewards.center` (`:261`).

**Locations & flotte** — `admin.rentals.center` → `Rental\NosLocationsCenter` (`:496`,
tuile `:238`, gate `manage-rentals`) · `admin.fleet-v2.center` (`:508`) · `admin.bundles.center`
(`:315`).
**Piège de structure** : `admin.rentals.center` est déclarée **à l'intérieur** du
`if (class_exists(AccountingCenter::class))` ouvert ligne 490. Si `AccountingCenter` disparaissait,
« Nos locations » disparaîtrait avec, sans rapport aucun.

---

## 6. LES 8 RISQUES LES PLUS SÉRIEUX — classés

### R1 — Le modèle d'habilitation admin n'existe que sur le web
**Preuve** : `routes/api/admin.php:55` (pile sans `module_gate`) ·
`app/Http/Middleware/EnforceModuleGate.php:58-77` (résolution par nom de route web) ·
`config/admin_console.php` : 0 occurrence de `gate`/`permission`/`capability` sur 86 modules ·
`ResourceController.php` / `ReportController.php` / `AdminCatalogController.php` : 0 `Gate::`.
**Casse pour l'admin** : les 22 capacités de `AuthServiceProvider.php:38-56` sont contournables par
la console native. Un admin « qualité » écrit dans le grand livre.
**Casse en aval (T1/T2)** : les leviers L1-L4 et L8 (§4) — catalogue, prix, ASAP, dérogations
d'annulation — deviennent modifiables par tout compte `isAdmin()`, sans capacité ni zone.

### R2 — Le périmètre de zone ne survit pas au passage en natif
**Preuve** : `HasAdminCapabilities.php:208-215` lu par 6 emplacements seulement sous
`app/Livewire/Admin/` + `routes/admin.php:598` + `missing-route-fixes-advanced.php:277` ; aucun
dans `app/Http/Controllers/Api/Admin/`.
**Casse pour l'admin** : un administrateur régional voit et modifie la plateforme entière depuis
son téléphone.
**Casse en aval** : données de clients et de prestataires d'autres pays exposées à un compte qui
n'y a pas droit sur le web — RGPD.

### R3 — Deux composants complets et injoignables
**Preuve** : `app/Livewire/Admin/AnalyticsCenter.php` (0 route, 0 appelant hors tests) et
`app/Livewire/Admin/MissionQualityAnalytics.php` (seul appelant :
`resources/views/livewire/admin/analytics-center.blade.php:58`, la vue du précédent).
Deux tests verts les couvrent (`tests/Feature/AdminAnalyticsCenterCoverageTest.php:23`,
`AdminAnalyticsCenterExperienceTest.php:9`) en les montant directement.
**Casse pour l'admin** : deux écrans d'analyse écrits, maintenus, testés, que personne n'a jamais
ouverts. Le nom court `AnalyticsCenter` est partagé avec un homonyme routé — tout inventaire par
`grep` de nom court dira « couvert ».
**Casse en aval** : aucune ; c'est du travail perdu, pas un défaut de production.

### R4 — Les paliers `admin:read` / `write` / `critical` ne filtrent aucun administrateur
**Preuve** : `ApiAuthController.php:159` et `:325` (`createToken` sans abilities) ·
`EnforceTokenScope.php:77-79` (`'*'` traverse tout).
**Casse pour l'admin** : la distinction lecture / écriture / critique posée sur 20 groupes de
routes (`routes/api/admin.php`) ne sépare rien. La suppression d'une écriture comptable (`:128`),
la révocation d'un jeton (`:152`) et l'annulation forcée d'un abonnement (`:136`) sont accessibles
à tout jeton mobile d'administrateur.
**Casse en aval (T1)** : une annulation forcée d'abonnement est un geste facturable côté client.

### R5 — Une tuile de menu qui mène à un gabarit vide
**Preuve** : `config/modules.php:235` (tuile Automation) ·
`routes/missing-route-fixes-advanced.php:198-203` · `AutomationCenter` et `AdminAutomationCenter`
absents du dépôt (vérifié par `find app/Livewire -name`).
**Casse pour l'admin** : la navigation promet un module d'automatisation et rend
`admin.module-a-connecter`. Le catalogue est censé être la source unique des points d'entrée
(`config/modules.php:4-11`) ; il ment ici.
**Casse en aval** : aucune directe, mais la tuile porte le gate `manage-automation`, donc la
capacité est distribuée pour rien.

### R6 — 101 composants sur 107 sans `#[Locked]`
**Preuve** : 6 fichiers seulement contiennent `#[Locked]` sous `app/Livewire/Admin/`.
Exemple non cherché : `app/Livewire/Admin/CatalogueServices.php:65` et `:67` —
`public $selectedServiceId`, `public $selectedZoneId`, non typées, sur l'écran de gestion du
catalogue (`can:manage-services`).
**Casse pour l'admin** : toute propriété publique servant de contexte est retournable par `$set`
depuis le navigateur ; une garde écrite dessus n'en est pas une.
**Casse en aval** : si la propriété désigne un service ou une zone, l'écriture atterrit ailleurs
que là où l'écran l'affichait — L1/L12 de §4.
**Non mesuré** : lesquelles de ces 101 servent effectivement de garde. Voir §7.

### R7 — Le super administrateur natif n'atteint pas sa seule capacité propre
**Preuve** : `RootNavigator.tsx:326-353` (pile `superAdmin` sans `AdminNavigator`) ·
`SuperAdminHomeScreen.tsx:112-140` (4 actions, aucune vers le règlement) · aucune route
`/api/super-admin/*` dans `routes/api/` · `PlatformSettlement` absent de `config/admin_console.php`.
**Casse pour l'admin** : le sixième rôle est, en mobilité, un cinquième rôle. La porte
`choose('admin')` existe (`:115`), donc il n'est pas enfermé — mais le registre de règlement,
justement placé derrière `enforce_2fa` parce qu'il expose la banque de la plateforme
(`routes/admin.php:694-699`), n'est joignable que depuis un navigateur.
**Casse en aval** : aucune.

### R8 — `enforce_2fa` tient à une variable d'environnement, et `Enforce2FA` ne connaît que `isAdmin()`
**Preuve** : `app/Http/Middleware/Enforce2FA.php:36` (sortie immédiate si le drapeau est faux) ·
`config/auth.php:129` (`env('ENFORCE_2FA_FOR_ADMINS', true)`) · `Enforce2FA:42`
(`$user->isAdmin()`).
**Casse pour l'admin** : une ligne d'environnement désarme simultanément la 2FA du web et celle de
`/api/admin/*` — c'est-à-dire la garde ajoutée le 2026-08-16 précisément parce que la console est
native. Le repli hérité d'`isAdmin()` (`User.php:326`) fait par ailleurs entrer dans le champ de
la 2FA des comptes que `roleCanonique()` ne reconnaît pas comme administrateurs.
**Casse en aval** : le chemin de l'argent (§4, L7/L8) devient accessible sur mot de passe seul.

**Mention hors classement — `EnforceTokenScope.php:53-58`** : le chemin Mockery rend
`$next($request)` sur tout `\Throwable` **et** en sortie de boucle. Fermé en production
(`:38`), mais tout test de refus écrit contre `api_scope` seul passera au vert en mesurant ce
repli, jamais une garde. Témoin positif obligatoire.

---

## 7. NON COUVERT — à reprendre

Ces points sont ouverts ; ils n'ont pas été mesurés et ne doivent pas être présumés sains.

1. **Balayage `#[Locked]` exhaustif** — les 101 composants sans verrou n'ont pas été lus un par un.
   Il faut distinguer les propriétés publiques d'affichage (sans enjeu) de celles qui portent un
   identifiant ou un périmètre. `CatalogueServices.php:65,67` est un exemple trouvé au premier
   coup de sonde, pas le résultat d'un balayage.
2. **Le point d'écriture des commissions (levier L7)** — `CommissionService`,
   `PayoutAnnouncementService`, `ProviderProfile` et `HasProviderFeatures` la lisent ; **quel écran
   admin l'écrit n'a pas été localisé**. Si personne ne l'écrit, c'est un levier subi par T2 que
   T3 ne tient pas.
3. **`admin.audit.center` vs `admin.audit.logs`** — deux écrans (`AuditCenter` et
   `AuditLogsCenter`), deux tuiles (`config/modules.php:290` et `:339`), même gate
   `manage-audit-logs`. Décrivent-ils le même objet ? C'est le motif « deux notions, un
   événement » du dépôt. Non tranché.
4. **Le contenu réel des 86 descripteurs** de `config/admin_console.php` : quels champs de
   formulaire chaque module expose, et donc ce qu'un administrateur peut réellement écrire par
   `POST /api/admin/console/{resource}`. Le garde-fou « un champ non déclaré n'est jamais écrit »
   est documenté dans `ResourceController.php` (en-tête, point 3) mais **n'a pas été vérifié dans
   le code**.
5. **Les 12 modules `coverage: 'report'`** — `ReportController` (`routes/api/admin.php:306`) n'a
   pas été lu.
6. **Les décisions de lecture seule assumées** — la note mémoire
   `admin_console_readonly_rationale` existe ; le document `docs/` correspondant n'a pas été
   relu. Aucun manque de fonctionnalité n'est signalé ici de ce fait : §6 ne contient que des
   écarts de GARDE et de JOIGNABILITÉ, pas des « il manque un bouton ».
7. **`resources/views/admin/gestion-utilisateurs-page.blade.php` et
   `resources/views/admin/countries-page.blade.php`** — vues sans aucun appelant (`grep` sur le nom
   de vue : 0 résultat). Ce sont des vues orphelines, pas des composants orphelins ; à confirmer
   avant suppression.
8. **La console native n'a pas été exécutée.** Tout ce qui est dit en §3 vient de la lecture des
   fichiers TypeScript ; `tsc` et `jest` ne prouvent pas la joignabilité d'un écran sur ce dépôt.

---

## 8. Verdict du chef d'équipe T3

**Reconnaissance : ✅ livrée**, avec les 8 réserves du §7 nommées et non maquillées.

Le périmètre est **beaucoup plus sain sur le web qu'en natif**. Sur le web : 88 tuiles, 88 routes,
un seul écart réel (R5) et deux composants morts (R3). En natif : le moteur de console couvre 86
modules mais **n'applique aucune des 22 capacités ni le périmètre de zone** (R1, R2), et les
paliers de scope ne filtrent aucun administrateur (R4).

**Ce que T3 demande à T4 d'arbitrer avant tout lot de modification** : R1 et R2 touchent les
leviers L1-L4 et L8, c'est-à-dire le catalogue, les prix et l'annulation — trois zones que la
charte range explicitement en frontière partagée T1 + T2 + T3. Aucune correction ne peut y être
posée par T3 seule.
