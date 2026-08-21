# T1 — CLIENT · carte du périmètre

Mesuré sur le code réel le 2026-08-19. **Reconnaissance en lecture seule, aucun fichier de code
modifié, aucune suite lancée.** Chaque affirmation porte son `fichier:ligne`.

**État : PARTIEL.** La passe web (routes, Livewire, Blade, gardes) est faite. Le natif
(`mobile/client/`, 52 écrans) et la surface API cliente ont été délégués et **ne sont pas revenus** —
voir « NON COUVERT » en fin de document. Ne pas lire l'absence d'une alerte dans ces deux sections
comme une absence de défaut.

---

## 1. Comment on entre dans le périmètre client (web)

### 1.1 La pile de gardes, une fois pour toutes

```
routes/web.php:29   Route::middleware(['auth','verified','active.account','phone.verified'])
  └── routes/client.php:48        + ['role:client']        prefix dashboard/client      name client.
  └── routes/company-dashboards.php:39  + ['auth','verified','active.account','org.type:client']
                                          prefix dashboard/entreprise-client   name client-company.
  └── routes/missing-route-fixes-advanced.php:78/314  + ['role:client']  (repli)
```

- `role` → `App\Http\Middleware\CheckRole` (`app/Http/Kernel.php:123`). Il accepte le rôle si
  `matchesRole()` ou si `platform_role === $role` (`app/Http/Middleware/CheckRole.php:19-27`).
- `matchesRole('client')` délègue à `isClient()` (`app/Models/Concerns/HasUserTypeChecks.php:216`).
  Les deux rôles canoniques du périmètre s'y ajoutent séparément :
  `client_individuelle` (`:229`) et `client_societe` (`:230`).
- **`enforce_2fa` n'est posé sur AUCUN groupe client**, ni `routes/client.php:48`, ni
  `routes/company-dashboards.php:39`. L'admin l'a (`routes/missing-route-fixes-advanced.php:91`).
  Décision produit à prendre par T4 — pas un défaut tant qu'elle n'est pas prise.

### 1.2 Le mécanisme de joignabilité réel : le répertoire des modules

Ce dépôt ne navigue pas par une barre latérale exhaustive. La navigation client tient en
**trois liens** dans `resources/views/navigation-menu.blade.php` (`:143` nouveau RDV, `:276` et
`:409` profil). Tout le reste passe par `ModulesDirectory` :

- `routes/client.php:57` → `/dashboard/client/modules`, contexte figé par la route (`defaults('contexte','client')`)
- `routes/company-dashboards.php:48` → même chose en contexte `client-company`
- Le catalogue est `config/modules.php` (495 l.) : contexte `client` aux lignes 76-158,
  contexte `client-company` aux lignes 349-374.

**Conséquence pour T4 : une route client sans tuile dans `config/modules.php` ET sans lien Blade est
injoignable, même si elle répond 200.** C'est le test à appliquer, pas `Route::has()`.

### 1.3 Carte route → composant → vue, par domaine

`routes/client.php` déclare 46 routes. Les colonnes « tuile » et « lien » disent comment un client
réel y arrive.

| Domaine | Route (`client.`) | Ligne | Composant | Tuile `config/modules.php` | Lien Blade |
|---|---|---|---|---|---|
| Accueil | `dashboard` | 53 | `ClientDashboard` | :76 | 36 réf. |
| Accueil | `modules` | 57 | `Shared\ModulesDirectory` | — | (la porte elle-même) |
| Réservation | `rendezvous.index` | 62 | `Client\MesRendezVousClient` | :79 | 15 réf. |
| Réservation | `rendezvous.series` | 64 | `Client\EditRecurringBooking` | — | `rendezvous/appointment-card.blade.php:27` |
| Réservation | `rendezvous.create` | 80 | `OrderEngine\OrderJourney` | :78 | 15 réf. |
| Réservation | `historique` | 131 | `Client\HistoriqueClient` | :77 | `NotificationPresenter.php:167` |
| Réservation | `calendar.interactive` | 262 | `Client\Calendar\ClientCalendarFC` | :148 | **aucun** |
| Réservation | `recurring.templates` | 268 | `Client\Templates\RecurringTemplatesGallery` | :149 | auto-lien seul (`recurring-templates-gallery.blade.php:10`) |
| Réservation | `bundles.manage` | 239 | `Client\MultiTradesBundleManager` | :130 | — |
| Moteur de commande | `rendezvous.create` | 80 | `OrderEngine\OrderJourney` (2232 l.) | :78 | — |
| Moteur de commande | *(public)* `order.confirmation` | — | `OrderEngine\OrderConfirmation` | :157 (`booking.create`) | — |
| Moteur de commande | *(public)* `order.asap.search` | — | `OrderEngine\AsapSearch` | — | redirection depuis `OrderConfirmation.php:225` |
| Paiement | `booking.checkout` | 215 | `Client\BookingCheckout` | — | `order-engine/order-confirmation.blade.php:223` |
| Paiement | `payment.methods` | 220 | `Client\SavedPaymentMethods` | :105 | — |
| Paiement | `wallet` | 111 | `Client\WalletClient` | :118 | — |
| Paiement | `finance` | 119 | `Client\FinanceDocumentsClient` | :81 | `dashboard/header.blade.php:23` |
| Paiement | `finance.quote.download` / `finance.invoice.download` | 247 / 251 | contrôleur, `middleware('signed')` | — | — |
| Paiement | `budget` | 196 | `Client\HomeBudget` | :98 | — |
| Paiement | `subscriptions` / `subscriptions-v2` | 135 / 140 | `Client\ClientSubscriptions(V2)` | :106 / :107 | `dashboard/header.blade.php:31` |
| Paiement | `quotes` | 177 | `Client\ReceivedQuotes` | :89 | — |
| Paiement | `tip.booking` | 210 | `Client\ClientTipBooking` | — | `historique-client.blade.php:159` |
| Fidélité | `loyalty` / `loyalty.rewards` | 91 / 165 | `Client\LoyaltyDashboard`, `ClientLoyaltyRewards` | :137 / :138 | 2 réf. |
| Fidélité | `referrals` | 107 | `Client\ReferralProgramPage` | :140 | — |
| Avis | `feedback.create` | 99 | `Client\ClientFeedbackForm` | — | `historique-client.blade.php:142`, `MissionCompletedNotification.php:39` |
| Avis | `nps.survey` | 235 | `Client\NpsSurvey` | :139 | `Nps/NpsSurveyNotification.php:66` |
| Suivi | `missions.tracking` | 95 | `Client\MissionLiveTracking` | — | `rendezvous/actions.blade.php:11` |
| Suivi | `booking.tracking.map` | 230 | `Client\ClientLiveTrackingMap` | — | `rendezvous/mission-tracking-panel.blade.php:34` |
| Litiges | `claims` | 115 | `Client\LitigesClient` | :134 | 3 réf. |
| Litiges | `protection` | 206 | `Client\MyProtection` | :104 | — |
| Favoris | `favorite-employes` | 127 | `Client\FavoriteEmployesManager` | :133 | — |
| Prestataires | `providers.browse` | 83 | `Client\BrowseProviders` | :131 (primary) | — |
| Prestataires | `ai.quote.photo` | 243 | `Client\AiQuotePhoto` | :129 | — |
| Communication | `chat.inbox` | 145 | `Client\ClientChatInbox` | :132 | — |
| Communication | `conversations.show` | 103 | `Conversations\ConversationPage` | — | `NewConversationMessageNotification.php:38` |
| Compte | `profile` / `profile.edit` | 123 / 225 | `Client\ProfilClient`, `ProfileEdit` | :127 / :128 | `navigation-menu.blade.php:276,409` |
| Compte | `places` | 187 | `Client\PlacesBook` | :95 | — |
| Conformité | `gdpr.data` | 87 | `Client\GdprDataPage` | :135 | 3 réf. |
| Conformité | `kyb.onboarding` | 155 | `Client\ClientKybOnboarding` | :136 | — |
| Documents | `contracts` | 160 | `Client\ClientContractSign` | :80 | — |
| Plateforme | `api-tokens` | 150 | `Client\ClientApiTokens` | :141 | — |
| Données | `analytics.dashboard` | 257 | **`ClientCompany\Analytics\ClientAnalyticsDashboard`** | :150 | `dashboard/header.blade.php:38` — **voir R3** |
| Données | `exports.bookings.xlsx` | 273 | `ClientExcelExportController` | — | `historique-client.blade.php:21` |
| Données | `analytics.export.{kpis,monthly_revenue,bookings}` | 278-282 | `AnalyticsExportController` | — | `client-company/analytics/…blade.php:64,70,75` — **voir R5** |

### 1.4 Société cliente — `routes/company-dashboards.php:39-88`

| Route (`client-company.`) | Ligne | Composant | Permission exigée dans le composant |
|---|---|---|---|
| `dashboard` | 44 | `ClientCompanyDashboard` | `isClientCompany()` (`ClientCompanyDashboard.php:31`) |
| `modules` | 48 | `Shared\ModulesDirectory` | garde du groupe |
| `sites` | 52 | `SiteManager` | `sites.view_all` (`SiteManager.php:68-69`), `sites.create` (:124), `sites.edit` (:137) |
| `bookings.index` / `bookings.create` | 53 / 54 | `BookingHub` | `bookings.create` (`BookingHub.php:88-89`), `bookings.approve` (:441, :460) |
| `bookings.multi-site` | 55 | `MultiSiteRequest` | `bookings.create` (`MultiSiteRequest.php:55-56`) |
| `bookings.bulk-import` | 85 | `BulkBookingImporter` | `bookings.create` (`BulkBookingImporter.php:40-41`) |
| `members` | 56 | `MembersAccess` | `members.invite` (`MembersAccess.php:110-111`) |
| `contracts` | 57 | `ClientContractsCenter` | **aucune** — trait seul |
| `contracts.signing-appointments` | 58 | `SigningAppointments` | `isClientCompany()` (`SigningAppointments.php:34`) |
| `billing` | 59 | `BillingCenter` | `finance.view` (`BillingCenter.php:53-54`) |
| `governance` | 70 | `GovernanceCenter` | `finance.view` ‖ `bookings.approve` (`GovernanceCenter.php:64-66`) |
| `analytics` | 73 | `Analytics\ClientAnalyticsDashboard` | `isClientCompany()` (:46) |
| `disputes` | 77 | `DisputesCenter` | **aucune** — trait seul |
| `missions.photos` | 81 | `SiteMissionPhotos` | `abort_unless` (`SiteMissionPhotos.php:26`) |

**Le socle multi-tenant est correct.** `EnforcesActiveOrgMembership`
(`app/Support/Livewire/Concerns/EnforcesActiveOrgMembership.php:21`) est écrit
`bootEnforcesActiveOrgMembership()` — la forme `boot{Trait}`, **qui est bien appelée** par Livewire à
chaque requête, montage compris. Ce n'est PAS le piège `boot{NomDuComposant}` inerte du dépôt. Les
13 composants `ClientCompany/` l'utilisent tous.

---

## 2. Trous de joignabilité mesurés (web)

### 2.1 Composants Livewire sans route — 8 sur 43, dont **1 injoignable**

Les 43 composants de `app/Livewire/Client/` : 35 ont une route, 8 n'en ont pas. J'ai grepé les
appelants de chacun des 8, pas seulement leur existence.

| Composant | Monté par | Verdict |
|---|---|---|
| `MissionTracking` | `rendezvous/mission-tracking-panel.blade.php:42` | joignable |
| `GererMaMission` | `mission-tracking.blade.php:51` | joignable |
| `MissionClientActions` | `mission-tracking.blade.php:115` | joignable |
| `MissionQrCodes` | `mission-tracking.blade.php:143` | joignable |
| `MissionAftercareSummary` | `mission-tracking.blade.php:144` | joignable |
| `MissionFinalValidation` | `mission-tracking.blade.php:145` | joignable |
| `PremiumOfferPage` | `routes/public.php:15` | joignable (route publique) |
| **`BrowseCompanies`** | `booking/scheduling/provider-selection.blade.php:88` | **INJOIGNABLE — voir 2.2** |

Chaîne vérifiée pour les six premiers :
`rendezvous/list.blade.php:3` → `appointment-card.blade.php:55` → `mission-tracking-panel.blade.php:42`
→ `MissionTracking` → `mission-tracking.blade.php`. Racine = `MesRendezVousClient`
(`app/Livewire/Client/MesRendezVousClient.php:343`), route `client.rendezvous.index`.

### 2.2 T1-J1 — 39 fichiers Blade morts : l'ancien tunnel de réservation client

`resources/views/livewire/client/booking/` contient un tunnel complet en 5 étapes — 39 fichiers,
`layout.blade.php`, `content-card.blade.php`, `stepper.blade.php`, `step-1-service` … `step-5-confirmation`,
et les sous-dossiers `service/`, `details/`, `coordinates/`, `scheduling/`, `confirmation/`.

**Sa racine n'est rendue par personne.** `livewire.client.booking.layout` n'a qu'une seule
occurrence dans tout le dépôt : sa propre ligne 3, qui inclut `content-card`. Aucun `return view()`
d'aucun composant PHP ne le vise — vérifié sur les 43 `return view('livewire.client…')` de
`app/Livewire/Client/`.

C'est l'ancien parcours remplacé par `OrderJourney`, ce que le commentaire de
`routes/client.php:67-79` décrit explicitement (« Un client connecté réservait par un formulaire, un
visiteur par un autre »). La bascule a été faite ; l'arbre Blade est resté.

**Ce que ça casse :** `BrowseCompanies` (`app/Livewire/Client/BrowseCompanies.php`, 1 composant
complet avec filtres, tri par note, mode sélection) n'a **qu'un seul point de montage au monde** —
`booking/scheduling/provider-selection.blade.php:88` — situé dans cet arbre mort. Un client ne peut
donc **par aucun chemin** parcourir les sociétés prestataires. Et
`tests/Feature/Relations/BrowseCompaniesFilterTest.php` teste le composant en direct : il est vert
en mesurant un module que personne ne peut atteindre. C'est la famille de défauts dominante du
dépôt, dans sa forme exacte.

### 2.3 T1-J2 — « Mes statistiques » : une tuile qui garantit un 403

- `routes/client.php:257` monte sur `client.analytics.dashboard` le composant
  **`App\Livewire\ClientCompany\Analytics\ClientAnalyticsDashboard`** (import `routes/client.php:41`).
- Ce composant refuse tout ce qui n'est pas une société :
  `abort_unless(Auth::user()?->isClientCompany(), 403)` — `ClientAnalyticsDashboard.php:46`.
- Or la tuile `config/modules.php:150` est déclarée `'context' => 'client'` **sans `visible_si`** —
  contrairement à la tuile « Espace entreprise » qui, elle, porte
  `'visible_si' => 'belongsToClientCompany'` (`config/modules.php:126`).
- Et le lien est posé en dur dans l'en-tête du tableau de bord de **tous** les clients :
  `resources/views/livewire/client/dashboard/header.blade.php:38`, gardé par `Route::has()` seul.

**Ce que ça casse :** tout client particulier voit « Analytics » sur son accueil et « Mes
statistiques » dans ses modules, et reçoit 403 en cliquant. `Route::has()` prouve que la porte
existe, pas qu'on a la clé — le piège vérifié du dépôt, ici en exemplaire.

### 2.4 T1-J3 — deux routes pour une série récurrente, dont une est un bouchon

- Le vrai écran : `client.rendezvous.series` → `Client\EditRecurringBooking` (`routes/client.php:64`),
  lié depuis `rendezvous/appointment-card.blade.php:27`.
- Le bouchon : `client.rendezvous.series.edit`
  (`routes/missing-route-fixes-advanced.php:319-326`) qui renvoie une **chaîne HTML brute** :
  `'<h1>Gérer ma série récurrente</h1><p>Série ID : …</p>'`.
- Et c'est le bouchon qui est lié depuis `rendezvous/actions.blade.php:30`.

Deux notions, un événement — même objet, deux adresses, et le client qui clique sur le bouton
« actions » tombe sur une page sans mise en page, sans navigation, sans thème.

### 2.5 T1-J4 — routes atteignables par la seule page Modules

`client.calendar.interactive` (`routes/client.php:262`) n'a **aucun lien Blade** dans tout le dépôt ;
`client.recurring.templates` (`:268`) n'a que son propre auto-lien
(`templates/recurring-templates-gallery.blade.php:10`). Les deux ne vivent que par leur tuile
(`config/modules.php:148,149`). Non bloquant, mais à trancher : découvrabilité voulue ou oubli.

---

## 3. Gardes et sécurité — ce que la passe web a donné

Chaque point ci-dessous a été lu dans le code, pas déduit. **Aucun n'a été exploité ni testé** : la
mission était en lecture seule.

### R1 — `ClientChatInbox` : lire n'importe quelle conversation de la plateforme

`app/Livewire/Client/ClientChatInbox.php`

- `:17` `public ?int $activeThreadId = null;` — **pas de `#[Locked]`**.
- `:44-55` `selectThread(int $threadId)` vérifie correctement l'appartenance via `ChatParticipant`
  (`where user_id = Auth::id()`, `whereNull('left_at')`). **C'est la porte prévue, et elle est bonne.**
- `:126-138` `activeMessages()` lit `ChatMessage::where('thread_id', $this->activeThreadId)`
  `->limit(200)` — **sans aucun contrôle d'appartenance**.
- `:141-144` `activeThread()` fait `ChatThread::find($this->activeThreadId)`, idem.

Le navigateur pose directement `$wire.set('activeThreadId', N)` : `selectThread()` n'est jamais
appelée, sa vérification n'est jamais exécutée, et les deux propriétés calculées servent 200
messages du fil N. La garde existante est exactement ce qui rend le défaut invisible en relecture.

**Chemins d'écriture, eux, fermés** — vérifiés : `send()` (`:64`) passe par
`ChatService::sendMessage()` qui exige la participation vivante
(`app/Services/ChatV2/ChatService.php:114-121`) ; le canal temps réel est autorisé côté serveur
(`routes/channels.php:241-253`). Le trou est **en lecture seule** — ce qui, pour une messagerie
client↔prestataire, reste une fuite de confidentialité complète.

### R2 — `ClientCalendarFC` : l'adresse du domicile d'un autre client

`app/Livewire/Client/Calendar/ClientCalendarFC.php`

- `:29` `public ?int $selectedBookingId = null;` — pas de `#[Locked]`.
- `:109-112` `selectEvent(int $bookingId)` affecte la propriété **sans vérifier quoi que ce soit**.
- `:138-145` `getSelectedBookingProperty()` fait `Booking::with([...])->find($this->selectedBookingId)`
  — **aucun `where client_id`**.

Et le panneau de détail affiche :
`resources/views/livewire/client/calendar/client-calendar-fc.blade.php:115-121` →
`$this->selectedBooking->address`, `->postal_code`, `->city`, plus `booking_reference` (`:76`),
`organizationSite->name` (`:104`), `status` (`:111`).

**Ce que ça casse pour un client réel :** une adresse de domicile, un code postal et une ville
appartenant à un autre client s'affichent en énumérant les identifiants de réservation. Sur une
plateforme où des professionnels se rendent chez des particuliers, c'est la donnée la plus sensible
du produit.

**Le chemin d'écriture est fermé** : `handleEventDrop()` (`:72`) passe par
`BookingRescheduleService::reschedule()` qui appelle `authorize()`
(`app/Services/Client/Calendar/BookingRescheduleService.php:44`), lequel exige propriétaire OU
membre de l'organisation cliente (`:74-82`). Reprogrammer la réservation d'autrui est impossible ;
la lire ne l'est pas.

### R3 — `InteractsWithRecurringSeries` : la série d'un autre, en lecture

`app/Support/Livewire/Concerns/InteractsWithRecurringSeries.php`

- `:14` `public int $rendezVousId;` — pas de `#[Locked]`.
- `:28` `mountRecurringSeries()` fait bien `Gate::authorize('view', $rendezVous)` — **au montage seulement**.
- `:39-44` `currentRendezVous()` re-résout `Booking::findOrFail($this->rendezVousId)` avec
  `->with([… 'client'])`, **sans re-autoriser**. `seriesOccurrences()` (`:47-56`) enchaîne sur
  `recurring_series_id`.

Les écritures re-autorisent toutes (`saveChanges` `:83`, `pauseSeries` `:104`, `resumeSeries` `:112`,
`cancelSeries` `:120`) — la garde est donc réelle pour les mutations, absente pour l'affichage.

**Frontière T3** : le même trait est utilisé par `app/Livewire/Admin/EditRecurringBooking.php`.
Poser `#[Locked]` sur `:14` corrige les deux d'un coup, mais touche le périmètre admin → arbitrage T4.

### R4 — Analytics : deux colonnes pour « l'organisation active »

- L'écran lit `current_organization_id` :
  `app/Livewire/ClientCompany/Analytics/ClientAnalyticsDashboard.php:71`.
- Les trois exports lisent `organization_account_id` :
  `app/Http/Controllers/Analytics/AnalyticsExportController.php:33`, `:46`, `:56`.
- Les boutons d'export sont **sur cet écran-là** :
  `resources/views/livewire/client-company/analytics/client-analytics-dashboard.blade.php:64,70,75`.

Deux notions, un événement, dans sa forme documentée (« Organisation active : deux colonnes »). Le
CSV téléchargé peut ne pas décrire l'organisation affichée juste au-dessus.

**Aggravant, à confirmer par T4 :** les signatures acceptent `?int $organizationAccountId` et le
docblock du contrôleur annonce « ou null = global »
(`app/Http/Controllers/Analytics/AnalyticsExportController.php:19`). Dans
`app/Services/Analytics/AnalyticsKpiService.php`, seuls `activeSiteStats` (`:62-64`) et `topSites`
(`:155`) court-circuitent le cas nul ; `mainKpis` (`:53-56`), `monthlyRevenue` (`:120`),
`statusBreakdown` (`:133`) et `alerts` (`:200`) passent le `null` tel quel à l'agrégateur.
**Je n'ai pas lu l'agrégateur** — si `null` y vaut « pas de filtre », un client sans
`organization_account_id` télécharge le chiffre d'affaires de toute la plateforme. À vérifier avant
tout autre travail sur ce module.

### R5 — Un bouton d'export gardé par le mauvais rôle

Les routes `client.analytics.export.*` sont déclarées **dans le groupe `role:client`**
(`routes/client.php:48`, routes `:277-284`, préfixe `dashboard/client`), mais leurs seuls liens
vivent sur l'écran **société** dont le groupe est `org.type:client` sans `role:client`
(`routes/company-dashboards.php:39`). Un membre d'organisation cliente dont le rôle canonique n'est
pas résolu à `client` par `HasUserTypeChecks::matchesRole` (`:216`) reçoit 403 sur un bouton affiché
dans son propre tableau de bord.

### R6 — Aucune permission sur l'export financier

`BillingCenter` exige `finance.view` (`app/Livewire/ClientCompany/BillingCenter.php:53-54`) et
`GovernanceCenter` exige `finance.view ‖ bookings.approve`
(`app/Livewire/ClientCompany/GovernanceCenter.php:64-66`). `AnalyticsExportController` n'exige
**rien** : ni permission d'organisation, ni appartenance vérifiée au-delà du middleware. Un
sous-rôle `viewer` télécharge le chiffre d'affaires complet en CSV.

### R7 — Deux centres société sans garde de permission

`ClientContractsCenter` (`routes/company-dashboards.php:57`) et `DisputesCenter` (`:77`) ne portent
que `EnforcesActiveOrgMembership` — aucun `PermissionService::can()`, là où les onze autres
composants `ClientCompany/` en ont un. Contrats et litiges de la société sont donc lisibles par
`viewer` et `requester`. À confirmer avec la matrice de `app/Enums/OrganizationRole.php` (non lue).

### R8 — Vérifié propre, ne pas re-litiger

`BookingCheckout` porte `#[Url] public ?int $bookingId` **sans `#[Locked]`**
(`app/Livewire/Client/BookingCheckout.php:28-29`) — et c'est sans conséquence : les trois chemins
re-vérifient la propriété. `startPayment()` `:51-52`, `confirmAuthorization()` `:89-90`,
`render()` `:111` (`where('client_id', $user->id)`). De même `AsapSearch` : `public int $requestId`
(`app/Livewire/OrderEngine/AsapSearch.php:41`) est non verrouillée, mais la propriété calculée
`request()` re-vérifie le propriétaire à **chaque** lecture (`:78-94`, `persist: false`) — le
commentaire `:45-50` documente pourquoi. `OrderConfirmation::draft()` filtre bien sur
`client_id` (`app/Livewire/OrderEngine/OrderConfirmation.php:70`).

**La leçon de contraste :** la différence entre R1/R2/R3 et R8 n'est pas le `#[Locked]`, c'est
**qui re-vérifie à la lecture**. `#[Locked]` est le correctif le moins cher ; la re-vérification dans
la propriété calculée est le correctif juste.

---

## 4. Frontières partagées — arbitrage T4 obligatoire

| Zone | Fichier(s) | Avec | Pourquoi c'est piégé |
|---|---|---|---|
| Série récurrente | `app/Support/Livewire/Concerns/InteractsWithRecurringSeries.php` | **T3** | Un seul trait, deux composants (`Client\EditRecurringBooking`, `Admin\EditRecurringBooking`). Corriger R3 touche l'admin. |
| Fichier de routes à deux publics | `routes/company-dashboards.php` | **T2** | Client `:39-88`, prestataire `:95-156`. Une modification de groupe touche les deux. |
| Garde multi-tenant | `app/Support/Livewire/Concerns/EnforcesActiveOrgMembership.php` | **T2** | Utilisée par `ClientCompany/` (13) et `ProviderCompany/` (15). |
| Colonne d'organisation active | `current_organization_id` vs `organization_account_id` | **T2 + T3** | R4. Le choix de la source unique dépasse le client. |
| Analytics | `app/Services/Analytics/AnalyticsKpiService.php`, `AnalyticsExporter.php`, `AnalyticsExportController.php` | **T2 + T3** | Un service, plusieurs publics ; la question du `null = global` est transverse. |
| Chat | `app/Services/ChatV2/ChatService.php`, `routes/channels.php:241` | **T2** | Un émetteur, un destinataire, deux écrans. R1 est côté client ; le pendant prestataire est à vérifier par T2. |
| Reprogrammation | `app/Services/Client/Calendar/BookingRescheduleService.php` | **T3** | `:69-71` accorde un contournement `isPlatformAdmin()`. |
| Réservation / mission | `Booking`, `booking_id` | **T2** | `MissionTracking`, `GererMaMission`, `MissionFinalValidation` côté client décrivent le même objet que les écrans mission de T2. |
| Répertoire des modules | `config/modules.php` (`:76-158` client, `:349-374` société cliente) | **T3 → T1 + T2** | T3 ouvre et ferme ; T1 subit. J2 naît là. |
| Rôles d'organisation | `app/Enums/OrganizationRole.php`, `PermissionService` | **T2** | `owner`/`finance`/`viewer` des deux côtés, sens dépendant du type d'organisation. |
| Code partagé natif | `mobile/shared/` | **T2 (+T3)** | Non mesuré — voir NON COUVERT. |

---

## 5. NON COUVERT — à reprendre

Nommé, avec son motif. Réduire le périmètre n'est pas une décision de T1.

1. **`mobile/client/` — 52 écrans natifs.** Reconnaissance déléguée (inventaire, navigateurs,
   écrans orphelins, cibles `navigate()` mortes, onglets). **Le résultat n'est pas revenu.** Rien
   n'est mesuré ici : ni la joignabilité des écrans, ni les onglets. Rappel du piège : ni `tsc` ni
   Jest ne détectent un écran orphelin.
2. **Surface API cliente et ses gardes.** Reconnaissance déléguée sur `routes/api.php` et
   `routes/api/*` (groupes de middleware, table des endpoints, absence de `active.account` /
   `verified` / `phone.verified` côté API, garde de rôle, IDOR de contrôleurs). **Résultat non
   revenu.** Le trou d'authentification « garde web sans équivalent API » est un défaut connu et
   récurrent de ce dépôt : **il faut présumer qu'il existe tant qu'on n'a pas mesuré**.
3. **Parité web ↔ natif.** Impossible à établir : elle exige les points 1 et 2.
4. **`OrderEngine\OrderJourney` — 2232 lignes.** Le composant le plus gros du périmètre, cœur du
   parcours de commande et du prix. Non audité : ni ses propriétés publiques, ni ses gardes, ni ses
   méthodes publiques sans appelant Blade.
5. **Méthodes publiques sans appelant.** Le balayage « méthode publique qu'aucune Blade n'appelle » a
   été fait sur `ClientChatInbox`, `ClientCalendarFC`, `BookingCheckout`, `AsapSearch`,
   `OrderConfirmation`, `InteractsWithRecurringSeries` uniquement. Les 37 autres composants
   `Client/` n'ont pas été passés au crible — c'est là qu'a été trouvé le piège
   « sélecteur d'heures absent des vues ».
6. **Composants non ouverts individuellement :** `ProfilClient`, `WalletClient`, `LitigesClient`,
   `PlacesBook`, `HomeBudget`, `MyProtection`, `ReceivedQuotes`, `MultiTradesBundleManager`,
   `AiQuotePhoto`, `ClientKybOnboarding`, `ClientContractSign`, `SavedPaymentMethods`,
   `GdprDataPage`, `ClientApiTokens`, `FavoriteEmployesManager`, `NpsSurvey`, `ClientTipBooking`,
   `MissionLiveTracking`, `ClientLiveTrackingMap`.
7. **`app/Enums/OrganizationRole.php` non lu.** R7 reste donc une présomption étayée par l'absence
   d'appel à `PermissionService`, pas par la matrice des droits.
8. **Agrégateur analytics non lu** — la partie aggravante de R4 est ouverte.
9. **Trois notions de suivi non départagées** : `MissionTracking` (imbriqué),
   `MissionLiveTracking` (`client.missions.tracking`, clé `{mission}`) et `ClientLiveTrackingMap`
   (`client.booking.tracking.map`, clé `{bookingId}`). Deux clés différentes pour un même
   déplacement — candidat « deux notions, un événement » **non instruit**.

---

## 6. Les risques classés — 8 sur les 8 demandés, mais 6 mesurés

Classement par ce que ça casse pour un client réel. **Aucun rang ne provient du natif ni de l'API :
ces deux surfaces ne sont pas mesurées, et deux places sont donc tenues par des inconnues.**

| # | Risque | Preuve | Ce que ça casse |
|---|---|---|---|
| 1 | Adresse de domicile d'autrui lisible par énumération | `ClientCalendarFC.php:29,109-112,138-145` + `client-calendar-fc.blade.php:115-121` | Adresse, code postal, ville d'un autre client. La donnée la plus sensible du produit, sur une plateforme d'intervention à domicile. |
| 2 | Toute conversation de la plateforme lisible | `ClientChatInbox.php:17,126-138,141-144` | 200 messages de n'importe quel fil, client↔prestataire compris. La garde de `:44-55` fait croire à une protection. |
| 3 | Export financier sans permission, et peut-être sans périmètre | `AnalyticsExportController.php:19,33,46,56` ; `AnalyticsKpiService.php:53-56,120,133,200` | Un `viewer` télécharge le CA complet ; si `null` vaut « global » côté agrégateur, c'est le CA de la plateforme. **Rang provisoire : passe en 1 si l'agrégateur ne filtre pas.** |
| 4 | Le comparateur de sociétés n'existe pour aucun client | `BrowseCompanies.php` + seul montage `booking/scheduling/provider-selection.blade.php:88`, dans l'arbre mort de `client/booking/` (39 fichiers) | Choisir sa société prestataire est impossible. Un test vert (`BrowseCompaniesFilterTest.php`) mesure un module injoignable. |
| 5 | Série récurrente d'autrui lisible | `InteractsWithRecurringSeries.php:14,39-44,47-56` | Dates, heures, prestataire assigné, client — la série entière. Écritures fermées. **Frontière T3.** |
| 6 | « Mes statistiques » garantit un 403 | `routes/client.php:257` + `ClientAnalyticsDashboard.php:46` + `config/modules.php:150` + `dashboard/header.blade.php:38` | Tout client particulier voit deux entrées qui refusent l'accès. Confiance dans le produit. |
| 7 | Le bouton « gérer ma série » ouvre un bouchon HTML | `rendezvous/actions.blade.php:30` → `missing-route-fixes-advanced.php:319-326` | Page sans navigation ni thème, pendant que le vrai écran existe sur `client.rendezvous.series`. |
| 8 | **INCONNU — gardes API et joignabilité native** | non mesuré (voir NON COUVERT 1 et 2) | Réservé. Le défaut « garde web absente de l'API » a déjà produit 4 failles d'authentification sur 6 dans un audit antérieur de ce dépôt : cette place lui est gardée jusqu'à mesure. |
