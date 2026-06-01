# SP2 — Sélection du type de prestataire côté client (3 paliers) — Plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Donner au client le contrôle sur QUI réalise la prestation — choix du type (independent/company/any), re-réserver un favori, ou (premium) choisir un prestataire précis — câblé au matching SP1, sur web + mobile, avec le portail société unifié sur l'action canonique.

**Architecture :** Une colonne `provider_type_preference` + le `preferred_provider_user_id` existant portent le choix. `CreateBookingAction` (seul chemin) les persiste ; `SmartDispatchService` honore le type via l'éligibilité SP1 ; un `PreferredProviderResolver` gère « presta précis dispo→assigné / indispo→créneaux de X » ; un `ProviderSelectionResolver` valide les 3 paliers + le gating premium. UI web (`PrendreRendezVous`) + mobile (wizard `BookingStep*`) branchées dessus. `BookingHub` refactoré sur `CreateBookingAction`.

**Tech Stack :** Laravel 10 (Eloquent, Livewire), Expo/React Native (TS, Jest), PHPStan full, Pint.

**Spec :** `docs/superpowers/specs/2026-06-01-relations-sp2-client-provider-selection-design.md`
**Branche :** `feat/relations-sp2-client-selection` (off `main`).

---

## Vérités terrain (vérifiées)

- `bookings` a déjà `preferred_provider_user_id` (y), `employe_id` (y), `assigned_provider_user_id` (y) ; **PAS** `provider_type_preference` (n → à créer).
- `app/Services/Booking/CreateBookingAction.php::execute(User $client, PostalCode, ServiceZone, ServiceCatalog $catalog, ZoneServiceRule, User $assignedEmployee, array $data, ?OrganizationSite, ?ZoneCoverageResult): Booking` — crée la `Booking` (le gros `Booking::create([...])` lignes 120-177, où il faut ajouter les 2 champs) puis **re-dispatche** via `SmartDispatchService::assignBestEmployee($freshRdv)` (lignes 263-298, où `employe_id` est écrasé par le meilleur). NB : `assignBestEmployee` y est appelé en double (263-264 + 266-270) — redondance pré-existante à nettoyer en passant.
- `app/Services/Booking/SmartDispatchService.php::assignBestEmployee(Booking $rdv): ?User` : `sortedEligibleEmployeesForZone((int) $rdv->service_zone_id)` (défaut providerType='any') → filtre `employeeIsAvailableForSlot($empId, $date, $heure, $zone, $duration, $rdv->id)` → tri score → `first()`.
- `app/Services/Booking/EmployeeAvailabilityService.php` (SP1) : `sortedEligibleEmployeesForZone(int $zoneId, string $providerType='any')` + `employeeIsAvailableForSlot(int $empId, string $date, string $heure, ?ServiceZone, int $duration=90, ?int $ignoreRdvId): bool`.
- `app/Models/CustomerProfile.php::isPremium(): bool` = `plan_type==='premium' && plan_status==='active'`. `User::customerProfile()` (HasOne). Pas de plan au niveau org.
- Favoris : `App\Models\BookingFavorite` (table `booking_favorites`, scope par `client`), `App\Services\Bookings\BookingFavoriteService` (createFromBooking/markUsed/delete). Le re-book pose déjà `preferred_provider_user_id` (`BookingFavoriteService:57`).
- Recherche prestataires : `app/Livewire/Client/BrowseProviders.php` (réutilisable pour le palier premium).
- Form web : `app/Livewire/Client/PrendreRendezVous.php` (appelle `$this->bookingCreator()->execute(...)` ~ligne 228). Wizard mobile : `mobile/client/src/screens/booking/BookingStep1Service.tsx … BookingStep4Scheduling.tsx` + `BookingProvider.tsx` + `BookingNavigator.tsx`.
- Portail société : `app/Livewire/ClientCompany/BookingHub.php::submitBooking()` fait un `Booking::create([...])` ad-hoc (sans `service_catalog_id`, colonnes B2B-spécifiques) → à refactorer sur `CreateBookingAction`.

---

## File structure

- Create `database/migrations/2026_06_02_000001_add_provider_type_preference_to_bookings.php` (Task 1)
- Modify `app/Models/Booking.php` — fillable + cast + helper `prefersProviderType()` (Task 1)
- Modify `app/Services/Booking/CreateBookingAction.php` — persiste les 2 champs (Task 2)
- Modify `app/Services/Booking/SmartDispatchService.php` — honore `provider_type_preference` (Task 3)
- Create `app/Services/Booking/PreferredProviderResolver.php` (Task 4)
- Create `app/Services/Booking/ProviderSelectionResolver.php` (Task 5)
- Modify `app/Livewire/Client/PrendreRendezVous.php` + sa vue Blade (Task 6)
- Modify `mobile/client/src/screens/booking/*` + `BookingProvider.tsx` (Task 7)
- Modify `app/Livewire/ClientCompany/BookingHub.php` (Task 8)

---

## Task 1 : Colonne `provider_type_preference`

**Files :** Create migration ; Modify `app/Models/Booking.php` ; Test `tests/Feature/Relations/ProviderPreferenceColumnTest.php`

- [ ] **Step 1 : Test qui échoue**
```php
<?php

namespace Tests\Feature\Relations;

use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProviderPreferenceColumnTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_type_preference_is_persisted_and_defaults_to_any(): void
    {
        $this->assertTrue(Schema::hasColumn('bookings', 'provider_type_preference'));

        $booking = Booking::factory()->create(['provider_type_preference' => 'company']);
        $this->assertSame('company', $booking->fresh()->provider_type_preference);
        $this->assertTrue($booking->prefersProviderType('company'));

        $default = Booking::factory()->create();
        $this->assertSame('any', $default->fresh()->provider_type_preference);
    }
}
```

- [ ] **Step 2 : FAIL.** `php artisan test --filter=ProviderPreferenceColumnTest`

- [ ] **Step 3 : Migration idempotente** — `database/migrations/2026_06_02_000001_add_provider_type_preference_to_bookings.php` :
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'provider_type_preference')) {
                $table->string('provider_type_preference', 20)->default('any')->after('preferred_provider_user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (Schema::hasColumn('bookings', 'provider_type_preference')) {
                $table->dropColumn('provider_type_preference');
            }
        });
    }
};
```
(Si `preferred_provider_user_id` n'existe pas comme ancrage en DB — il existe, vérifié — garder le `->after()` ; sinon le retirer.)

- [ ] **Step 4 : Booking model** — dans `app/Models/Booking.php`, ajouter `'provider_type_preference'` au `$fillable`, et ajouter le helper :
```php
    public function prefersProviderType(string $type): bool
    {
        return ($this->provider_type_preference ?? 'any') === $type;
    }
```

- [ ] **Step 5 : PASS** `php artisan test --filter=ProviderPreferenceColumnTest`

- [ ] **Step 6 : pint + commit**
```bash
vendor/bin/pint database/migrations app/Models/Booking.php tests/Feature/Relations/ProviderPreferenceColumnTest.php
git add database/migrations app/Models/Booking.php tests/Feature/Relations/ProviderPreferenceColumnTest.php
git commit -m "feat(relations): bookings.provider_type_preference column (default any)"
```

---

## Task 2 : `CreateBookingAction` persiste la préférence + le presta précis

**Files :** Modify `app/Services/Booking/CreateBookingAction.php` ; Test `tests/Feature/Relations/CreateBookingPersistsPreferenceTest.php`

- [ ] **Step 1 : Test qui échoue**
Le test appelle `CreateBookingAction::execute(...)` avec `$data` contenant `provider_type_preference` + `preferred_provider_user_id` et vérifie qu'ils sont persistés. **Lis d'abord un test existant de `CreateBookingAction`** (cherche `tests/` pour `CreateBookingAction` ou un helper de fixtures booking — `CreatesZoneAwareFixtures`/`CreatesDomainFixtures`) pour construire les arguments réels (`$client, $postal, $zone, $catalog, $rule, $assignedEmployee, $data`). Squelette :
```php
public function test_execute_persists_provider_type_preference_and_preferred_provider(): void
{
    // ... seed client, postal, zone, catalog, rule, assignedEmployee (réutiliser un helper existant) ...
    $preferred = /* un User prestataire éligible */;
    $booking = app(\App\Services\Booking\CreateBookingAction::class)->execute(
        $client, $postal, $zone, $catalog, $rule, $assignedEmployee,
        [
            // ... données minimales requises (date, heure, service_zone_id, postal_code_id, etc.) ...
            'provider_type_preference' => 'independent',
            'preferred_provider_user_id' => $preferred->id,
        ]
    );
    $this->assertSame('independent', $booking->fresh()->provider_type_preference);
    $this->assertSame($preferred->id, $booking->fresh()->preferred_provider_user_id);
}
```

- [ ] **Step 2 : FAIL.** `php artisan test --filter=CreateBookingPersistsPreferenceTest`

- [ ] **Step 3 : Implémenter** — dans le `Booking::create([...])` de `CreateBookingAction::execute` (lignes ~120-177), ajouter :
```php
            'preferred_provider_user_id' => Arr::get($data, 'preferred_provider_user_id'),
            'provider_type_preference' => Arr::get($data, 'provider_type_preference', 'any'),
```
(Profiter du passage pour **nettoyer la double-assignation** `assignBestEmployee` lignes 263-270 → garder UN seul appel : supprimer les lignes 263-264 redondantes, garder le bloc 266-270.)

- [ ] **Step 4 : PASS** `php artisan test --filter=CreateBookingPersistsPreferenceTest`

- [ ] **Step 5 : Non-régression** `php artisan test --filter='CreateBooking|PrendreRendezVous|ZoneAware'` vert.

- [ ] **Step 6 : pint + commit**
```bash
vendor/bin/pint app/Services/Booking/CreateBookingAction.php tests/Feature/Relations/CreateBookingPersistsPreferenceTest.php
git add app/Services/Booking/CreateBookingAction.php tests/Feature/Relations/CreateBookingPersistsPreferenceTest.php
git commit -m "feat(relations): CreateBookingAction persists provider preference + preferred provider"
```

---

## Task 3 : `SmartDispatchService` honore le type

**Files :** Modify `app/Services/Booking/SmartDispatchService.php` ; Test `tests/Feature/Relations/DispatchHonorsTypePreferenceTest.php`

- [ ] **Step 1 : Test qui échoue**
```php
public function test_assign_best_employee_respects_provider_type_preference(): void
{
    // booking provider_type_preference='independent' dans une zone ; seede un indépendant éligible
    // (helper CreatesZoneAwareFixtures) + un company_worker éligible. assignBestEmployee doit
    // retourner l'indépendant, jamais le company_worker.
    // (Réutilise le helper de zone SP1 ; crée les 2 profils actifs+vérifiés taggés du métier.)
    $best = app(\App\Services\Booking\SmartDispatchService::class)->assignBestEmployee($booking);
    $this->assertSame($independent->id, $best?->id);
    $this->assertNotSame($companyWorker->id, $best?->id);
}
```

- [ ] **Step 2 : FAIL** (assignBestEmployee ignore la préférence). `php artisan test --filter=DispatchHonorsTypePreferenceTest`

- [ ] **Step 3 : Implémenter** — dans `SmartDispatchService::assignBestEmployee`, passer la préférence à l'éligibilité :
```php
        $providerType = $rdv->provider_type_preference ?: 'any';

        $employees = $this->availabilityService
            ->sortedEligibleEmployeesForZone((int) $rdv->service_zone_id, $providerType);
```
(Le reste — filtre dispo + score — inchangé.)

- [ ] **Step 4 : PASS** `php artisan test --filter=DispatchHonorsTypePreferenceTest`

- [ ] **Step 5 : Non-régression** `php artisan test --filter='SmartDispatch|Dispatch|Matching'` vert.

- [ ] **Step 6 : pint + commit**
```bash
vendor/bin/pint app/Services/Booking/SmartDispatchService.php tests/Feature/Relations/DispatchHonorsTypePreferenceTest.php
git add app/Services/Booking/SmartDispatchService.php tests/Feature/Relations/DispatchHonorsTypePreferenceTest.php
git commit -m "feat(relations): SmartDispatch honors provider_type_preference (eligibility type)"
```

---

## Task 4 : `PreferredProviderResolver` (dispo→assigné / indispo→créneaux)

**Files :** Create `app/Services/Booking/PreferredProviderResolver.php` ; Modify `SmartDispatchService::assignBestEmployee` ; Test `tests/Feature/Relations/PreferredProviderResolverTest.php`

- [ ] **Step 1 : Test qui échoue**
```php
public function test_preferred_provider_available_is_assigned(): void
{
    // booking.preferred_provider_user_id = X (éligible + dispo sur le créneau)
    $result = app(\App\Services\Booking\PreferredProviderResolver::class)->resolve($booking);
    $this->assertSame('assigned', $result['status']);
    $this->assertSame($X->id, $result['provider']->id);
}

public function test_preferred_provider_unavailable_returns_alternative_slots(): void
{
    // X éligible mais occupé sur le créneau (seede un RDV concurrent pour X)
    $result = app(\App\Services\Booking\PreferredProviderResolver::class)->resolve($booking);
    $this->assertSame('unavailable', $result['status']);
    $this->assertIsArray($result['alternative_slots']);
}
```

- [ ] **Step 2 : FAIL** (classe absente). `php artisan test --filter=PreferredProviderResolverTest`

- [ ] **Step 3 : Implémenter le resolver** — `app/Services/Booking/PreferredProviderResolver.php` :
```php
<?php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Models\User;

class PreferredProviderResolver
{
    public function __construct(protected EmployeeAvailabilityService $availability) {}

    /**
     * @return array{status:string, provider:?User, alternative_slots:list<array<string,mixed>>}
     */
    public function resolve(Booking $rdv): array
    {
        $none = ['status' => 'none', 'provider' => null, 'alternative_slots' => []];
        if (! $rdv->preferred_provider_user_id || ! $rdv->service_zone_id || ! $rdv->date || ! $rdv->heure) {
            return $none;
        }

        $provider = User::find($rdv->preferred_provider_user_id);
        if (! $provider) {
            return $none;
        }

        $duration = (int) ($rdv->duree_estimee ?: $rdv->duree ?: 90);
        $available = $this->availability->employeeIsAvailableForSlot(
            $provider->id,
            $rdv->date->format('Y-m-d'),
            substr((string) $rdv->heure, 0, 5),
            $rdv->serviceZone,
            $duration,
            $rdv->id,
        );

        if ($available) {
            return ['status' => 'assigned', 'provider' => $provider, 'alternative_slots' => []];
        }

        return [
            'status' => 'unavailable',
            'provider' => $provider,
            'alternative_slots' => $this->alternativeSlots($provider, $rdv, $duration),
        ];
    }

    /** @return list<array<string,mixed>> Créneaux dispo de X dans les 7 jours. */
    private function alternativeSlots(User $provider, Booking $rdv, int $duration): array
    {
        $slots = [];
        $start = $rdv->date->copy()->startOfDay();
        for ($d = 0; $d < 7 && count($slots) < 5; $d++) {
            $day = $start->copy()->addDays($d);
            foreach (['09:00', '11:00', '14:00', '16:00'] as $heure) {
                if (count($slots) >= 5) {
                    break;
                }
                if ($this->availability->employeeIsAvailableForSlot(
                    $provider->id, $day->format('Y-m-d'), $heure, $rdv->serviceZone, $duration, $rdv->id
                )) {
                    $slots[] = ['date' => $day->format('Y-m-d'), 'heure' => $heure];
                }
            }
        }

        return $slots;
    }
}
```

- [ ] **Step 4 : Câbler dans `assignBestEmployee`** — au début de `SmartDispatchService::assignBestEmployee`, avant l'auto-match : si un presta préféré est résolu « assigned », le retourner ; si « unavailable », NE PAS auto-matcher silencieusement (retourner `null` pour que l'appelant/UI propose les créneaux). Le repli « pressé » se fait côté UI en re-soumettant SANS `preferred_provider_user_id`.
```php
        if ($rdv->preferred_provider_user_id) {
            $pref = app(PreferredProviderResolver::class)->resolve($rdv);
            if ($pref['status'] === 'assigned') {
                return $pref['provider'];
            }
            if ($pref['status'] === 'unavailable') {
                return null; // l'UI proposera les créneaux de X ou le repli "pressé"
            }
        }
```
(Important : `CreateBookingAction` doit exposer le résultat du resolver à l'UI — voir Task 6/7 : la couche UI appelle `PreferredProviderResolver::resolve` AVANT de soumettre pour décider du parcours. Le `return null` ici protège contre une auto-assignation indésirable si la préférence indispo passe quand même par le dispatch.)

- [ ] **Step 5 : PASS** `php artisan test --filter=PreferredProviderResolverTest`

- [ ] **Step 6 : pint + commit**
```bash
vendor/bin/pint app/Services/Booking/PreferredProviderResolver.php app/Services/Booking/SmartDispatchService.php tests/Feature/Relations/PreferredProviderResolverTest.php
git add app/Services/Booking/PreferredProviderResolver.php app/Services/Booking/SmartDispatchService.php tests/Feature/Relations/PreferredProviderResolverTest.php
git commit -m "feat(relations): PreferredProviderResolver (assign if available, else alternative slots)"
```

---

## Task 5 : `ProviderSelectionResolver` (3 paliers + gating premium)

**Files :** Create `app/Services/Booking/ProviderSelectionResolver.php` ; Test `tests/Feature/Relations/ProviderSelectionResolverTest.php`

- [ ] **Step 1 : Test qui échoue**
```php
public function test_favorite_provider_is_allowed_for_any_client(): void
{
    // client non-premium ; X est un booking_favorite du client → autorisé
    $out = app(\App\Services\Booking\ProviderSelectionResolver::class)
        ->resolve($client, ['provider_type_preference' => 'any', 'preferred_provider_user_id' => $favoriteProviderId]);
    $this->assertSame($favoriteProviderId, $out['preferred_provider_user_id']);
}

public function test_choosing_new_provider_requires_premium(): void
{
    // client NON-premium choisit un presta NON-favori → exception/refus
    $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
    app(\App\Services\Booking\ProviderSelectionResolver::class)
        ->resolve($nonPremiumClient, ['provider_type_preference' => 'any', 'preferred_provider_user_id' => $strangerProviderId]);
}

public function test_premium_client_can_choose_new_provider(): void
{
    $out = app(\App\Services\Booking\ProviderSelectionResolver::class)
        ->resolve($premiumClient, ['provider_type_preference' => 'any', 'preferred_provider_user_id' => $strangerProviderId]);
    $this->assertSame($strangerProviderId, $out['preferred_provider_user_id']);
}
```

- [ ] **Step 2 : FAIL.** `php artisan test --filter=ProviderSelectionResolverTest`

- [ ] **Step 3 : Implémenter** — `app/Services/Booking/ProviderSelectionResolver.php` :
```php
<?php

namespace App\Services\Booking;

use App\Models\BookingFavorite;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class ProviderSelectionResolver
{
    private const TYPES = ['independent', 'company', 'any'];

    /**
     * @param  array{provider_type_preference?:string, preferred_provider_user_id?:int|null}  $input
     * @return array{provider_type_preference:string, preferred_provider_user_id:?int}
     */
    public function resolve(User $client, array $input): array
    {
        $type = in_array($input['provider_type_preference'] ?? 'any', self::TYPES, true)
            ? $input['provider_type_preference']
            : 'any';

        $preferredId = $input['preferred_provider_user_id'] ?? null;

        if ($preferredId === null) {
            return ['provider_type_preference' => $type, 'preferred_provider_user_id' => null]; // palier auto
        }

        // palier favori (autorisé pour tous) : le presta est un favori du client ?
        $isFavorite = BookingFavorite::query()
            ->where('client_id', $client->id)
            ->where('preferred_provider_user_id', $preferredId)
            ->exists();

        // palier premium pick (découverte d'un NOUVEAU presta) : gaté
        if (! $isFavorite && ! ($client->customerProfile?->isPremium() ?? false)) {
            throw new AuthorizationException('Le choix d’un nouveau prestataire est réservé au pack Premium.');
        }

        return ['provider_type_preference' => $type, 'preferred_provider_user_id' => (int) $preferredId];
    }
}
```
(Vérifie le nom RÉEL de la colonne sur `booking_favorites` qui porte le presta favori — lis la migration/le modèle `BookingFavorite` ; c'est probablement `preferred_provider_user_id` d'après `BookingFavoriteService:57`. Adapte si différent.)

- [ ] **Step 4 : PASS** `php artisan test --filter=ProviderSelectionResolverTest`

- [ ] **Step 5 : pint + commit**
```bash
vendor/bin/pint app/Services/Booking/ProviderSelectionResolver.php tests/Feature/Relations/ProviderSelectionResolverTest.php
git add app/Services/Booking/ProviderSelectionResolver.php tests/Feature/Relations/ProviderSelectionResolverTest.php
git commit -m "feat(relations): ProviderSelectionResolver (3 tiers + premium gating for new providers)"
```

---

## Task 6 : UI Web — `PrendreRendezVous`

**Files :** Modify `app/Livewire/Client/PrendreRendezVous.php` + sa vue Blade (cherche `resources/views/livewire/client/prendre-rendez-vous*.blade.php`) ; Test `tests/Feature/Relations/PrendreRendezVousSelectionTest.php`

**Contrat d'intégration :** ajouter au composant 3 propriétés publiques — `providerTypePreference` (string, défaut `'any'`), `preferredProviderUserId` (?int), et un flag `wantsSpecificProvider` (bool). Avant la soumission, le composant appelle `ProviderSelectionResolver::resolve($client, [...])` (gating) puis passe `provider_type_preference` + `preferred_provider_user_id` dans le `$data` du `bookingCreator()->execute(...)`. Pour le flux indispo, le composant appelle `PreferredProviderResolver::resolve($draftBooking)` (ou expose les créneaux) et affiche soit l'assignation, soit les créneaux de X + un bouton « je suis pressé » (qui vide `preferredProviderUserId` et re-soumet → auto-match).

- [ ] **Step 1 : Lire** le composant `PrendreRendezVous.php` (structure des steps, la méthode qui construit `$data` et appelle `execute` ~ligne 228) + sa vue.
- [ ] **Step 2 : Test composant (Livewire)** — `tests/Feature/Relations/PrendreRendezVousSelectionTest.php` :
  - un client non-premium qui set `providerTypePreference='independent'` et soumet → la booking créée a `provider_type_preference='independent'`, `preferred_provider_user_id=null`.
  - un client qui re-réserve un favori → `preferred_provider_user_id` = le favori.
  - un client non-premium qui tente de choisir un presta non-favori → erreur de validation/gating (pas de booking créée OU message premium).
  - un client premium qui choisit un nouveau presta → booking avec ce `preferred_provider_user_id`.
  Utilise `Livewire::test(PrendreRendezVous::class)->set(...)->call('submit'...)` (adapte au nom réel de la méthode de soumission).
- [ ] **Step 3 : Implémenter** — ajouter les propriétés + le passage dans `$data` + l'appel au `ProviderSelectionResolver` (gating) + le flux indispo (créneaux de X / bouton pressé). Ajouter à la vue : le sélecteur de type (3 boutons radio), la section « Mes favoris » (`BookingFavorite::where('client_id', auth id)`), et — `@if($isPremium)` — le bouton « Choisir un prestataire » embarquant/ouvrant `BrowseProviders` (émet un event avec le providerId sélectionné → set `preferredProviderUserId`). Pour un non-premium, afficher l'upsell à la place.
- [ ] **Step 4 : PASS** `php artisan test --filter=PrendreRendezVousSelectionTest`
- [ ] **Step 5 : Non-régression** `php artisan test --filter='PrendreRendezVous|Booking'` vert ; vérifier la vue rend sans erreur.
- [ ] **Step 6 : pint + commit**
```bash
vendor/bin/pint app/Livewire/Client/PrendreRendezVous.php resources/views/livewire/client tests/Feature/Relations/PrendreRendezVousSelectionTest.php
git add app/Livewire/Client/PrendreRendezVous.php resources/views/livewire/client tests/Feature/Relations/PrendreRendezVousSelectionTest.php
git commit -m "feat(relations): web booking form — type selector + favorites + premium provider pick + unavailable flow"
```

---

## Task 7 : UI Mobile — wizard `BookingStep*`

**Files :** Modify `mobile/client/src/screens/booking/*` (ajouter une étape/section « Prestataire ») + `mobile/client/src/booking/BookingProvider.tsx` (état du wizard) + l'appel API de création ; Test `mobile/client/src/screens/booking/__tests__/*` (Jest)

**Contrat d'intégration :** le wizard doit collecter `providerTypePreference` (independent/company/any, défaut any), permettre le re-book d'un favori et — si premium — la recherche d'un prestataire (réutiliser l'écran/endpoint de recherche client), puis envoyer `provider_type_preference` + `preferred_provider_user_id` dans le payload de création de réservation (l'endpoint API qui appelle `CreateBookingAction`). Le flux indispo (créneaux de X / bouton « pressé ») doit être géré côté mobile aussi (parité).

- [ ] **Step 1 : Lire** le wizard (`BookingStep1Service`…`BookingStep4Scheduling`, `BookingProvider.tsx`, `BookingNavigator.tsx`) + l'endpoint API de création de booking côté mobile (cherche dans `mobile/client/src` les appels `apiClient.post('/api/client/bookings'…)` ou équivalent ; vérifie quels champs il envoie). Repérer où ajouter une étape « Prestataire ».
- [ ] **Step 2 : Test Jest** — un test du nouveau composant « Prestataire » : sélection du type met à jour l'état du wizard ; le payload de création inclut `provider_type_preference` ; le bouton premium n'apparaît que si l'utilisateur est premium (mocker le flag premium) ; le re-book favori pose `preferred_provider_user_id`.
- [ ] **Step 3 : Implémenter** — ajouter le composant/étape « Prestataire » (sélecteur de type, liste favoris, bouton recherche premium), brancher sur `BookingProvider` (état), inclure les 2 champs dans le payload API. Gérer le retour « presta indispo » (afficher les créneaux de X / bouton pressé qui vide le presta et renvoie).
- [ ] **Step 4 : Vérifs mobile**
```bash
cd mobile/client && npx tsc --noEmit && npx jest src/screens/booking --silent ; cd ../..
```
Typecheck + Jest verts.
- [ ] **Step 5 : commit**
```bash
git add mobile/client/src/screens/booking mobile/client/src/booking
git commit -m "feat(relations): mobile booking wizard — provider step (type + favorites + premium pick + unavailable)"
```

---

## Task 8 : `BookingHub` (société) sur l'action canonique

**Files :** Modify `app/Livewire/ClientCompany/BookingHub.php::submitBooking()` ; Test `tests/Feature/Relations/BookingHubCanonicalTest.php`

**Contexte :** `submitBooking()` fait aujourd'hui un `Booking::create([...])` ad-hoc qui PERD le `service_catalog_id` et n'a ni zone/prix/mission. Il faut router via `CreateBookingAction`. Le défi : le portail société raisonne en `selectedSiteId` + `selectedTradeId` ; il faut résoudre `postal/zone/catalog/rule/assignedEmployee` depuis le site + le métier. **Réutilise la logique de résolution déjà présente** (lis comment `PrendreRendezVous`/`HandlesBookingCreation` résout zone/catalog depuis une adresse/un métier — il y a un service de couverture `CountryMarketResolver`/résolution de zone). Le site porte une adresse (code postal) → résoudre la zone ; le métier (`selectedTradeId`) → résoudre un `service_catalog` du métier.

- [ ] **Step 1 : Lire** `BookingHub::submitBooking` + les helpers de résolution zone/catalog réutilisables (concern `HandlesBookingCreation`, `BookingSnapshotFactory`, le résolveur de zone). Identifier comment obtenir `postal/zone/catalog/rule/assignedEmployee` depuis un `OrganizationSite` + un `trade_id`.
- [ ] **Step 2 : Test** — `tests/Feature/Relations/BookingHubCanonicalTest.php` : une société cliente soumet via `BookingHub` (site + métier + date) → la booking créée a un `service_catalog_id` NON NULL (du métier), un `customer_organization_id` = l'org, et est passée par `CreateBookingAction` (zone/pricing snapshot présents). Plus : si la société est premium et choisit un presta, `preferred_provider_user_id` posé ; sinon auto-match du type.
- [ ] **Step 3 : Implémenter** — remplacer le `Booking::create([...])` ad-hoc par : résoudre postal/zone/catalog/rule/assignedEmployee depuis le site+métier (helpers réutilisés), construire `$data` (incluant `customer_organization_id`, `organization_site_id`, `service_zone_id`, `provider_type_preference`, `preferred_provider_user_id` via `ProviderSelectionResolver`), puis `app(CreateBookingAction::class)->execute(...)`. Conserver l'event `booking-created` + le reset du wizard.
- [ ] **Step 4 : PASS** `php artisan test --filter=BookingHubCanonicalTest`
- [ ] **Step 5 : Non-régression** `php artisan test --filter='BookingHub|ClientCompany|Entreprise'` vert.
- [ ] **Step 6 : pint + commit**
```bash
vendor/bin/pint app/Livewire/ClientCompany/BookingHub.php tests/Feature/Relations/BookingHubCanonicalTest.php
git add app/Livewire/ClientCompany/BookingHub.php tests/Feature/Relations/BookingHubCanonicalTest.php
git commit -m "fix(relations): BookingHub routes through CreateBookingAction (fixes lost service_catalog_id) + selection"
```

---

## Task 9 : Vérification complète + DoD

**Files :** aucun (vérif + éventuel pint).

- [ ] **Step 1 : Gates backend**
```bash
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=1G --no-progress
```
Suite verte ; **PHPStan FULL** `[OK]` (leçon SP1 : run complet ; corriger les nouveaux services/relations avec de vraies annotations — `@param`/`@return` génériques sur Builder/relations, `@return array{...}` sur les resolvers — zéro suppression) ; Pint clean. Corriger toute fixture cassée par la nouvelle colonne/le gating (sans affaiblir les assertions).

- [ ] **Step 2 : Gates mobile**
```bash
cd mobile/client && npx tsc --noEmit && npx jest --silent ; cd ../..
```
Typecheck + Jest verts.

- [ ] **Step 3 : Confirmer le DoD** (spec) : colonne + persistance (T1-2) ; SmartDispatch type-aware (T3) ; PreferredProviderResolver (T4) ; ProviderSelectionResolver + gating (T5) ; UI web (T6) ; UI mobile (T7) ; BookingHub canonique (T8). Parité : chaque palier testé web ET mobile.

- [ ] **Step 4 : commit (si pint a reformaté)**
```bash
git add -A
git commit -m "chore(relations): pint formatting on SP2 files"
```

---

## Self-review (déjà appliqué)

- **Couverture spec :** donnée → T1 ; CreateBookingAction → T2 ; SmartDispatch type → T3 ; PreferredProviderResolver → T4 ; ProviderSelectionResolver+gating → T5 ; UI web → T6 ; UI mobile → T7 ; BookingHub → T8 ; vérif → T9. Le comportement « presta indispo → créneaux/repli pressé » est dans T4 (service) + exposé en UI T6/T7.
- **Réutilisations :** `booking_favorites`, `BrowseProviders`, `EmployeeAvailabilityService` (SP1), `CustomerProfile::isPremium`, le wizard mobile — pas de réécriture.
- **Points « lire+adapter » assumés :** les arguments réels de `CreateBookingAction::execute` (T2), la colonne favori sur `booking_favorites` (T5), la structure des composants UI web/mobile (T6/T7), et la résolution zone/catalog depuis un site (T8) — ce sont des intégrations sur du code existant que l'implémenteur lit au moment ; tout le code des nouveaux services (T1, T3, T4, T5) est complet.
- **Cohérence noms/types :** `provider_type_preference` (string, défaut 'any'), `preferred_provider_user_id` (existant), `ProviderSelectionResolver::resolve(User,$array):array{provider_type_preference,preferred_provider_user_id}`, `PreferredProviderResolver::resolve(Booking):array{status,provider,alternative_slots}`, `sortedEligibleEmployeesForZone($zoneId,$providerType)` (SP1) — cohérents entre tasks.
- **Hors scope :** société-entité Bolt-like (SP3), contrats B2B (SP4) — non touchés.
