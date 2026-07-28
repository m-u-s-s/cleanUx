# Audit critique CleanUx / brio — 26 juillet 2026

> Audit adverse (« cherche tout ce qui est cassé, incohérent ou inutile »), conduit sur
> l'arbre de travail à `bcb28771` + 2 fichiers modifiés non commités.
> Chaque constat ci-dessous a été **vérifié dans le code** (fichier:ligne). Les hypothèses
> non confirmées sont explicitement marquées « À CONFIRMER ».

---

## 0. Note globale : **11,5 / 20**

| Dimension | Note | Commentaire |
|---|---|---|
| Couverture fonctionnelle | 17/20 | Ampleur impressionnante : 60+ modules, 268 tables, 624 routes |
| Discipline de code | 14/20 | Aucune god class (max 776 L), autorisation Livewire propre, CI complète |
| **Exactitude runtime** | **7/20** | **7 crashs confirmés en production, dont 2 sur le cœur produit** |
| **Cohérence architecturale** | **7/20** | **9 doublons legacy/V2 vivants en parallèle, 3 géocodeurs, 2 moteurs de prix** |
| **Fiabilité du filet de sécurité** | **6/20** | **15 652 erreurs PHPStan neutralisées ; 1 test grave un bug comme spec** |
| Hygiène / code mort | 10/20 | ~5 900 lignes + 23 tables supprimables identifiées |
| Sécurité applicative | 15/20 | Secrets propres, IDOR bien fermés ; mais 15 `env()` cassés en prod |

**Le problème n'est pas la quantité de code écrit — c'est que rien ne vérifie plus qu'il
fonctionne.** La suite est verte (4223 tests), la CI est bien conçue, et pourtant le
dashboard prestataire-société plante, les QR de mission plantent, et l'export Excel client
plante. Les trois sont invisibles pour les tests **et** pour PHPStan, par construction.

---

## 1. Méthode

Outils réellement exécutés (pas d'inférence) :

| Vérification | Résultat |
|---|---|
| `php artisan test` (suite complète) | **4223 passés, 9 skipped, 0 échec** — 1261 s |
| `vendor/bin/phpstan analyse` (config projet) | **173 erreurs → la porte qualité CI est ROUGE** |
| PHPStan **niveau 0 sans baseline** | 51 erreurs, dont 7 crashs runtime |
| Détecteur de classes orphelines (maison) | 57 classes non référencées |
| Détecteur de vues Blade mortes (maison) | 43 vues sans référence |
| Détecteur de tables mortes (maison) | 23 tables sans code |
| Détecteur d'incohérences docblock↔relation | **44 docblocks faux / 623** |
| Scan autorisation Livewire (189 composants) | 5 candidats, **0 faille réelle après revue** |
| `composer audit` / `npm audit` | 0 vulnérabilité |
| Secrets : `.env` tracké ? historique git ? | **Propre** — rien à signaler |

---

## 2. BLOQUANTS — crashs confirmés en production

### B1. Dashboard prestataire-société → HTTP 500 dès qu'une mission est planifiée aujourd'hui
`app/Livewire/ProviderCompany/ProviderDashboard.php:85`

```php
return Mission::where('provider_organization_id', …)
    ->whereDate('planned_start_at', today())
    ->with(['assignedWorker:id,name,profile_photo_path'])   // ← relation inexistante
```

`Mission::assignedWorker()` **n'existe pas** (`app/Models/Mission.php` : ni la méthode, ni
un alias). Laravel lève `RelationNotFoundException` — mais uniquement si `get()` ramène ≥ 1
ligne (`Builder::get()` n'eager-load que si `count($models) > 0`). La vue aggrave :
`resources/views/livewire/provider-company/provider-dashboard.blade.php:119-122` lit
`$mission->assignedWorker->profile_photo_url`.

**Conséquence : la page d'accueil d'une société prestataire fonctionne quand elle n'a rien à
faire, et plante exactement les jours où elle travaille.** État vide = OK → invisible en test.
Aucun test ne couvre ce composant (`grep ProviderDashboard tests/` → 0 fichier dédié).

### B2. QR de mission (cœur du flow produit) → HTTP 500
`app/Livewire/Client/MissionQrCodes.php:10,49,57`

```php
use SimpleSoftwareIO\QrCode\Facades\QrCode;   // package NON INSTALLÉ
```

`composer.lock` : 0 occurrence de `simplesoftwareio` ; `vendor/simplesoftwareio/` absent.
Le composant est **monté inconditionnellement** depuis
`resources/views/livewire/client/mission-tracking.blade.php:72`. Dès qu'un code de
vérification `start` ou `end` non consommé existe — c'est-à-dire le cas normal — `QrCode::format()`
lève `Class not found`.

C'est le mécanisme **QR start / QR end** décrit comme signature du produit. `bacon/bacon-qr-code`
est bien présent (dépendance Fortify/2FA) mais ce n'est pas la même API. **Seul endroit du
codebase qui génère un QR — donc la génération de QR ne marche nulle part.**

### B3. Export Excel client → HTTP 500
`app/Services/Client/Exports/ClientBookingExcelExporter.php:38,59,80,157,…`

`PhpOffice\PhpSpreadsheet` **n'est pas dans `composer.json` ni dans le lock**.
Route exposée : `routes/client.php:210` → `client.exports.bookings.xlsx` →
`ClientExcelExportController::bookings()` → `new Spreadsheet()` → fatal.
**Zéro test** (`grep exports.bookings.xlsx tests/` → 0).
11 entrées de la baseline PHPStan couvrent précisément ce fichier.

### B4. Modal « suggérer un employé » du dashboard admin → TypeError
`app/Support/Livewire/Concerns/HandlesAdminDashboardPlanning.php:198,201`

```php
$employeeQuery->where(function (Builder $query) use ($rdv) {          // ← Builder non importé
    …->orWhereHas('zoneAssignments', function (Builder $assignmentQuery) …
```

Le trait n'importe **pas** `Illuminate\Database\Eloquent\Builder` (imports lignes 5-8 :
Booking, LimiteJournaliere, MissionReplanifieeNotification, Carbon). `Builder` résout donc
vers `App\Support\Livewire\Concerns\Builder`, inexistant. PHP vérifie les types de paramètres
**à l'exécution** → `TypeError` dès que `$rdv->service_zone_id` est non nul (le cas normal).

### B5. Team Lead Operations Center → RelationNotFoundException
`app/Livewire/Employe/TeamLeadOperationsCenter.php:79-80`

```php
->whereHas('missionBatchDay', …)          // MissionTaskSegment n'a pas missionBatchDay
->with(['assignments.user', 'memberStatuses'])   // ni assignments
```

`app/Models/MissionTaskSegment.php` expose : `batch`, `day`, `mission`, `fieldTeam`,
`servicePartner`, `assignedUser`. **Aucune** des deux relations utilisées n'existe.
Déclenché dès qu'un batch est sélectionné. La table `mission_batch_days` figure d'ailleurs
dans les 23 tables mortes (§9).

### B6. API flotte prestataire + disponibilité véhicules → RelationNotFoundException
`app/Http/Controllers/Api/Provider/FleetProviderController.php:23` → `with(['vehicle.certifications', …])`
`app/Services/FleetV2/FleetService.php:224` → `->with('certifications')->get()`

`FleetVehicle` a `currentProvider`, `assignments`, `maintenanceLogs` — **pas `certifications`**,
alors que le modèle `FleetCertification` existe. Donc :
- `GET /api/provider/fleet/my-vehicles` → 500
- `FleetService::getAvailableVehicles()` → 500
- le **gating « certification expirée »** annoncé du module Fleet ne peut pas fonctionner.

### B7. Cron marketing nocturne → ArgumentCountError chaque nuit à 02h00
`app/Console/Kernel.php:75`

```php
$schedule->call(fn () => RecomputeSegmentJob::dispatch())   // 0 argument
```
`RecomputeSegmentJob::__construct()` exige 1 paramètre. Le job échoue à chaque tick →
**les segments marketing ne sont jamais recalculés.**

### B8. Documents d'onboarding prestataire inaccessibles à l'admin
Collision de nom de route :
- `routes/admin.php:521` → `/admin/onboarding-documents/{document}/file`, middleware `signed` ✔
- `routes/api/provider.php:175` → `/api/admin/onboarding-documents/{document}/file`, **même nom**

Vérifié en runtime : `route('admin.onboarding.document.file', ['document' => 1])` renvoie
**`/api/admin/onboarding-documents/1/file`**. Or `AdminOnboardingDocumentsCenter.php:192`
construit un `URL::temporarySignedRoute()` avec ce nom → l'URL pointe vers la route API,
qui (a) n'a **pas** le middleware `signed`, et (b) utilise le guard session `auth` alors
qu'elle vit dans le groupe `api` où `EnsureFrontendRequestsAreStateful` est **commenté**
(`app/Http/Kernel.php:91`) — donc pas de session. **L'admin ne peut pas consulter les pièces
KYC / assurance / identité des prestataires.**

---

## 3. Bugs silencieux — la fonctionnalité existe, ne fait rien, et ne le dit pas

Ce sont les plus dangereux : aucune erreur, aucun log, l'utilisateur croit que ça a marché.

### S1. 🔴 Sync devis/factures admin : retourne toujours 0
`app/Services/Finance/FinanceDocumentService.php:131`
`app/Services/Finance/Concerns/SynchronizesFinanceDocuments.php:108`

```php
foreach ($rendezVousRows as $rdv) {
    if (! $rdv instanceof RendezVous) { continue; }   // classe inexistante dans ce namespace
```

`RendezVous` n'est pas importé → résout vers `App\Services\Finance\RendezVous`, inexistant.
`instanceof` sur une classe inconnue renvoie **`false` sans erreur** → `! false` = `true` →
**chaque ligne est sautée**. Le bouton admin
(`app/Support/Livewire/Concerns/Admin/HandlesFinanceDocuments.php:209`) affiche
« 0 devis, 0 factures » en silence, indéfiniment.

**Et le test grave le bug comme spécification** —
`tests/Feature/Finance/SynchronizesFinanceDocumentsTraitTest.php:150-158` :

```php
// The trait guards on `instanceof RendezVous`, an undefined class in its
// namespace, so every Booking row is skipped and counts stay at zero.
$this->assertSame(['quotes' => 0, 'invoices' => 0], $result);
```

C'est le constat le plus grave de l'audit sur le plan **process** : le bug a été compris,
documenté, puis verrouillé par une assertion au lieu d'être corrigé. La suite verte protège
désormais le comportement cassé.
*(Note : `syncAllEligible()`, la version du cron horaire, n'a pas ce garde et fonctionne.)*

### S2. 🔴 Invitations d'organisation : jamais envoyées (× 2 espaces)
`app/Livewire/ClientCompany/MembersAccess.php:124-128`
```php
} else {
    // TODO: Envoyer l'email d'invitation avec lien
    // Mail::to($this->inviteEmail)->send(new OrganizationInvitation(...));
}
$this->inviteEmail = ''; …
$this->dispatch('member-invited');     // ← succès annoncé quand même
```
`app/Livewire/ProviderCompany/TeamManagement.php:143-145` — identique.

Si l'invité **n'a pas de compte** : aucune ligne créée, aucun email, aucun token — et
l'UI se réinitialise en émettant `member-invited`. Le gabarit d'email existe pourtant :
`resources/views/emails/team-invitation.blade.php` (23 L, **0 référence dans tout le code**).
Fonctionnalité à moitié construite, présentée comme terminée.

**Effet de bord sécurité/consentement** : quand l'invité **existe**, il est inséré directement
avec `status => 'active'`, `joined_at => now()` — **sans aucune acceptation**. N'importe quel
admin d'organisation peut rattacher un utilisateur tiers à son organisation en connaissant
son email. Il n'y a pas d'étape « accepter l'invitation ».

### S3. 🔴 Campagnes marketing : les emails ne partent jamais, même en production
`app/Services/Marketing/CampaignEngine.php:274-283`
```php
protected function sendEmail(User $user, string $subject, string $body): void
{
    // In production : Mail::to($user)->queue(new GenericMarketingMail($subject, $body));
    // For now, log-only in dev/tests so no SMTP needed.
    Log::info('Marketing email send', […]);
}
```
Aucun test d'environnement : **c'est log-only partout, y compris en prod.** Le canal SMS
juste en dessous (`sendSms`), lui, appelle vraiment `SmsService`. Le module marketing est
donc mono-canal sans que rien ne le signale.

### S4. 🔴 Push iOS/Android : routage par plateforme mort, retombe sur Mock
`app/Services/Push/PushService.php:44-45`
```php
$apnsConfigured = (bool) config('push.apns.key_path') || (bool) config('push.apns.key_id');
$fcmConfigured  = (bool) config('push.fcm.credentials_path') || (bool) config('push.fcm.project_id');
```
Les vrais chemins sont `push.providers.apns.*` et `push.providers.fcm.*`
(`config/push.php:8-27`). Vérifié : `config('push.fcm')` → **`null`**.
Les deux drapeaux sont donc **toujours faux** → aucun `ApnsPushProvider` / `FcmPushProvider`
n'est jamais sélectionné → retour systématique au binding par défaut
(`config/push.php:6` : `env('PUSH_PROVIDER', 'mock')`).

Conséquences : (a) `.env.example` livre `PUSH_PROVIDER=mock` → push muet en dev/staging ;
(b) `.env.production.example:162` livre `PUSH_PROVIDER=fcm` → **tous** les tokens, iOS
compris, partent par FCM, et `ApnsPushProvider` n'est jamais instancié.
Pour une marketplace où le prestataire reçoit ses offres par push, c'est critique.

### S5. 🟠 Dispatch ASAP : branche morte depuis toujours
`app/Services/Booking/CreateBookingAction.php:303`
```php
if (($rendezVous->booking_mode ?? null) === 'asap' && isset($mission) && $mission) {
    app(MissionDispatchService::class)->dispatchToNextProvider($mission);
}
```
`$mission` **n'est jamais défini dans cette portée** (confirmé PHPStan `variable.undefined`).
`isset($mission)` est donc toujours faux → **le dispatch ASAP depuis cette action ne
s'exécute jamais.** À relier au TODO connu « ASAP double-dispatch » : la réalité est
l'inverse du soupçon — ce chemin-là ne dispatche pas du tout.

### S6. 🟠 Canal de notification SMS : jamais enregistré
`app/Notifications/Channels/SmsChannel.php` (72 L) — **0 référence**.
`Notification::extend()` n'est appelé nulle part ; `PushServiceProvider::boot()` se contente
d'un commentaire (« handled by config/services or Notification::extend at runtime »).
Toute `Notification` déclarant `'sms'` dans `via()` lèverait `Driver [sms] not supported`.
Aucune ne le fait aujourd'hui → capacité documentée, inexistante.

### S7. 🟠 Fuite de fichiers temporaires dans le scan antivirus
`app/Jobs/Messaging/ScanAttachmentForMalware.php:78`
```php
} finally {
    if (isset($cleanup) && $cleanup && file_exists($localPath)) { @unlink($localPath); }
}
```
`$cleanup` n'est jamais défini. Les pièces jointes téléchargées depuis S3 vers `/tmp` pour
le scan ClamAV **ne sont jamais supprimées** → saturation disque progressive.

### S8. 🟠 Périmètre admin régional sous-évalué : requête sur une table vide
`app/Livewire/Admin/GestionUtilisateurs.php:210-217`
```php
->orWhereHas('rendezVousClient',  fn ($rdv) => $rdv->where('service_zone_id', $zoneId))
->orWhereHas('rendezVousEmploye', fn ($rdv) => $rdv->where('service_zone_id', $zoneId))
```
Ces relations (`app/Models/User.php:155,161`) pointent vers `App\Models\RendezVous`, dont
`$table = 'rendez_vous'` — la table **legacy** créée par
`2026_05_10_013026_restore_option_b_legacy_advanced_schema.php:207`. Tout le reste du code
utilise `bookings` (252 usages de `Booking::` contre 5 de `RendezVous::`).
Un admin scopé zone **ne voit pas** les utilisateurs dont le seul lien à sa zone passe par
leurs réservations. Sous-périmétrage silencieux.

### S9. 🟠 Factur-X : le pays de l'acheteur est toujours « FR »
`app/Services/Finance/EInvoicing/FacturXBuilder.php:74`
```php
<ram:CountryID>{$this->esc($client?->country ?? 'FR')}</ram:CountryID>
```
Vérifié : **la table `users` n'a aucune colonne `country`** (scan de toutes les migrations
`Schema::create/table('users')`). L'expression vaut donc toujours `'FR'`. Sur une plateforme
multi-pays (services `International`, `Country`, table `country_billing_profiles`, locales
fr/nl/en/es/it/de), **toutes les factures électroniques déclarent un acheteur français.**
Enjeu de conformité, pas seulement d'affichage.

### S10. 🟠 Crons de fonctionnalités livrées, jamais planifiés
Absents de `app/Console/Kernel.php` **et** de toute doc/CI :
- `disputes:process-sla` → **les SLA de litiges et leurs escalades ne tournent jamais**, alors
  que le module SAV est décrit comme « workflow complet (SLA, escalades) ».
- `loyalty:reevaluate-tiers` → les paliers Bronze/Silver/Gold/Platinum ne sont jamais recalculés.

### S11. 🟡 Double rafraîchissement FX
`Kernel.php` planifie `currencies:refresh` à 06h00 **et** `RefreshFxRatesJob` à 06h15, le
commentaire admettant le doublon (« via job async (vs sync via currencies:refresh) »).
Deux écritures concurrentes dans le ledger de taux.

---

## 4. Fonctionnalités « faux plafond » exposées à l'utilisateur

`routes/missing-route-fixes-advanced.php` (336 L, chargé depuis `routes/web.php:32`) est un
fichier de **réparation de routes** : il enregistre des pages via des chaînes
`class_exists()` en cascade, avec repli sur une page HTML inline dont le texte est
« *Cette page est maintenant routée. Il reste à connecter le vrai composant ou la vraie
logique métier.* ».

**18 des 35 classes Livewire importées en tête de fichier n'existent pas** (`ActivityLogsCenter`,
`AdminAutomationCenter`, `AdminB2BOperationsCenter`, `AdminCalendar`, `AdminEmailsCenter`,
`AdminFinance`, `AdminPlanning`, `AdminTools`, `AuditLogs`, `AutomationCenter`, `EmailsCenter`,
`FeedbacksAdmin`, `FinanceDashboard`, `ModulesCenter`, `PremiumClients`,
`PremiumClientsManager`, `TeamsPartnersCenter`, `AdminInternationalOperationsCenter`,
`AdminOrchestrationTerrainCenter`) — soit trois conventions de nommage concurrentes pour les
mêmes écrans (`X`, `AdminX`, `XCenter`).

Stubs **atteignables et liés depuis l'UI** :

| Route | Ce que reçoit l'utilisateur | Lien UI |
|---|---|---|
| `admin.feedbacks.export` | PDF contenant « Export feedbacks / Export PDF temporaire des feedbacks. » | `livewire/admin/dashboard/export-feedbacks.blade.php:16` |
| `client.rendezvous.series.edit` | HTML brut sans layout : `<h1>Gérer ma série récurrente</h1><p>Série ID : 42</p>` | `livewire/client/rendezvous/actions.blade.php:30` |
| `admin.export.pdf` | PDF « Export PDF temporaire. À remplacer par la logique ExportTools. » | non lié |
| `admin.rendezvous.series.edit` | HTML brut | non lié |

**Alors que les vrais gabarits existent et dorment** : `resources/views/exports/feedbacks.blade.php`,
`feedbacks-pdf.blade.php`, `rendezvous.blade.php`, `rendezvous-pdf.blade.php`,
`rendez-vous-pdf.blade.php` — **0 référence**, et `exports/users.blade.php` est **vide (0 ligne)**.

Autres défauts du même fichier :
- `admin.feedbacks.export` est déclaré **deux fois** (l. ~130 sans `->name()`, donc le garde
  `Route::has()` du second bloc ne se déclenche pas) ; la première définition, sans contrôle
  `abort_unless`, masque la seconde.
- La page de repli charge **`https://cdn.tailwindcss.com`** — dépendance CDN externe en prod.
- `$livewireOrFallback([AdminFeedbacks::class, AdminFeedbacks::class, FeedbacksAdmin::class], …)`
  — même classe listée deux fois (copier-coller).
- `admin.export.csv` écrit `$rdv->status` et `$rdv->date` sans échappement CSV (les autres
  champs le sont) — injection CSV possible dans un tableur.

---

## 5. Incohérences architecturales — split brain legacy / V2

Neuf couples de modules **coexistent et sont tous les deux appelés en production**. Ce n'est
pas une migration en cours (un seul consommateur restant) : ce sont deux implémentations
vivantes, atteintes par des chemins différents.

| Domaine | Legacy → appelé par | V2 → appelé par | Risque |
|---|---|---|---|
| **Annulation** | `Services/Cancellation` ← `Api/Provider/ProviderCancellationController` | `Services/CancellationV2` ← `Api/CancellationV2Controller`, `Livewire/Client/MesRendezVousClient` | 🔴 **le prestataire et le client annulent via deux moteurs de frais différents** |
| **Tarification** | `Services/Pricing` ← `Api/Client/BookingEstimateController`, `RecomputeSurgeCommand` | `Services/PricingV2` ← `Api/PricingV2Controller` | 🔴 **deux prix possibles pour la même prestation selon le point d'entrée** |
| **Abonnements** | `Services/Subscription` ← cron `app:generate-subscriptions` (quotidien) | `Services/SubscriptionsV2` ← cron `subscriptions:tick` (03h00) | 🔴 deux systèmes récurrents sur cron simultanément (`client_subscriptions` vs `subscriptions_v2`) |
| **Géo** | `Services/Geo` ← `SmartDispatchService` **+** `Services/Geocoding` ← `MissionFromRendezVousSyncService` | `Services/GeolocationV2` ← matching, trip tracking, ops | 🟠 **trois** implémentations de distance/géocodage → résultats divergents entre modules |
| **Contrats** | `Services/Contracts` (9 appelants) | `Services/ContractsV2` (4 appelants) | 🟠 |
| **Chat** | `Services/Messaging` ← `ProviderCompany/TeamChannels` | `Services/ChatV2` ← API, inbox client, admin | 🟠 deux systèmes de messagerie |
| **Email** | `Services/Email` ← listeners, `ProductEmailsCenter` | `Services/EmailV2` ← provider dédié, loyalty | 🟠 |
| **Onboarding** | `Services/Onboarding` (wizard web, API) | `Services/OnboardingV2` (API V2) | 🟡 partiellement consolidé (le legacy appelle le V2) |
| **i18n** | `Services/I18n` (9 appelants) | `Services/Localization` ← uniquement `View/Components/Money` | 🟡 |

À quoi s'ajoutent :
- **`Services/Booking` (17 fichiers) et `Services/Bookings` (2 fichiers)** — deux namespaces
  au singulier/pluriel. Piège garanti à la relecture.
- **Trois mécanismes de récurrence** : `recurring_booking_series` (cron `bookings:process-recurring`),
  `recurring_templates` (→ alimente les series), `client_subscriptions` (cron
  `app:generate-subscriptions`). Un client possédant à la fois une série et un abonnement peut
  voir **deux réservations créées pour le même créneau** — À CONFIRMER par un test dédié, la
  déduplication de `SubscriptionScheduler` ne regarde que les `Booking` existants, pas l'origine.

### Modèle Booking / RendezVous
`app/Models/RendezVous.php` **étend** `Booking` en changeant `$table` (`rendez_vous`). Deux
tables pour un même concept métier, avec des colonnes FR **et** EN dupliquées dans la table
legacy (`type_lieu`+`place_type`, `frequence`+`frequency`, `priorite`+`priority`,
`adresse`+`address`, `date`/`heure`+`scheduled_at`). Le nommage reste incohérent partout :
`RendezVousObserver` observe `Booking`, `RendezVousPolicy` garde `Booking`
(`AppServiceProvider.php:106`, `AuthServiceProvider.php:23`).

### Argent : deux résolveurs de prestataire dans le même flux
- `CommissionService::calculateForBooking()` : `assignedProvider ?? employe ?? provider`
- `MissionPaymentService::authorize()` (l. 22) : `$rendezVous->employe` **uniquement**, et lève
  « Le prestataire ne peut pas encore recevoir de paiements » si absent.

Une réservation assignée par le chemin moderne (`assigned_provider_user_id` seul) est donc
calculable mais **non encaissable**. À CONFIRMER : le dispatch renseigne-t-il toujours
`employe_id` en plus ?

Autres points argent, dans `app/Services/Payments/CommissionService.php:43` :
- La base de commission est `devis_estime ?? estimated_price ?? payment_amount_cents/100` :
  **la colonne legacy a la priorité** sur la moderne, et **`final_price` est ignoré**. Une
  mission facturée 150 € pour un devis de 100 € ne génère de commission que sur 100 €.
- `'currency' => 'eur'` **codé en dur**, alors que `MissionPaymentService:44` crée le
  PaymentIntent avec `pricing_snapshot['currency']`. Si ce snapshot vaut autre chose qu'EUR,
  **Stripe encaisse un montant calculé en EUR libellé dans une autre devise.** Incohérent avec
  tout le module FX/multi-devises.
- `min($platformFeeCents, $totalCents)` combiné au minimum de 200 c : sur une prestation à
  1 €, la commission vaut 1 € et le prestataire touche 0 €.

---

## 6. Le filet de sécurité ne protège plus

### 6.1 PHPStan : 15 652 erreurs neutralisées, et la porte est déjà rouge

`phpstan-baseline.neon` = **815 Ko / 18 781 lignes / 15 652 erreurs supprimées**.
`phpstan.neon` ajoute `reportUnmatchedIgnoredErrors: false`, ce qui rend la baseline
non auditable (les entrées périmées ne sont plus signalées).

**Chacun des bloquants du §2 est explicitement listé dans la baseline** :

| Bug | Occurrences dans la baseline |
|---|---|
| `Mission::assignedWorker` (B1) | 1 |
| `SimpleSoftwareIO\QrCode` (B2) | 1 |
| `PhpOffice\PhpSpreadsheet` (B3) | 11 |
| `FleetVehicle::certifications` (B6) | 2 |
| `MissionTaskSegment::missionBatchDay` (B5) | 2 |
| `BookingApproval` / `BookingAttachment` | 8 |
| `Undefined variable: $mission` (S5) | 1 (l. 14284) |
| `Undefined variable: $cleanup` (S7) | 1 (l. 16318) |

L'outil a détecté sept crashs de production, et on lui a demandé de se taire.

**Et malgré cela : `vendor/bin/phpstan analyse` renvoie aujourd'hui `Found 173 errors`** — donc
l'étape CI « Static analysis (PHPStan / Larastan) » **échoue sur l'arbre actuel**. Parmi elles,
de vrais défauts non baselinés :

| Fichier:ligne | Erreur |
|---|---|
| `Livewire/Employe/MesRendezVous.php:449` | propriété `$selectedRendezVous` non déclarée sur un composant Livewire (casse l'hydratation d'état) |
| `Services/Bundles/MultiTradeBundleService.php:182,342,358` | `$pivot`, `Model::$id`, `Model::$bundle` non définis |
| `Services/Finance/EInvoicing/FacturXBuilder.php:74` | `User::$country` inexistant → **S9** |
| `Services/Calendar/GoogleCalendarBidirectionalService.php:92,97` | `Model` passé où `GoogleCalendarConnection` / `User` est attendu |
| `Services/CancellationV2/CancellationEngine.php:314`, `Insurance/InsurancePricingEngine.php:79` | comparaison toujours vraie (garde morte) |

### 6.2 Cause racine : 44 docblocks de relation faux sur 623

Le scan docblock↔implémentation révèle un motif net — **les génériques ont été recopiés en
série depuis la méthode précédente, sans vérification** :

```
app/Models/Mission.php
  media():          doc=HasMany<MissionTaskSegment>   actual=hasMany(MissionMedia)
  incidents():      doc=HasMany<MissionTaskSegment>   actual=hasMany(MissionIncident)
  qualityReviews(): doc=HasMany<MissionTaskSegment>   actual=hasMany(MissionQualityReview)
  report():         doc=HasOne<MissionTaskSegment>    actual=hasOne(MissionReport)
  events():         doc=HasMany<MissionTaskSegment>   actual=hasMany(MissionEvent)

app/Models/BusinessEntity.php        → les 4 relations annoncent HasMany<User>
app/Models/MissionQualityInspection.php → les 3 relations annoncent HasMany<User>

app/Models/Booking.php
  mission():        doc=HasOne<Feedback>              actual=hasOne(Mission)
  latestFeedback(): doc=HasOne<BookingApproval>       actual=hasOne(Feedback)

app/Models/Concerns/HasProviderFeatures.php
  providerProfile(): doc=HasOne<AvailabilitySlot>     actual=hasOne(ProviderProfile)
  trades():          doc=BelongsToMany<EmployeeZoneAssignment> actual=belongsToMany(Trade)
  zoneAssignments(): doc=HasMany<ServiceZone>         actual=hasMany(EmployeeZoneAssignment)
```
(liste complète : 44 entrées sur 18 modèles)

**Conséquence en chaîne** : Larastan croit que `Booking::mission` est un `Feedback`, donc il
signale « relation `report` introuvable sur Feedback », etc. Ces faux positifs ont grossi la
baseline, qui a ensuite avalé les vrais positifs. **Corriger ces 44 docblocks est le
prérequis à toute réduction de la baseline** — c'est le point de levier n°1 du codebase.

Effet collatéral direct : `HasProviderFeatures.php:200`, `hasClearedKyc()` — la porte KYC
stricte « décision produit 2026-06-11 » — est **non vérifiable statiquement** parce que le
docblock de `providerProfile()` mentionne `AvailabilitySlot`.

### 6.3 Qualité de la suite de tests

- **4223 tests verts**, mais **1095 méthodes (26 %) vivent dans 146 fichiers `*CoverageBatch*Test.php`** —
  tests écrits pour atteindre le seuil de couverture (CI : `--min=80`), pas pour spécifier un
  comportement. Beaucoup se contentent de vérifier qu'un cas limite est un « no-op ».
- **Un test verrouille un bug comme spec** (S1). Il faut supposer qu'il n'est pas seul.
- **Trous de couverture exactement là où sont les crashs** : `ProviderDashboard` (B1),
  `client.exports.bookings.xlsx` (B3), `TeamLeadOperationsCenter` (B5) → aucun test dédié.
- Le job CI **MySQL avec clés étrangères** est `continue-on-error: true` (« issue #5 ») : la
  suite par défaut tourne sur SQLite `DB_FOREIGN_KEYS=false`, donc **aucune contrainte
  d'intégrité référentielle n'est vérifiée de façon bloquante**.
- Le job **E2E Playwright** est également `continue-on-error: true`.
- `php artisan test --parallel` est **cassé** (ParaTest absent) → 21 minutes de boucle de
  rétroaction locale.

---

## 7. Schéma de base : 268 tables, 204 migrations, fragilité assumée

- **24 migrations de « réparation »** (`fix_*`, `*_compat_*`, `restore_*`, `align_*`), avec des
  noms comme `fix_runtime_schema_round6`, `fix_remaining_test_schema_compat_round_final`.
  Le schéma cible n'est pas décrit à un seul endroit : il est le résultat d'un empilement.
- **`bookings` est altérée par 32 blocs `Schema::table` distincts**, `users` par 27,
  `provider_profiles` et `missions` par 12. Une base fraîche et une base migrée
  incrémentalement ne convergent pas nécessairement.
- **12 tables sont créées dans plus d'une migration** — `field_teams` et `field_team_members`
  le sont **deux fois dans le même fichier** (`restore_option_b_legacy_advanced_schema.php`).
  Liste : `message_reactions`, `customer_credit_transactions`, `service_zone_postal_code`,
  `mission_task_segments`, `field_teams`, `field_team_members`, `mission_batches`, `feedback`,
  `work_order_approvals`, `enterprise_booking_approvals`, `country_billing_profiles`,
  `mission_team_assignments`.
- **Tables d'historique jamais écrites** : `booking_status_histories`, `mission_histories`,
  `mission_positions`, `mission_media` — 0 référence dans `app/`. Il n'y a donc **aucun
  historique de changement de statut de réservation**, ce qui pose un problème pour les litiges
  et l'audit.
- **Le trait d'audit Eloquent est orphelin** : `app/Services/Audit/Concerns/AuditsEloquentEvents.php`
  (72 L) — **aucun modèle ne l'utilise**. La piste d'audit automatique décrite pour le module
  Audit v2 n'est pas branchée. (Même motif que le trait `BelongsToTenant` déjà repéré en mai.)
- **Reliquats de Tenancy V2** (module retiré le 2026-05-29) : les tables `tenants`,
  `tenant_domains`, `tenant_users` existent toujours, et **`ProductionBootstrapSeeder` — appelé
  en production — écrit encore dans `tenants`**.

---

## 8. Sécurité & configuration de production

### 8.1 Ce qui est bon (à préserver)
- `.env` **non tracké**, correctement ignoré, **rien dans l'historique git**
  (`git log --all -- .env .env.production` → vide). `.env.example` (635 L) ne contient aucune
  vraie clé.
- **Aucune faille IDOR trouvée** : sur 189 composants Livewire, 5 seulement écrivent sans garde
  visible, et après revue manuelle les 5 sont correctement scopés
  (`Disponibilite::where('user_id', Auth::id())->findOrFail()`,
  `$user->notifications()->whereKey()`, …). C'est un vrai point fort, cohérent avec la
  convention `EnforcesActiveOrgMembership`.
- `composer audit` et `npm audit --omit=dev` : **0 vulnérabilité**.
- Aucune god class : le plus gros fichier `app/` fait 776 lignes.
- CI complète : `composer validate --strict`, Pint, PHPStan, audits de dépendances, tests avec
  seuil de couverture, job MySQL FK, E2E Playwright.

### 8.2 `env()` hors config : 15 appels qui renvoient `null` avec `config:cache`

`config:cache` est obligatoire en production ; `env()` y renvoie **`null`**. Les appels
suivants sont donc **des désactivations silencieuses en production uniquement** :

| Fichier:ligne | Effet en production |
|---|---|
| `Http/Controllers/Webhooks/StripeConnectWebhookController.php:36` | 🔴 **secret de webhook Stripe Connect = null** → vérification de signature compromise |
| `Http/Middleware/SecurityHeaders.php:33,34,35,41` | 🔴 **CSP / HSTS / en-têtes de sécurité désactivés** |
| `Http/Middleware/VerifyTurnstileCaptcha.php:35` | 🔴 **captcha inopérant** |
| `Services/Safety/MaskedCallService.php:121-123,183-185` | 🟠 appels masqués Twilio cassés (sécurité utilisateur) |
| `Http/Middleware/TrustProxies.php:21` | 🟠 IP client fausse derrière proxy (rate-limit, audit, géo) |
| `Services/Eta/EtaService.php:129` | 🟠 clé Google Maps absente → ETA dégradées |
| `Services/Payments/StripeReconciliationService.php:317` | 🟠 réconciliation Stripe faussée |
| `Services/Ai/PhotoQuoteEstimator.php:30` | 🟡 |

Larastan les signale (`larastan.noEnvCallsOutsideOfConfig`) — les 9 sont **dans la baseline**.

### 8.3 Clés de configuration lues mais inexistantes
Vérifié en amorçant l'application (`config()->has()`) :

| Clé lue | Statut |
|---|---|
| `push.fcm.*`, `push.apns.*` | ❌ inexistantes (bon chemin : `push.providers.*`) → **S4** |
| `services.google_maps.api_key` | ❌ inexistante, **sans valeur par défaut** |
| `services.turnstile.site_key` | ❌ inexistante |
| `backup.restore.path` | ❌ inexistante, **sans défaut** (drill de restauration) |
| `geolocation_v2.isochrone_avg_speed_kmh`, `kyb_v2.identifier_types_by_country` | ❌ inexistantes |

*(Faux positifs écartés après vérification : `cashier.*` est bien fourni par Cashier ;
`config/audit.php`, `cancellation_v2.php`, `marketing.php`, `presence.php`, `tips.php` sont
lus via la façade `Config::get()`.)*

### 8.4 Autres points
- `INSURANCE_PROVIDER=mock` dans **`.env.production.example:200`** — le module assurance
  tournerait en simulation en production. À CONFIRMER : intentionnel (pas d'assureur contracté) ?
- `scripts/patch_php85_deprecations.php` **modifie `vendor/laravel/framework/config/database.php`**
  à chaque `composer install`. Casse la reproductibilité (`--no-scripts` produit un arbre
  différent) et toute vérification d'intégrité de vendor. Le commentaire dit « Laravel 11 »
  alors que `composer.json` exige `^12.0`.
- `axios`, `laravel-echo`, `pusher-js` sont en **`devDependencies`** bien qu'utilisés à
  l'exécution dans le bundle → **`npm audit --omit=dev` de la CI ne les audite pas**.
- **Fichiers tracké à retirer** : `mobile/.expo/dev/logs/start.log` (log, modifié à chaque
  démarrage → bruit git permanent), `mobile/package-lock.json.bak`, `mobile/ruvector.db`
  (artefact d'agent). Le `.gitignore` ignore `/agentdb.rvf` et `ruvector.db` à la racine mais
  pas ceux de `mobile/`.

---

## 9. Code mort — inventaire chiffré et supprimable

### 9.1 Services squelettes : uniquement des `throw new RuntimeException('… TODO')` — **248 L**
Aucun n'est référencé nulle part.

| Fichier | L | Contenu |
|---|---|---|
| `app/Services/Ai/PhotoQuoteWorkflowService.php` | 79 | 8 TODO, `createBookingFromQuote()` lève une exception |
| `app/Services/Provider/MultiProviderCoordinationService.php` | 61 | 6 TODO, tout lève |
| `app/Services/Risk/FraudMlService.php` | 58 | 6 TODO, retourne `[]` |
| `app/Services/Video/VideoCallService.php` | 50 | 4 TODO, tout lève |

### 9.2 Classes orphelines (0 référence) — **~600 L**

| Fichier | L | Remarque |
|---|---|---|
| `app/Http/Middleware/CheckOrganizationPermission.php` | 74 | ⚠️ middleware de permission d'organisation **jamais enregistré** — vérifier qu'aucun contrôle ne repose dessus |
| `app/Notifications/Channels/SmsChannel.php` | 72 | jamais enregistré → **S6** |
| `app/Services/Audit/Concerns/AuditsEloquentEvents.php` | 72 | trait d'audit sans aucun modèle → §7 |
| `app/Services/Media/ImageOptimizer.php` | 78 | les images ne sont donc pas optimisées |
| `app/Services/Missions/MissionReportPdfService.php` | 30 | |
| `app/Livewire/Notifications.php` | 177 | doublon de `NotificationsCenter` (192 L) ; **devenu totalement mort** avec la modification non commitée de `layouts/app.blade.php` qui retire `@livewire('notifications')` |
| `app/Http/Requests/Api/Provider/MissionLocationRequest.php` | 21 | FormRequest inutilisé → l'endpoint de position valide-t-il quelque chose ? À CONFIRMER |
| `app/Models/RendezVous.php` | 68 | modèle legacy, 2 usages résiduels, tous deux bugués (**S8**) |
| `app/View/Components/RdvCleaningCard.php` + `StatCardDark.php` | 62 | balises `<x-…>` jamais utilisées |

### 9.3 Vues Blade sans référence — **1 115 L / 35 fichiers**
Dont les 6 gabarits d'export dormants (§4), `emails/team-invitation.blade.php` (§S2),
4 partiels `livewire/admin/automation/*`, les reliquats Jetstream
(`switchable-team`, `application-mark`, `welcome`), 5 wrappers de page non routés
(`admin/gestion-utilisateurs-page`, `employe/{feedbacks,validation-multiple,missions}-page`,
`admin/countries-page`), et **`exports/users.blade.php` vide (0 ligne)**.
*(Exclus de ce total : `resources/views/auth/*` — utilisées par convention Fortify/Jetstream —
et `scribe/index.blade.php`, 5 139 L générées.)*

### 9.4 Fichier de réparation de routes — **336 L**
`routes/missing-route-fixes-advanced.php` : à supprimer après avoir soit branché les vrais
composants, soit retiré les entrées de navigation (§4).

### 9.5 23 tables sans aucun code applicatif
`team_user`, `team_invitations` (Jetstream teams non utilisées) · `provider_teams`,
`provider_team_members`, `provider_availabilities`, `provider_daily_limits` (**encore
alimentées par des seeders !**) · `booking_status_histories`, `mission_histories`,
`mission_positions`, `mission_media`, `mission_batch_days` · `invoice_items`,
`customer_credit_transactions`, `subscription_items`, `account_subscriptions`, `pricing_logs` ·
`knowledge_articles`, `platform_settings`, `google_calendar_events` ·
`tenants`, `tenant_domains`, `tenant_users` (module retiré) · `rendez_vous` (via §5).

⚠️ Ne pas supprimer sans vérifier la présence de données en production —
`customer_credit_transactions` et `invoice_items` touchent au financier.

### 9.6 Commandes ops jetables (~700 L)
`app:audit-seed-integrity`, `db:check-missing-tables`, `app:security-audit`, `livewire:routes`,
`livewire:unused`, `livewire:verify`, `deploy:check`, `dispo:generer`, `webpush:vapid`,
`translations:scan`, `translations:sync` — 0 référence hors auto-découverte Laravel. Scripts
d'audit ponctuels devenus du poids mort. `AuditSeedIntegrity.php:29` référence en plus une
relation `User::postalCode` **inexistante** → la commande plante si on l'exécute.
`GenerateSubscriptions.php:22` a gardé la description par défaut du stub :
`'Command description'`.

### 9.7 Mobile : deux applis Expo, écrans copiés-collés
`mobile/client` et `mobile/provider` partagent **18 noms de fichiers identiques** — dont
`LoginScreen.tsx` (305 L vs 288 L, **48 lignes de diff seulement**, ~85 % identique),
`HomeScreen`, `ProfileScreen`, `RootNavigator`, `TabNavigator`, `AppearanceScreen`,
`LanguageScreen`, `LegalScreen`, `NotificationPreferencesScreen`, `OnboardingScreen`,
`ForgotPasswordScreen`, plus 5 mocks de test dupliqués (`expo-camera`, `react-native-maps`,
`stripe-react-native`, `vector-icons`, `react-native-safe-area-context`).
**~2 842 lignes dans ces fichiers**, dont une grande part duplifiée.

`mobile/shared/` existe pourtant (api, auth, chat, offlineQueue) mais n'héberge que la couche
données. Et l'import passe par un **chemin relatif fragile** au lieu du nom de package :
`mobile/client/src/lib/offlineQueue.ts:2` → `'../../../shared/src/lib/offlineQueue'`.

### 9.8 Relations Booking pointant vers des modèles absents
`app/Models/Booking.php:544-553` :
```php
public function approvals(): HasMany  { return $this->hasMany(BookingApproval::class); }
public function attachments(): HasMany { return $this->hasMany(BookingAttachment::class); }
```
Ni `App\Models\BookingApproval` ni `App\Models\BookingAttachment` n'existent (le modèle réel
est `EnterpriseBookingApproval`). Personne ne les appelle aujourd'hui — **bombe à retardement** :
le premier appel donne un fatal. À supprimer ou à rebrancher. Le docblock de
`latestFeedback()` (l. 537) annonce également `BookingApproval` alors qu'il retourne `Feedback`.

### 9.9 Total supprimable identifié
**≈ 5 900 lignes de PHP/Blade + 23 tables + ~2 000 lignes de duplication mobile.**

---

## 10. Plan d'action priorisé

### Sprint 0 — arrêter l'hémorragie (2–3 j)
1. **B2** — installer `simplesoftwareio/simple-qrcode` (ou réécrire sur BaconQrCode). *Le QR est le cœur du produit.*
2. **B1** — créer `Mission::assignedWorker()` (ou corriger l'eager-load + la vue).
3. **B4** — ajouter `use Illuminate\Database\Eloquent\Builder;` dans `HandlesAdminDashboardPlanning`.
4. **B7** — passer l'argument requis à `RecomputeSegmentJob::dispatch()`.
5. **S1** — corriger l'`instanceof RendezVous` (→ `Booking`) **et réécrire le test** qui l'entérine.
6. **S4** — corriger les chemins `config('push.providers.…')`.
7. **8.2** — remplacer les 15 `env()` par `config()`, en priorité `StripeConnectWebhookController:36`,
   `SecurityHeaders`, `VerifyTurnstileCaptcha`. **Vérifier ensuite avec `config:cache` actif.**
8. Ajouter un test de fumée « la page rend » pour chaque composant Livewire routé — c'est ce
   test unique et trivial qui aurait attrapé B1, B4, B5 et B6.

### Sprint 1 — rétablir le filet (1 semaine)
9. **Corriger les 44 docblocks de relation** (§6.2) — prérequis technique de tout le reste.
10. Régénérer la baseline PHPStan **après** ce nettoyage, et remettre
    `reportUnmatchedIgnoredErrors: true`. Cible : diviser 15 652 par 5 au premier passage.
11. Rendre la CI verte de nouveau (les 173 erreurs actuelles) et **interdire l'ajout d'entrées
    à la baseline** en revue.
12. **B3, B5, B6, B8** — corriger ou retirer proprement (route + entrée de nav).
13. **S2** — implémenter réellement les invitations (le gabarit d'email existe déjà) **et**
    ajouter une étape d'acceptation avant `status = 'active'`.
14. **S3** — brancher l'envoi email marketing, ou le désactiver explicitement par config.
15. **S10** — planifier `disputes:process-sla` et `loyalty:reevaluate-tiers`.
16. **S7, S9, S11** — fuite `/tmp`, pays Factur-X, doublon FX.

### Sprint 2 — trancher les doublons (2 semaines)
17. Choisir **un** moteur par domaine et supprimer l'autre, dans cet ordre de risque financier :
    **Annulation → Tarification → Abonnements → Géo (3 → 1) → Chat → Contrats → Email → i18n.**
18. Unifier l'argent : `CommissionService` doit lire `final_price`, respecter la devise du
    `pricing_snapshot`, et `MissionPaymentService` doit utiliser le même résolveur de prestataire.
19. Fusionner `Services/Booking` et `Services/Bookings` ; retirer `Models/RendezVous` + la table
    `rendez_vous` après migration de `GestionUtilisateurs` (**S8**).
20. Trancher entre `client_subscriptions` et `recurring_booking_series`.

### Sprint 3 — hygiène (1 semaine)
21. Supprimer les ~5 900 lignes du §9 (squelettes, orphelins, vues, fichier de réparation).
22. Retirer les 23 tables mortes après contrôle des données de production ; nettoyer les
    seeders qui alimentent `tenants` et `provider_*`.
23. Hisser les écrans communs dans `mobile/shared`, remplacer les imports relatifs par le
    nom de package.
24. Réparer `--parallel` (installer ParaTest) : 21 min → ~4 min de boucle locale.
25. `git rm --cached mobile/.expo/dev/logs/start.log mobile/package-lock.json.bak mobile/ruvector.db`
    et étendre le `.gitignore`.
26. Déplacer `axios`/`laravel-echo`/`pusher-js` en `dependencies` pour qu'ils soient audités.
27. Rendre bloquants les jobs CI **MySQL-FK** et **E2E** (issue #5).
28. Remplacer `scripts/patch_php85_deprecations.php` par un pin de version PHP ou un patch
    déclaré (`cweagans/composer-patches`).

---

## 11. Ce qu'il faut retenir

Le codebase n'est pas de mauvaise qualité — il est **non vérifié**. Les indicateurs de
qualité classiques sont tous au vert : pas de god class, autorisation soignée, 0 vulnérabilité,
CI riche, 4223 tests. Mais les trois garde-fous qui auraient dû attraper les crashs ont chacun
été neutralisés d'une manière différente :

- **PHPStan** a détecté les 7 crashs → on les a mis en baseline.
- **Les tests** couvrent 80 % des lignes → mais 26 % des méthodes de test sont du remplissage
  de couverture, un test grave un bug comme spec, et les composants qui plantent n'ont
  aucun test.
- **Les types** devaient guider l'analyse → 44 docblocks de relation faux la font mentir.

Le levier le plus rentable n'est aucun des correctifs individuels : c'est **restaurer la
véracité du signal** (docblocks → baseline → CI bloquante → test de fumée par écran routé).
Sans cela, le prochain audit trouvera sept nouveaux crashs invisibles.

Second levier, non technique : **arrêter d'ajouter des modules V2 à côté des V1.** Neuf
doublons vivants, dont deux sur le chemin de l'argent (annulation, tarification), c'est le
risque le plus élevé du projet à l'approche du lancement — bien avant n'importe lequel des
bugs listés ici.
