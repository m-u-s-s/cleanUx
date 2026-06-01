# SP3 — Société prestataire comme entité réservable (Bolt-like) — Plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permettre à un client premium de choisir une SOCIÉTÉ prestataire précise (selon sa note) dans sa zone ; la société est attribuée à la réservation, le système auto-suggère le meilleur worker de la société, et la société peut réassigner — sur web + mobile.

**Architecture:** La société gagne une note agrégée de ses workers. Un `EligibleCompaniesResolver` liste les sociétés réservables. L'éligibilité SP1 gagne un filtre org optionnel ; quand `booking.assigned_provider_organization_id` est posé, le matcher (web + mobile) restreint les candidats aux workers de l'org et auto-suggère le meilleur. `ProviderSelectionResolver` (SP2) est étendu pour valider+gater le choix d'une société. UI : un browse-sociétés web (embarqué dans le picker SP2) + mobile (écran dans la stack booking).

**Tech Stack:** Laravel 10 (Eloquent, Livewire, Artisan), Expo/RN (TS, Jest), PHPStan full, Pint.

**Spec :** `docs/superpowers/specs/2026-06-01-relations-sp3-company-as-bookable-entity-design.md`
**Branche :** `feat/relations-sp3-company-entity` (off `main`).

---

## Vérités terrain (vérifiées)

- `OrganizationAccount` : `$fillable` n'a PAS `rating_avg`/`rating_count` (à ajouter). Relation `users(): HasMany(User)` (ligne 71) = les utilisateurs de l'org (dont ses workers). `type` (string) ; `App\Enums\OrganizationType::PROVIDER_COMPANY` = `'provider_company'`.
- `ProviderProfile` : `rating_avg` (decimal:2), `rating_count` (int), `organization_account_id`. `App\Services\Rating\RatingAggregationService` (ligne ~90) écrit `rating_avg`/`rating_count` sur un `ProviderProfile` (point d'ancrage du hook soft).
- `EmployeeAvailabilityService` (SP1) : `eligibleEmployeesQuery(?int $zoneId=null, string $providerType='any'): Builder` (l.15), `sortedEligibleEmployeesForZone(int $zoneId, string $providerType='any'): Collection` (l.67), `applyProviderEligibility(Builder $query, string $providerType='any'): Builder` (l.176) avec `whereHas('providerProfile', fn => $q->whereIn('provider_type', …)->where('status','active')->where('verification_status','verified'))`.
- `SmartDispatchService::assignBestEmployee(Booking $rdv): ?User` (web) : appelle `sortedEligibleEmployeesForZone($zoneId, $providerType)` + un bloc preferred (SP2) ; déjà : `$providerType = $rdv->provider_type_preference ?: 'any';`.
- `AiDispatchService::rankEmployees(Booking)` (mobile) : appelle `sortedEligibleEmployeesForZone($zoneId, $providerType)` (SP2).
- `ProviderSelectionResolver::resolve(User $client, array $input): array{provider_type_preference, preferred_provider_user_id}` (SP2) — gate premium = `$client->customerProfile instanceof CustomerProfile && ->isPremium()`. À étendre.
- `PreferredProviderResolver::resolve(Booking): array{status,provider,alternative_slots}` (SP2) — pattern pour l'indispo société.
- `CreateBookingAction::execute(...)` (web/société) + `CreateBookingFromApiAction::execute(...)` (mobile) persistent déjà `provider_type_preference`/`preferred_provider_user_id` ; `bookings.assigned_provider_organization_id` existe (SP1) mais n'est pas encore posé par ces actions.
- UI patterns SP2 à réutiliser : web `app/Livewire/Client/BrowseProviders.php` (`selectionMode` + event) embarqué dans `resources/views/livewire/client/booking/scheduling/provider-selection.blade.php` ; mobile `mobile/client/src/screens/booking/BookingProviderSearchScreen.tsx` + `BookingProvider.tsx` (état) + `BookingNavigator.tsx`. `is_premium` exposé sur `/auth/me`.
- `ProviderCompany/DispatchCenter` (existant) : liste `Mission::where('provider_organization_id',$orgId)` + `confirmAssign` (réassignation worker) — réutilisé tel quel.

---

## File structure

- Create `database/migrations/2026_06_03_000001_add_rating_to_organization_accounts.php` ; Modify `app/Models/OrganizationAccount.php` (Task 1)
- Create `app/Services/Rating/OrganizationRatingAggregator.php` ; Modify `app/Services/Rating/RatingAggregationService.php` (hook) ; Create `app/Console/Commands/RecomputeOrganizationRatings.php` (Task 1)
- Modify `app/Services/Booking/EmployeeAvailabilityService.php` (filtre org) (Task 2)
- Create `app/Services/Booking/EligibleCompaniesResolver.php` (Task 3)
- Modify `app/Services/Booking/SmartDispatchService.php` + `app/Services/Dispatch/AiDispatchService.php` (scope org) (Task 4)
- Create `app/Services/Booking/PreferredCompanyResolver.php` (Task 5)
- Modify `app/Services/Booking/ProviderSelectionResolver.php` + `CreateBookingAction.php` + `CreateBookingFromApiAction.php` (Task 6)
- Create `app/Http/Controllers/Api/Client/CompanyDirectoryController.php` + route (Task 7)
- Modify `app/Livewire/Client/PrendreRendezVous.php` (+ vue) ; Create `app/Livewire/Client/BrowseCompanies.php` (+ vue) (Task 8)
- Create `mobile/client/src/screens/booking/BookingCompanySearchScreen.tsx` ; Modify mobile booking state/nav (Task 9)
- Task 10 : vérification complète + DoD

---

## Task 1 : Note société (colonnes + agrégateur + commande + hook)

**Files :** migration ; `app/Models/OrganizationAccount.php` ; `app/Services/Rating/OrganizationRatingAggregator.php` ; `app/Console/Commands/RecomputeOrganizationRatings.php` ; `app/Services/Rating/RatingAggregationService.php` ; Test `tests/Feature/Relations/OrganizationRatingTest.php`

- [ ] **Step 1 : Test qui échoue**
```php
<?php

namespace Tests\Feature\Relations;

use App\Enums\ProviderType;
use App\Models\OrganizationAccount;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Rating\OrganizationRatingAggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrganizationRatingTest extends TestCase
{
    use RefreshDatabase;

    public function test_org_rating_is_weighted_average_of_worker_ratings(): void
    {
        $this->assertTrue(Schema::hasColumn('organization_accounts', 'rating_avg'));
        $this->assertTrue(Schema::hasColumn('organization_accounts', 'rating_count'));

        $org = OrganizationAccount::factory()->create();
        // worker A : 4.0 sur 10 avis ; worker B : 5.0 sur 30 avis → moyenne pondérée = (40+150)/40 = 4.75
        foreach ([[4.0, 10], [5.0, 30]] as [$avg, $count]) {
            $u = User::factory()->create(['organization_account_id' => $org->id]);
            ProviderProfile::create([
                'user_id' => $u->id, 'organization_account_id' => $org->id,
                'provider_type' => ProviderType::COMPANY_WORKER->value, 'status' => 'active',
                'verification_status' => 'verified', 'rating_avg' => $avg, 'rating_count' => $count,
            ]);
        }

        app(OrganizationRatingAggregator::class)->recompute($org);

        $this->assertEqualsWithDelta(4.75, (float) $org->fresh()->rating_avg, 0.01);
        $this->assertSame(40, (int) $org->fresh()->rating_count);
    }
}
```

- [ ] **Step 2 : FAIL.** `php artisan test --filter=OrganizationRatingTest`

- [ ] **Step 3 : Migration idempotente** — `database/migrations/2026_06_03_000001_add_rating_to_organization_accounts.php` :
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('organization_accounts', 'rating_avg')) {
                $table->decimal('rating_avg', 3, 2)->nullable()->after('metadata');
            }
            if (! Schema::hasColumn('organization_accounts', 'rating_count')) {
                $table->unsignedInteger('rating_count')->default(0)->after('rating_avg');
            }
        });
    }

    public function down(): void
    {
        Schema::table('organization_accounts', function (Blueprint $table) {
            foreach (['rating_avg', 'rating_count'] as $c) {
                if (Schema::hasColumn('organization_accounts', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
```

- [ ] **Step 4 : Model** — dans `app/Models/OrganizationAccount.php`, ajoute `'rating_avg'`, `'rating_count'` au `$fillable` + au `$casts` (`'rating_avg' => 'decimal:2'`, `'rating_count' => 'integer'`).

- [ ] **Step 5 : Agrégateur** — `app/Services/Rating/OrganizationRatingAggregator.php` :
```php
<?php

namespace App\Services\Rating;

use App\Models\OrganizationAccount;
use Illuminate\Support\Facades\DB;

class OrganizationRatingAggregator
{
    public function recompute(OrganizationAccount $org): void
    {
        // Moyenne pondérée des notes des workers (provider_profiles) de l'org.
        $row = DB::table('provider_profiles')
            ->where('organization_account_id', $org->id)
            ->whereNotNull('rating_avg')
            ->where('rating_count', '>', 0)
            ->selectRaw('SUM(rating_avg * rating_count) AS weighted, SUM(rating_count) AS total')
            ->first();

        $total = (int) ($row->total ?? 0);
        $avg = $total > 0 ? round(((float) $row->weighted) / $total, 2) : null;

        $org->forceFill(['rating_avg' => $avg, 'rating_count' => $total])->save();
    }

    public function recomputeForUser(int $userId): void
    {
        $orgId = DB::table('provider_profiles')->where('user_id', $userId)->value('organization_account_id');
        if ($orgId) {
            $org = OrganizationAccount::find($orgId);
            if ($org) {
                $this->recompute($org);
            }
        }
    }
}
```

- [ ] **Step 6 : PASS** `php artisan test --filter=OrganizationRatingTest`

- [ ] **Step 7 : Commande de recompute** — `app/Console/Commands/RecomputeOrganizationRatings.php` (signature `organizations:recompute-ratings`) : itère les `OrganizationAccount` de type `provider_company` (et `hybrid`) et appelle `OrganizationRatingAggregator::recompute`. Output un résumé (`X orgs recomputed`). Mets un test léger qui appelle `$this->artisan('organizations:recompute-ratings')->assertExitCode(0)`.

- [ ] **Step 8 : Hook soft** — dans `app/Services/Rating/RatingAggregationService.php`, là où une `ProviderProfile` voit sa `rating_avg`/`rating_count` recalculée (~ligne 90), ajoute APRÈS le save, en soft-fail :
```php
        try {
            app(\App\Services\Rating\OrganizationRatingAggregator::class)->recomputeForUser((int) $profile->user_id);
        } catch (\Throwable $e) {
            report($e);
        }
```
(Adapte `$profile->user_id` à la variable réelle du profil dans cette méthode.)

- [ ] **Step 9 : Non-régression + pint + commit**
```bash
php artisan test --filter='OrganizationRating|RatingAggregation'
vendor/bin/pint database/migrations app/Models/OrganizationAccount.php app/Services/Rating app/Console/Commands/RecomputeOrganizationRatings.php tests/Feature/Relations/OrganizationRatingTest.php
git add database/migrations app/Models/OrganizationAccount.php app/Services/Rating app/Console/Commands/RecomputeOrganizationRatings.php tests/Feature/Relations/OrganizationRatingTest.php
git commit -m "feat(relations): organization rating aggregated from workers + recompute command + soft hook"
```

---

## Task 2 : Filtre org optionnel sur l'éligibilité

**Files :** `app/Services/Booking/EmployeeAvailabilityService.php` ; Test `tests/Feature/Relations/EligibilityOrgFilterTest.php`

- [ ] **Step 1 : Test qui échoue**
```php
public function test_eligibility_can_be_scoped_to_an_organization(): void
{
    // 2 company_workers éligibles (zone, métier, actif/vérifié) dans 2 orgs différentes ;
    // eligibleEmployeesQuery(null, 'company', $orgA->id) ne renvoie QUE le worker de l'org A.
    $ids = app(\App\Services\Booking\EmployeeAvailabilityService::class)
        ->eligibleEmployeesQuery(null, 'company', $orgA->id)->pluck('id');
    $this->assertTrue($ids->contains($workerA->id));
    $this->assertFalse($ids->contains($workerB->id));
}
```
(Monte 2 orgs + 2 workers company via le pattern des tests SP1/SP2 — `ProviderProfile` actif/vérifié + `organization_account_id`.)

- [ ] **Step 2 : FAIL** (le 3e param n'existe pas). `php artisan test --filter=EligibilityOrgFilterTest`

- [ ] **Step 3 : Implémenter** — dans `EmployeeAvailabilityService` :
  - `eligibleEmployeesQuery(?int $zoneId = null, string $providerType = 'any', ?int $organizationId = null): Builder` — passe `$organizationId` à `applyProviderEligibility`.
  - `applyProviderEligibility(Builder $query, string $providerType = 'any', ?int $organizationId = null): Builder` — dans le `whereHas('providerProfile', …)`, ajoute `if ($organizationId !== null) { $q->where('organization_account_id', $organizationId); }`.
  - `sortedEligibleEmployeesForZone(int $zoneId, string $providerType = 'any', ?int $organizationId = null): Collection` — passe `$organizationId` à `eligibleEmployeesQuery`.
  Mets à jour les `@param/@return` génériques (`Builder<User>`) pour PHPStan.

- [ ] **Step 4 : PASS** `php artisan test --filter=EligibilityOrgFilterTest`

- [ ] **Step 5 : Non-régression** `php artisan test --filter='Eligibility|Dispatch|Matching|ZoneAware'` vert (le défaut `null` ne change rien).

- [ ] **Step 6 : pint + commit**
```bash
vendor/bin/pint app/Services/Booking/EmployeeAvailabilityService.php tests/Feature/Relations/EligibilityOrgFilterTest.php
git add app/Services/Booking/EmployeeAvailabilityService.php tests/Feature/Relations/EligibilityOrgFilterTest.php
git commit -m "feat(relations): optional organization filter on provider eligibility"
```

---

## Task 3 : `EligibleCompaniesResolver`

**Files :** Create `app/Services/Booking/EligibleCompaniesResolver.php` ; Test `tests/Feature/Relations/EligibleCompaniesResolverTest.php`

- [ ] **Step 1 : Test qui échoue**
```php
public function test_returns_provider_companies_with_at_least_one_eligible_worker_sorted_by_rating(): void
{
    // booking dans une zone + métier ; orgA (note 4.8) a 1 worker éligible, orgB (note 4.2) a 1 worker
    // éligible, orgC a 0 worker éligible (mauvais métier). Résultat = [orgA, orgB] (tri note desc), pas orgC.
    $companies = app(\App\Services\Booking\EligibleCompaniesResolver::class)->forBooking($booking);
    $ids = $companies->pluck('id');
    $this->assertSame([$orgA->id, $orgB->id], $ids->all());
    $this->assertFalse($ids->contains($orgC->id));
}
```

- [ ] **Step 2 : FAIL.** `php artisan test --filter=EligibleCompaniesResolverTest`

- [ ] **Step 3 : Implémenter** — `app/Services/Booking/EligibleCompaniesResolver.php` :
```php
<?php

namespace App\Services\Booking;

use App\Enums\OrganizationType;
use App\Models\Booking;
use App\Models\OrganizationAccount;
use App\Models\User;
use Illuminate\Support\Collection;

class EligibleCompaniesResolver
{
    public function __construct(protected EmployeeAvailabilityService $availability) {}

    /**
     * @return Collection<int, OrganizationAccount>
     */
    public function forBooking(Booking $rdv): Collection
    {
        if (! $rdv->service_zone_id) {
            return collect();
        }

        // Workers company éligibles (type 'company', zone, actif/vérifié) ; on garde le métier souple
        // comme le matching (le filtre métier strict est appliqué côté matcher ; ici on liste les
        // sociétés ayant des workers couvrant la zone/le métier).
        $tradeId = $rdv->serviceCatalog?->trade_id;

        $workers = $this->availability
            ->sortedEligibleEmployeesForZone((int) $rdv->service_zone_id, 'company')
            ->filter(function (User $w) use ($tradeId) {
                if (! $tradeId) {
                    return true;
                }
                $w->loadMissing('trades:id');

                return $w->trades->contains('id', $tradeId);
            });

        $orgIds = $workers
            ->pluck('providerProfile.organization_account_id')
            ->filter()
            ->unique()
            ->values();

        if ($orgIds->isEmpty()) {
            return collect();
        }

        return OrganizationAccount::query()
            ->whereIn('id', $orgIds)
            ->where('type', OrganizationType::PROVIDER_COMPANY->value)
            ->orderByDesc('rating_avg')
            ->orderBy('name')
            ->get();
    }

    public function isEligible(Booking $rdv, int $organizationId): bool
    {
        return $this->forBooking($rdv)->contains('id', $organizationId);
    }
}
```
(Vérifie l'accès `providerProfile.organization_account_id` sur un `User` chargé — `loadMissing('providerProfile')` si besoin avant le `pluck`.)

- [ ] **Step 4 : PASS** `php artisan test --filter=EligibleCompaniesResolverTest`

- [ ] **Step 5 : pint + commit**
```bash
vendor/bin/pint app/Services/Booking/EligibleCompaniesResolver.php tests/Feature/Relations/EligibleCompaniesResolverTest.php
git add app/Services/Booking/EligibleCompaniesResolver.php tests/Feature/Relations/EligibleCompaniesResolverTest.php
git commit -m "feat(relations): EligibleCompaniesResolver (provider companies with an eligible worker, by rating)"
```

---

## Task 4 : Matching scopé société (auto-suggestion)

**Files :** `app/Services/Booking/SmartDispatchService.php` + `app/Services/Dispatch/AiDispatchService.php` ; Test `tests/Feature/Relations/CompanyScopedDispatchTest.php`

- [ ] **Step 1 : Test qui échoue**
```php
public function test_dispatch_scopes_to_assigned_company_and_picks_its_worker(): void
{
    // booking.assigned_provider_organization_id = orgA ; orgA a un worker éligible+dispo, orgB aussi.
    // SmartDispatchService::assignBestEmployee → un worker DE orgA, jamais celui d'orgB.
    $best = app(\App\Services\Booking\SmartDispatchService::class)->assignBestEmployee($booking->fresh());
    $this->assertSame($orgA->id, $best?->providerProfile?->organization_account_id);
    // idem côté mobile :
    $rankedIds = app(\App\Services\Dispatch\AiDispatchService::class)->rankEmployees($booking->fresh())->pluck('id');
    $this->assertTrue($rankedIds->contains($workerA->id));
    $this->assertFalse($rankedIds->contains($workerB->id));
}
```

- [ ] **Step 2 : FAIL.** `php artisan test --filter=CompanyScopedDispatchTest`

- [ ] **Step 3 : Implémenter** — dans `SmartDispatchService::assignBestEmployee` ET `AiDispatchService::rankEmployees`, passe l'org au matcher :
```php
        $providerType = $rdv->provider_type_preference ?: 'any';
        $organizationId = $rdv->assigned_provider_organization_id;   // ?int

        $employees = $this->availability
            ->sortedEligibleEmployeesForZone((int) $rdv->service_zone_id, $providerType, $organizationId);
```
(Pour `SmartDispatchService`, garde le bloc preferred SP2 AVANT ; si `assigned_provider_organization_id` est posé sans `preferred_provider_user_id`, on scope sur l'org et on auto-suggère le meilleur — le `provider_organization_id` de la mission est ensuite dérivé du worker, déjà fait en SP1. Vérifie que `mission.provider_organization_id` finit bien = l'org choisie.)

- [ ] **Step 4 : PASS** `php artisan test --filter=CompanyScopedDispatchTest`

- [ ] **Step 5 : Non-régression** `php artisan test --filter='SmartDispatch|AiDispatch|Dispatch|Matching'` vert.

- [ ] **Step 6 : pint + commit**
```bash
vendor/bin/pint app/Services/Booking/SmartDispatchService.php app/Services/Dispatch/AiDispatchService.php tests/Feature/Relations/CompanyScopedDispatchTest.php
git add app/Services/Booking/SmartDispatchService.php app/Services/Dispatch/AiDispatchService.php tests/Feature/Relations/CompanyScopedDispatchTest.php
git commit -m "feat(relations): dispatch scopes to assigned company + auto-suggests its best worker (web + mobile)"
```

---

## Task 5 : `PreferredCompanyResolver` (indispo société)

**Files :** Create `app/Services/Booking/PreferredCompanyResolver.php` ; Test `tests/Feature/Relations/PreferredCompanyResolverTest.php`

- [ ] **Step 1 : Test qui échoue** — calque sur `PreferredProviderResolverTest` (SP2) :
```php
public function test_company_with_available_worker_is_assignable(): void
{
    // booking.assigned_provider_organization_id = orgA, orgA a un worker dispo → status 'assigned', provider = ce worker
    $r = app(\App\Services\Booking\PreferredCompanyResolver::class)->resolve($booking->fresh());
    $this->assertSame('assigned', $r['status']);
    $this->assertSame($orgA->id, $r['provider']?->providerProfile?->organization_account_id);
}

public function test_company_without_available_worker_returns_alternative_slots(): void
{
    // orgA worker occupé sur le créneau → status 'unavailable' + alternative_slots non vide
    $r = app(\App\Services\Booking\PreferredCompanyResolver::class)->resolve($booking->fresh());
    $this->assertSame('unavailable', $r['status']);
    $this->assertIsArray($r['alternative_slots']);
}
```

- [ ] **Step 2 : FAIL.** `php artisan test --filter=PreferredCompanyResolverTest`

- [ ] **Step 3 : Implémenter** — `app/Services/Booking/PreferredCompanyResolver.php` (réutilise `EmployeeAvailabilityService` scopé org + le pattern de créneaux de `PreferredProviderResolver`) :
```php
<?php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Models\ServiceZone;
use App\Models\User;

class PreferredCompanyResolver
{
    public function __construct(protected EmployeeAvailabilityService $availability) {}

    /**
     * @return array{status:string, provider:?User, alternative_slots:list<array{date:string, heure:string}>}
     */
    public function resolve(Booking $rdv): array
    {
        $none = ['status' => 'none', 'provider' => null, 'alternative_slots' => []];
        $orgId = $rdv->assigned_provider_organization_id;
        if (! $orgId || ! $rdv->service_zone_id || ! $rdv->date || ! $rdv->heure) {
            return $none;
        }

        $type = $rdv->provider_type_preference ?: 'company';
        $duration = (int) ($rdv->duree_estimee ?: $rdv->duree ?: 90);
        $zone = $rdv->serviceZone instanceof ServiceZone ? $rdv->serviceZone : null;
        $date = $rdv->date->format('Y-m-d');
        $heure = substr((string) $rdv->heure, 0, 5);

        $candidates = $this->availability->sortedEligibleEmployeesForZone((int) $rdv->service_zone_id, $type, (int) $orgId);

        $available = $candidates->first(fn (User $w) => $this->availability->employeeIsAvailableForSlot(
            $w->id, $date, $heure, $zone, $duration, $rdv->id
        ));

        if ($available) {
            return ['status' => 'assigned', 'provider' => $available, 'alternative_slots' => []];
        }

        return ['status' => 'unavailable', 'provider' => null, 'alternative_slots' => $this->slots($candidates, $rdv, $duration, $zone)];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, User>  $candidates
     * @return list<array{date:string, heure:string}>
     */
    private function slots($candidates, Booking $rdv, int $duration, ?ServiceZone $zone): array
    {
        $slots = [];
        $start = $rdv->date->copy()->startOfDay();
        for ($d = 0; $d < 7 && count($slots) < 5; $d++) {
            $day = $start->copy()->addDays($d);
            foreach (['09:00', '11:00', '14:00', '16:00'] as $heure) {
                if (count($slots) >= 5) {
                    break;
                }
                $hit = $candidates->first(fn (User $w) => $this->availability->employeeIsAvailableForSlot(
                    $w->id, $day->format('Y-m-d'), $heure, $zone, $duration, $rdv->id
                ));
                if ($hit) {
                    $slots[] = ['date' => $day->format('Y-m-d'), 'heure' => $heure];
                }
            }
        }

        return $slots;
    }
}
```

- [ ] **Step 4 : PASS** `php artisan test --filter=PreferredCompanyResolverTest`

- [ ] **Step 5 : pint + commit**
```bash
vendor/bin/pint app/Services/Booking/PreferredCompanyResolver.php tests/Feature/Relations/PreferredCompanyResolverTest.php
git add app/Services/Booking/PreferredCompanyResolver.php tests/Feature/Relations/PreferredCompanyResolverTest.php
git commit -m "feat(relations): PreferredCompanyResolver (company available→worker / else alternative slots)"
```

---

## Task 6 : Sélection société (gating) + persistance

**Files :** `app/Services/Booking/ProviderSelectionResolver.php` ; `app/Services/Booking/CreateBookingAction.php` ; `app/Actions/Booking/CreateBookingFromApiAction.php` ; Test `tests/Feature/Relations/CompanySelectionResolverTest.php`

- [ ] **Step 1 : Test qui échoue**
```php
public function test_choosing_an_eligible_company_requires_premium_and_validates_eligibility(): void
{
    // client NON-premium choisit orgA éligible → AuthorizationException
    // client PREMIUM choisit orgA éligible → out['assigned_provider_organization_id'] === orgA->id
    // client PREMIUM choisit orgC NON éligible → AuthorizationException (org pas réservable)
}
```
(Monte un booking-context minimal pour que `EligibleCompaniesResolver::isEligible` puisse statuer — réutilise les fixtures de Task 3. Le resolver reçoit le booking OU les ids zone/métier — voir Step 3 pour la signature.)

- [ ] **Step 2 : FAIL.** `php artisan test --filter=CompanySelectionResolverTest`

- [ ] **Step 3 : Étendre `ProviderSelectionResolver`** — accepte `assigned_provider_organization_id` + `booking` (ou zone/trade) pour valider l'éligibilité :
  - signature : `resolve(User $client, array $input, ?Booking $context = null): array{provider_type_preference:string, preferred_provider_user_id:?int, assigned_provider_organization_id:?int}`.
  - logique société : si `$input['assigned_provider_organization_id']` est posé : exiger premium (`$isPremium`) sinon `AuthorizationException` ; si `$context` fourni, exiger `app(EligibleCompaniesResolver::class)->isEligible($context, $orgId)` sinon `AuthorizationException('Société non disponible pour cette réservation.')`. Retourner l'org dans la sortie. (Le palier worker SP2 reste inchangé ; `assigned_provider_organization_id` et `preferred_provider_user_id` sont mutuellement exclusifs — si les deux sont posés, privilégie l'org OU lève une erreur de cohérence ; choisis : privilégie l'org et ignore le worker, OU lève — documente.)
  - Met à jour le `@param/@return` (array shape) pour PHPStan.

- [ ] **Step 4 : Persistance** — `CreateBookingAction::execute` (dans le `Booking::create`, à côté de `preferred_provider_user_id`/`provider_type_preference`) ET `CreateBookingFromApiAction::execute` ajoutent :
```php
            'assigned_provider_organization_id' => Arr::get($data, 'assigned_provider_organization_id'),
```
(Et les appelants — `ClientBookingController` (API) + `PrendreRendezVous`/`HandlesBookingCreation` (web) + `BookingHub` — passent la sortie enrichie du resolver dans `$data`. Le contrôleur API appelle `ProviderSelectionResolver::resolve($user, $input, $bookingContextOrNull)` ; si pas de contexte booking dispo au moment du gating, fais la validation d'éligibilité avec les ids zone/métier de la requête — adapte.)

- [ ] **Step 5 : PASS** `php artisan test --filter='CompanySelectionResolver|ProviderSelectionResolver'`

- [ ] **Step 6 : Non-régression** `php artisan test --filter='CreateBooking|MobileBookingSelection|PrendreRendezVous|BookingHub'` vert.

- [ ] **Step 7 : pint + commit**
```bash
vendor/bin/pint app/Services/Booking/ProviderSelectionResolver.php app/Services/Booking/CreateBookingAction.php app/Actions/Booking/CreateBookingFromApiAction.php app/Http/Controllers/Api tests/Feature/Relations/CompanySelectionResolverTest.php
git add app/Services/Booking/ProviderSelectionResolver.php app/Services/Booking/CreateBookingAction.php app/Actions/Booking/CreateBookingFromApiAction.php app/Http/Controllers/Api tests/Feature/Relations/CompanySelectionResolverTest.php
git commit -m "feat(relations): company selection (premium-gated + eligibility-validated) persisted on booking"
```

---

## Task 7 : Endpoint sociétés éligibles

**Files :** Create `app/Http/Controllers/Api/Client/CompanyDirectoryController.php` + route ; Test `tests/Feature/Relations/CompanyDirectoryApiTest.php`

- [ ] **Step 1 : Test qui échoue** — `GET /client/companies?service_catalog_id=..&service_zone_id=..` (Sanctum) renvoie les sociétés éligibles `{id, name, rating_avg, rating_count, providers_count}` triées par note ; un client authentifié OK ; une société non éligible absente. (Calque l'auth sur un test API existant.)

- [ ] **Step 2 : FAIL.** `php artisan test --filter=CompanyDirectoryApiTest`

- [ ] **Step 3 : Implémenter** — contrôleur qui construit un `Booking` non persisté (ou un DTO) à partir de `service_catalog_id`/`service_zone_id` de la requête, appelle `EligibleCompaniesResolver::forBooking`, et sérialise `{id, name, rating_avg, rating_count, providers_count}` (`providers_count` = nb de workers `company_worker` actifs/vérifiés de l'org). Route `Route::get('/companies', CompanyDirectoryController::class)` dans le groupe client authentifié (cherche le fichier de routes API client + son préfixe). (Si construire un `Booking` transitoire est gênant pour `forBooking`, ajoute une surcharge `EligibleCompaniesResolver::forContext(int $zoneId, ?int $tradeId)` que `forBooking` appelle, et utilise-la ici.)

- [ ] **Step 4 : PASS** `php artisan test --filter=CompanyDirectoryApiTest`

- [ ] **Step 5 : pint + commit**
```bash
vendor/bin/pint app/Http/Controllers/Api/Client/CompanyDirectoryController.php app/Services/Booking/EligibleCompaniesResolver.php routes tests/Feature/Relations/CompanyDirectoryApiTest.php
git add app/Http/Controllers/Api/Client/CompanyDirectoryController.php app/Services/Booking/EligibleCompaniesResolver.php routes tests/Feature/Relations/CompanyDirectoryApiTest.php
git commit -m "feat(relations): GET /client/companies — eligible provider companies (rating + providers count)"
```

---

## Task 8 : UI Web — browse-sociétés

**Files :** Create `app/Livewire/Client/BrowseCompanies.php` (+ vue) ; Modify `app/Livewire/Client/PrendreRendezVous.php` (+ la vue picker `provider-selection.blade.php`) ; Test `tests/Feature/Relations/BrowseCompaniesSelectionTest.php`

**Contrat (réutilise le pattern SP2 BrowseProviders) :** `BrowseCompanies` (Livewire) liste les sociétés éligibles (via `EligibleCompaniesResolver` ou l'endpoint) avec note + nb prestataires ; en mode sélection, un bouton « Choisir cette société » → `dispatch('companySelected', organizationId: $id)`. `PrendreRendezVous` : un listener `#[On('companySelected')] onCompanySelected(int $orgId)` pose `$this->assignedProviderOrganizationId = $orgId` (+ vide `preferredProviderUserId`) et ferme le picker. La vue picker : quand `provider_type_preference='company'` + `canPickPremiumProvider()`, embarque `<livewire:client.browse-companies :selection-mode="true">` au lieu de (ou en plus de) `BrowseProviders`. À la soumission, `assignedProviderOrganizationId` passe par `ProviderSelectionResolver` (Task 6) dans `$data`.

- [ ] **Step 1 : LIRE** `PrendreRendezVous.php` (les props SP2 : `providerTypePreference`, `preferredProviderUserId`, `showProviderPicker`, `canPickPremiumProvider`, `onProviderSelected`), la vue `provider-selection.blade.php`, et `BrowseProviders.php` (le pattern `selectionMode` + event).
- [ ] **Step 2 : Test composant** — `BrowseCompaniesSelectionTest.php` : (a) `BrowseCompanies` en mode sélection `->call('selectCompany', $id)->assertDispatched('companySelected', organizationId: $id)` ; (b) `PrendreRendezVous->call('onCompanySelected', $org->id)` → `assertSet('assignedProviderOrganizationId', $org->id)` + picker fermé ; (c) un client premium qui choisit une société et soumet → booking `assigned_provider_organization_id` = l'org.
- [ ] **Step 3 : Implémenter** — `BrowseCompanies.php` (`selectionMode`, liste via `EligibleCompaniesResolver::forContext` ou un `where type=provider_company orderBy rating`, `selectCompany($id)` dispatch) + sa vue (carte société : nom, note, nb prestataires, bouton « Choisir »). `PrendreRendezVous` : prop `public ?int $assignedProviderOrganizationId = null;` + listener `onCompanySelected` + passage dans `$data` (via le resolver Task 6, avec le contexte booking pour la validation d'éligibilité). Vue picker : embed `BrowseCompanies` pour le type company premium.
- [ ] **Step 4 : PASS** `php artisan test --filter='BrowseCompaniesSelection|PrendreRendezVous'`
- [ ] **Step 5 : Non-régression + rendu** `php artisan test --filter='PrendreRendezVous|BrowseCompanies|BrowseProviders|Booking'` vert ; la vue rend.
- [ ] **Step 6 : pint + commit**
```bash
vendor/bin/pint app/Livewire/Client/BrowseCompanies.php app/Livewire/Client/PrendreRendezVous.php resources/views/livewire/client tests/Feature/Relations/BrowseCompaniesSelectionTest.php
git add app/Livewire/Client/BrowseCompanies.php app/Livewire/Client/PrendreRendezVous.php resources/views/livewire/client tests/Feature/Relations/BrowseCompaniesSelectionTest.php
git commit -m "feat(relations): web — browse + select a provider company (rating) in the booking picker"
```

---

## Task 9 : UI Mobile — browse-sociétés

**Files :** Create `mobile/client/src/screens/booking/BookingCompanySearchScreen.tsx` ; Modify `mobile/client/src/booking/BookingProvider.tsx` (état `assignedProviderOrganizationId`) + `BookingStepProvider.tsx` + `BookingNavigator.tsx` + types + le payload de création + un hook `useEligibleCompanies` ; Test Jest

**Contrat (réutilise le pattern SP2 `BookingProviderSearchScreen`) :** un écran `BookingCompanySearch` dans la stack booking liste les sociétés éligibles (via `GET /client/companies`) avec note + nb prestataires ; tap « Choisir » → `dispatch({ type: 'SET_PREFERRED_COMPANY', assignedProviderOrganizationId: id })` (nouvelle action du reducer) + `goBack()`. `BookingStepProvider` : pour le type « company » + premium, le bouton « Choisir une société » navigue vers `BookingCompanySearch`. Le payload de création envoie `assigned_provider_organization_id`.

- [ ] **Step 1 : LIRE** `BookingProvider.tsx` (reducer/état SP2 : `preferredProviderUserId`, `providerTypePreference`), `BookingStepProvider.tsx` (boutons type + picker premium), `BookingProviderSearchScreen.tsx` (le pattern à cloner), `hooks.ts` (`useBrowseProviders`, le payload de création), `BookingNavigator.tsx`/`types.ts`.
- [ ] **Step 2 : Test Jest** — calque sur `booking-provider-search.test.tsx` : mock `GET /client/companies` (org id 7), rendre `BookingCompanySearchScreen` dans le contexte booking, tap « Choisir » → `state.assignedProviderOrganizationId === 7` + `goBack()` appelé. + un test que le payload de création inclut `assigned_provider_organization_id` quand posé.
- [ ] **Step 3 : Implémenter** — état `assignedProviderOrganizationId: number|null` + action `SET_PREFERRED_COMPANY` dans `BookingProvider.tsx` ; hook `useEligibleCompanies` (`GET /client/companies`) ; écran `BookingCompanySearchScreen.tsx` (liste sociétés : nom, note, nb prestataires, bouton Choisir → dispatch + goBack) ; route `BookingCompanySearch` dans `BookingNavigator`/`types.ts` ; `BookingStepProvider` : pour type company + `is_premium`, bouton « Choisir une société » → navigate. Payload création : ajoute `assigned_provider_organization_id`.
- [ ] **Step 4 : Vérifs** `cd mobile/client && npx tsc --noEmit && npx jest src/screens/booking --silent ; cd ../..` verts.
- [ ] **Step 5 : commit**
```bash
git add mobile/client/src/screens/booking mobile/client/src/booking mobile/client/src/navigation
git commit -m "feat(relations): mobile — browse + select a provider company in the booking wizard"
```

---

## Task 10 : Vérification complète + DoD

- [ ] **Step 1 : Gates backend** `php artisan test` ; `vendor/bin/pint --test` ; `vendor/bin/phpstan analyse --memory-limit=1G --no-progress`. Suite verte ; **PHPStan FULL** `[OK]` (corriger les nouveaux services/relations avec de vraies annotations — `@return array{...}`, `Builder<User>`, accès typés via `instanceof` comme SP2 ; zéro suppression) ; Pint clean. Corriger toute fixture cassée par la colonne note ou le scope org, sans affaiblir.
- [ ] **Step 2 : Gates mobile** `cd mobile/client && npx tsc --noEmit && npx jest --silent ; cd ../..` verts.
- [ ] **Step 3 : DoD** (spec) : note org agrégée + commande + hook (T1) ; filtre org éligibilité (T2) ; EligibleCompaniesResolver (T3) ; matching scopé org web+mobile (T4) ; indispo société (T5) ; sélection société gating+persistance (T6) ; endpoint (T7) ; UI web (T8) ; UI mobile (T9). DispatchCenter réassignation : vérifie un test existant `ProviderCompany`/`DispatchCenter` reste vert (les missions auto-suggérées ont `provider_organization_id` rempli).
- [ ] **Step 4 : commit (si pint a reformaté)** `git add -A && git commit -m "chore(relations): pint formatting on SP3 files"`

---

## Self-review (déjà appliqué)

- **Couverture spec :** note société → T1 ; éligibilité société → T3 ; filtre org → T2 ; matching scopé + auto-suggest → T4 ; indispo société → T5 ; sélection+gating+persistance → T6 ; endpoint → T7 ; UI web → T8 ; UI mobile → T9 ; vérif → T10.
- **Réutilisations :** SP1 (éligibilité + `provider_organization_id`), SP2 (`ProviderSelectionResolver`, pattern `PreferredProviderResolver`, picker web `BrowseProviders`/mobile `BookingProviderSearchScreen`, `is_premium`), `RatingAggregationService`, `OrganizationAccount::users`, `DispatchCenter` société.
- **Cohérence noms/types :** `OrganizationRatingAggregator::recompute/recomputeForUser`, `EligibleCompaniesResolver::forBooking/forContext/isEligible`, `EmployeeAvailabilityService::*(?int $organizationId=null)`, `PreferredCompanyResolver::resolve(Booking):array{status,provider,alternative_slots}`, `ProviderSelectionResolver::resolve(User,$input,?Booking):array{…,assigned_provider_organization_id:?int}`, event web `companySelected`, action mobile `SET_PREFERRED_COMPANY`, champ `assigned_provider_organization_id`.
- **Points « lire+adapter » assumés :** la ligne exacte du hook dans `RatingAggregationService` (T1) ; l'accès `providerProfile.organization_account_id` sur User chargé (T3) ; les fichiers de routes API + l'auth des tests (T7) ; la structure des composants UI web/mobile (T8/T9). Tout le code des nouveaux services backend (T1, T3, T5) est complet.
- **Hors scope :** favori société, mode manuel, avis société, refonte DispatchCenter (réutilisé).
