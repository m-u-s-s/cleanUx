# SP1 — Socle prestataire (identité, matchabilité, traçabilité) — Plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rendre les 4 relations client×prestataire (C2I/C2B/B2I/B2B) réellement matchables et tracées : éligibilité par `provider_type`×métier×zone×dispo×vérif (fin de `role='employe'`), inscription qui rend le presta matchable, désambiguïsation société-cliente/société-prestataire, et propagation de la société prestataire jusqu'à la mission.

**Architecture :** On corrige la **requête d'éligibilité unique** (`EmployeeAvailabilityService`, à laquelle MatchingV2/AiDispatch/SmartDispatch délèguent tous) pour filtrer sur `provider_profiles` (type/statut/vérif) ; le filtre **métier** reste dans `MatchingV2Service::applyTradeFilter` (fallback souple conservé). On corrige `isClientCompany()` pour gater sur le `type` d'org. On ajoute les colonnes société/équipe manquantes (migration idempotente + correction de la migration `trade_id` cassée) et on les propage booking→mission (création + dispatch). 4 tests E2E + cas négatifs.

**Tech Stack :** Laravel 10, Eloquent, PHPUnit, enums PHP (`ProviderType`, `OrganizationType`), pivot `trade_user`, PHPStan (larastan, run complet), Pint.

**Spec :** `docs/superpowers/specs/2026-05-31-relations-sp1-provider-foundation-design.md`
**Branche :** `feat/relations-sp1-provider-foundation` (off `main`).

---

## Vérités terrain (vérifiées dans le code)

- `EmployeeAvailabilityService::eligibleEmployeesQuery()` (`app/Services/Booking/EmployeeAvailabilityService.php:14-18`) et `employeeCanCoverZone()` (`:80-83`) filtrent `->where('role','employe')->where('is_active',true)`. MatchingV2/AiDispatch/SmartDispatch passent tous par `sortedEligibleEmployeesForZone()` → un seul point.
- `MatchingV2Service::applyTradeFilter()` (`app/Services/Matching/MatchingV2Service.php:98-126`) filtre déjà par métier via `User::trades` (pivot `trade_user`) avec **fallback souple** (si personne n'a le métier, renvoie tous + log warning). **On le conserve tel quel.**
- `ProviderProfile` a `provider_type` (cast `ProviderType`), `status`, `verification_status` ; helpers `isActive()` (`status==='active'`), `isVerified()` (`verification_status==='verified'`). `organization_account_id` lie le worker à sa société.
- `ProviderType` (`app/Enums/ProviderType.php`) : `INDEPENDENT`, `INDIVIDUAL`, `COMPANY`, `COMPANY_WORKER`. `OrganizationType` (`app/Enums/OrganizationType.php`) : `CLIENT_COMPANY`, `PROVIDER_COMPANY`, `PROVIDER_SOLO`, `HYBRID` + `isClient()` (CLIENT_COMPANY|HYBRID) / `isProvider()`.
- `OrganizationAccount.type` **n'est PAS casté** → `$org->type` est une **string**.
- `User` : relation `trades()` (BelongsToMany, `app/Models/Concerns/HasProviderFeatures.php:36`), relation `organizationAccount()` (BelongsTo via `organization_account_id`, `app/Models/Concerns/HasOrganizationContext.php:56`). `isEmploye()` = `isProviderIndependent() || isProviderCompanyWorker()` (`User.php:212`).
- `HasUserTypeChecks::isClientCompany()` (`:56-78`) renvoie `true` dès `! empty(organization_account_id)` sans regarder le type d'org (**le bug**). `homeDashboardRoute()` (`:151`) et `assistantContextRole()` (`:126`) testent `isClientCompany()` avant `isProviderCompanyWorker()`.
- `CreateNewUser` (`app/Actions/Fortify/CreateNewUser.php`) : `role => $input['role'] ?? 'client'` (ligne ~81) ; `createProviderIndependent()` / `createProviderCompany()` créent le `ProviderProfile` (bon type, `status='pending'`, `verification_status='unverified'`) mais **ne mettent jamais `role='employe'` ni de métiers**.
- `Mission::$fillable` (`app/Models/Mission.php:15-57`) n'a **ni `provider_organization_id` ni `provider_team_id`** (les 2 colonnes existent déjà en DB). `Booking::$fillable` a déjà `assigned_provider_organization_id`/`assigned_provider_user_id`/`provider_team_id`/`customer_organization_id` + relations, mais **`bookings.assigned_provider_organization_id` et `bookings.provider_team_id` n'existent PAS en DB**.
- Création mission (2 chemins) : `CreateBookingFromApiAction.php:77-81` et `ProcessRecurringBookings.php:136-140` font `Mission::create(['booking_id','status'=>'planned','planned_start_at'])` — aucun champ prestataire.
- Dispatch : `MissionDispatchService.php:204-208` (`app/Services/Dispatch/MissionDispatchService.php`) écrit `lead_provider_user_id` + `lead_employee_id`, **jamais l'org**.
- `ProviderDashboard.php:35` filtre `Mission::where('provider_organization_id',$orgId)` (jamais rempli → dashboard vide) ; `DispatchCenter.php:44` filtre `Mission::where('organization_account_id',$orgId)` (org cliente — incohérent).
- Migration cassée `2026_05_27_000005_make_trade_id_required_on_service_catalogs.php` : `$table->foreignId('trade_id')->nullable(false)->change()` échoue en MySQL car une FK `ON DELETE SET NULL` existe sur `trade_id`.

---

## Structure de fichiers

- Modifier `app/Services/Booking/EmployeeAvailabilityService.php` — éligibilité type-aware. (Task 3)
- Modifier `app/Models/Concerns/HasUserTypeChecks.php` — `isClientCompany()` gate type + réordo routage. (Task 2)
- Modifier `app/Actions/Fortify/CreateNewUser.php` — inscription presta matchable + métiers. (Task 4)
- Créer `database/migrations/2026_06_01_000001_add_provider_org_team_to_booking_and_mission.php` — colonnes idempotentes. (Task 1)
- Modifier `database/migrations/2026_05_27_000005_make_trade_id_required_on_service_catalogs.php` — drop/recrée la FK. (Task 1)
- Modifier `app/Models/Mission.php` — `$fillable` + relation `providerOrganization()`. (Task 1)
- Modifier `app/Actions/Booking/CreateBookingFromApiAction.php` + `app/Console/Commands/ProcessRecurringBookings.php` — copie booking→mission. (Task 5)
- Modifier `app/Services/Dispatch/MissionDispatchService.php` — écrit l'org dérivée. (Task 5)
- Modifier `app/Livewire/ProviderCompany/DispatchCenter.php` — filtre `provider_organization_id`. (Task 6)
- Modifier `database/factories/UserFactory.php` — l'état `employe()` crée un `ProviderProfile` actif+vérifié (backward-compat). (Task 3)
- Créer `tests/Feature/Relations/ProviderEligibilityTest.php` + `tests/Feature/Relations/CompanyDisambiguationTest.php` + `tests/Feature/Relations/FourRelationsE2ETest.php`. (Tasks 2,3,7)

---

## Task 1 : Schéma — colonnes société/équipe + déblocage migration + Mission fillable

**Files :**
- Create : `database/migrations/2026_06_01_000001_add_provider_org_team_to_booking_and_mission.php`
- Modify : `database/migrations/2026_05_27_000005_make_trade_id_required_on_service_catalogs.php`
- Modify : `app/Models/Mission.php:15-57` (fillable) + relations
- Test : `tests/Feature/Relations/SchemaProviderColumnsTest.php`

- [ ] **Step 1 : Test qui échoue**

Créer `tests/Feature/Relations/SchemaProviderColumnsTest.php` :

```php
<?php

namespace Tests\Feature\Relations;

use App\Models\Mission;
use App\Models\OrganizationAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchemaProviderColumnsTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_org_and_team_columns_exist_and_are_fillable(): void
    {
        $this->assertTrue(Schema::hasColumn('bookings', 'assigned_provider_organization_id'));
        $this->assertTrue(Schema::hasColumn('bookings', 'provider_team_id'));
        $this->assertTrue(Schema::hasColumn('missions', 'provider_organization_id'));
        $this->assertTrue(Schema::hasColumn('missions', 'provider_team_id'));

        $org = OrganizationAccount::factory()->create();
        $mission = Mission::create([
            'status' => 'planned',
            'provider_organization_id' => $org->id,
        ]);

        $this->assertSame($org->id, $mission->fresh()->provider_organization_id);
        $this->assertInstanceOf(OrganizationAccount::class, $mission->providerOrganization);
    }
}
```

- [ ] **Step 2 : Lancer → FAIL** (`provider_organization_id` non fillable / relation absente).
`php artisan test --filter=SchemaProviderColumnsTest`

- [ ] **Step 3 : Migration idempotente**

Créer `database/migrations/2026_06_01_000001_add_provider_org_team_to_booking_and_mission.php` :

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SP1 socle prestataire : garantit la présence des colonnes de traçabilité
 * société/équipe sur bookings + missions, indépendamment de la chaîne de
 * migrations en attente. Idempotent (gardes hasColumn).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'assigned_provider_organization_id')) {
                $table->foreignId('assigned_provider_organization_id')->nullable()->after('assigned_provider_user_id');
            }
            if (! Schema::hasColumn('bookings', 'provider_team_id')) {
                $table->unsignedBigInteger('provider_team_id')->nullable()->after('assigned_provider_organization_id');
            }
        });

        Schema::table('missions', function (Blueprint $table) {
            if (! Schema::hasColumn('missions', 'provider_organization_id')) {
                $table->foreignId('provider_organization_id')->nullable()->after('organization_account_id');
            }
            if (! Schema::hasColumn('missions', 'provider_team_id')) {
                $table->unsignedBigInteger('provider_team_id')->nullable()->after('provider_organization_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            foreach (['assigned_provider_organization_id', 'provider_team_id'] as $col) {
                if (Schema::hasColumn('bookings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
        Schema::table('missions', function (Blueprint $table) {
            foreach (['provider_organization_id', 'provider_team_id'] as $col) {
                if (Schema::hasColumn('missions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
```

- [ ] **Step 4 : Corriger la migration `trade_id` cassée**

Dans `database/migrations/2026_05_27_000005_make_trade_id_required_on_service_catalogs.php`, remplacer le bloc `if ($remainingNulls === 0 && ...)` par une version qui dépose la FK avant le `NOT NULL` puis la recrée sans `SET NULL` :

```php
        if ($remainingNulls === 0 && config('database.default') !== 'sqlite') {
            // MySQL refuse de passer une colonne en NOT NULL tant qu'elle porte
            // une FK ON DELETE SET NULL : on dépose la FK, on change, on recrée.
            Schema::table('service_catalogs', function (Blueprint $table) {
                try {
                    $table->dropForeign(['trade_id']);
                } catch (\Throwable $e) {
                    // FK déjà absente : on continue.
                }
            });

            Schema::table('service_catalogs', function (Blueprint $table) {
                $table->foreignId('trade_id')->nullable(false)->change();
            });

            Schema::table('service_catalogs', function (Blueprint $table) {
                $table->foreign('trade_id')->references('id')->on('trades')
                    ->cascadeOnUpdate()->restrictOnDelete();
            });
        }
```

- [ ] **Step 5 : Mission `$fillable` + relation**

Dans `app/Models/Mission.php`, ajouter dans `$fillable` (après `'organization_account_id',` ligne 18) :

```php
        'provider_organization_id',
        'provider_team_id',
```

Et ajouter la relation (près des autres `BelongsTo`) :

```php
    public function providerOrganization(): BelongsTo
    {
        return $this->belongsTo(OrganizationAccount::class, 'provider_organization_id');
    }
```

- [ ] **Step 6 : Lancer → PASS.** `php artisan test --filter=SchemaProviderColumnsTest`

- [ ] **Step 7 : pint + commit**

```bash
vendor/bin/pint database/migrations app/Models/Mission.php tests/Feature/Relations/SchemaProviderColumnsTest.php
git add database/migrations app/Models/Mission.php tests/Feature/Relations/SchemaProviderColumnsTest.php
git commit -m "feat(relations): provider org/team columns on booking+mission + unblock trade_id migration"
```

---

## Task 2 : Désambiguïsation société-cliente / société-prestataire

**Files :**
- Modify : `app/Models/Concerns/HasUserTypeChecks.php:56-78` (isClientCompany) + `:151-174`/`:126-149` (routage)
- Test : `tests/Feature/Relations/CompanyDisambiguationTest.php`

- [ ] **Step 1 : Test qui échoue**

```php
<?php

namespace Tests\Feature\Relations;

use App\Enums\OrganizationType;
use App\Enums\ProviderType;
use App\Models\OrganizationAccount;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyDisambiguationTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_company_worker_is_not_a_client_company(): void
    {
        $org = OrganizationAccount::factory()->create(['type' => OrganizationType::PROVIDER_COMPANY->value]);
        $user = User::factory()->create(['organization_account_id' => $org->id, 'current_organization_id' => $org->id]);
        ProviderProfile::create([
            'user_id' => $user->id,
            'organization_account_id' => $org->id,
            'provider_type' => ProviderType::COMPANY_WORKER->value,
            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        $this->assertFalse($user->isClientCompany(), 'une société-prestataire ne doit pas être vue comme société-cliente');
        $this->assertTrue($user->isProviderCompanyWorker());
        $this->assertSame('provider-company.dashboard', $user->homeDashboardRoute());
    }

    public function test_client_company_is_still_a_client_company(): void
    {
        $org = OrganizationAccount::factory()->create(['type' => OrganizationType::CLIENT_COMPANY->value]);
        $user = User::factory()->create(['organization_account_id' => $org->id, 'current_organization_id' => $org->id]);

        $this->assertTrue($user->isClientCompany());
        $this->assertSame('client-company.dashboard', $user->homeDashboardRoute());
    }
}
```

- [ ] **Step 2 : Lancer → FAIL** (la société-prestataire est vue comme cliente). `php artisan test --filter=CompanyDisambiguationTest`

- [ ] **Step 3 : Corriger `isClientCompany()`**

Dans `app/Models/Concerns/HasUserTypeChecks.php`, ajouter `use App\Enums\OrganizationType;` en tête, puis remplacer le bloc `if (! empty($this->organization_account_id)) { return true; }` (`:68-70`) par :

```php
        if (! empty($this->organization_account_id)) {
            $orgType = $this->organizationAccount?->type;
            $enum = $orgType instanceof OrganizationType ? $orgType : OrganizationType::tryFrom((string) $orgType);
            if ($enum !== null) {
                // CLIENT_COMPANY / HYBRID → cliente ; PROVIDER_* → non.
                return $enum->isClient();
            }
            // type inconnu : on retombe sur le fallback legacy ci-dessous.
        }
```

- [ ] **Step 4 : Réordonner le routage (provider-société avant client-société)**

Dans `homeDashboardRoute()` ET `assistantContextRole()`, déplacer le bloc `isProviderCompanyWorker()` **avant** `isClientCompany()`. Pour `homeDashboardRoute()` :

```php
    public function homeDashboardRoute(): string
    {
        if ($this->isPlatformAdmin()) {
            return 'admin.dashboard';
        }

        if ($this->isProviderCompanyWorker()) {
            return 'provider-company.dashboard';
        }

        if ($this->isProviderIndependent()) {
            return 'employe.dashboard';
        }

        if ($this->isClientCompany()) {
            return 'client-company.dashboard';
        }

        if ($this->isClientPersonal()) {
            return 'client.dashboard';
        }

        return 'dashboard';
    }
```

Et symétriquement dans `assistantContextRole()` (tester `isProviderCompanyWorker()` puis `isProviderIndependent()` avant `isClientCompany()`/`isClientPersonal()`).

- [ ] **Step 5 : Lancer → PASS.** `php artisan test --filter=CompanyDisambiguationTest`

- [ ] **Step 6 : pint + commit**

```bash
vendor/bin/pint app/Models/Concerns/HasUserTypeChecks.php tests/Feature/Relations/CompanyDisambiguationTest.php
git add app/Models/Concerns/HasUserTypeChecks.php tests/Feature/Relations/CompanyDisambiguationTest.php
git commit -m "fix(relations): isClientCompany gate sur le type d'org + routage provider-société prioritaire"
```

---

## Task 3 : Éligibilité prestataire type-aware (cœur)

**Files :**
- Modify : `app/Services/Booking/EmployeeAvailabilityService.php`
- Modify : `database/factories/UserFactory.php` (état `employe()` → ProviderProfile actif+vérifié)
- Test : `tests/Feature/Relations/ProviderEligibilityTest.php`

- [ ] **Step 1 : Test qui échoue**

```php
<?php

namespace Tests\Feature\Relations;

use App\Enums\ProviderType;
use App\Models\OrganizationAccount;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Booking\EmployeeAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderEligibilityTest extends TestCase
{
    use RefreshDatabase;

    private function makeProvider(string $providerType, string $status = 'active', string $verif = 'verified', ?int $orgId = null): User
    {
        $user = User::factory()->create(['is_active' => true]);
        ProviderProfile::create([
            'user_id' => $user->id,
            'organization_account_id' => $orgId,
            'provider_type' => $providerType,
            'status' => $status,
            'verification_status' => $verif,
        ]);

        return $user;
    }

    public function test_any_type_includes_independent_and_company_worker_but_excludes_unverified(): void
    {
        $indep = $this->makeProvider(ProviderType::INDEPENDENT->value);
        $org = OrganizationAccount::factory()->create();
        $companyWorker = $this->makeProvider(ProviderType::COMPANY_WORKER->value, orgId: $org->id);
        $unverified = $this->makeProvider(ProviderType::INDEPENDENT->value, verif: 'unverified');
        $pending = $this->makeProvider(ProviderType::INDEPENDENT->value, status: 'pending');

        $ids = app(EmployeeAvailabilityService::class)
            ->eligibleEmployeesQuery(null, 'any')->pluck('id');

        $this->assertTrue($ids->contains($indep->id));
        $this->assertTrue($ids->contains($companyWorker->id));
        $this->assertFalse($ids->contains($unverified->id), 'un presta non vérifié ne doit pas être éligible');
        $this->assertFalse($ids->contains($pending->id), 'un presta non actif ne doit pas être éligible');
    }

    public function test_independent_type_excludes_company_worker_and_vice_versa(): void
    {
        $indep = $this->makeProvider(ProviderType::INDEPENDENT->value);
        $org = OrganizationAccount::factory()->create();
        $companyWorker = $this->makeProvider(ProviderType::COMPANY_WORKER->value, orgId: $org->id);

        $independentOnly = app(EmployeeAvailabilityService::class)
            ->eligibleEmployeesQuery(null, 'independent')->pluck('id');
        $this->assertTrue($independentOnly->contains($indep->id));
        $this->assertFalse($independentOnly->contains($companyWorker->id));

        $companyOnly = app(EmployeeAvailabilityService::class)
            ->eligibleEmployeesQuery(null, 'company')->pluck('id');
        $this->assertTrue($companyOnly->contains($companyWorker->id));
        $this->assertFalse($companyOnly->contains($indep->id));
    }
}
```

- [ ] **Step 2 : Lancer → FAIL** (la requête lit `role='employe'`, pas le profil ; le paramètre `$providerType` n'existe pas). `php artisan test --filter=ProviderEligibilityTest`

- [ ] **Step 3 : Réécrire l'éligibilité**

Dans `app/Services/Booking/EmployeeAvailabilityService.php`, ajouter `use App\Enums\ProviderType;` en tête. Remplacer la signature + le filtre de base de `eligibleEmployeesQuery` :

```php
    public function eligibleEmployeesQuery(?int $zoneId = null, string $providerType = 'any'): Builder
    {
        $query = User::query()
            ->whereHas('providerProfile', function (Builder $q) use ($providerType) {
                $q->whereIn('provider_type', $this->providerTypeValues($providerType))
                    ->where('status', 'active')
                    ->where('verification_status', 'verified');
            })
            ->where('is_active', true);

        // ... (le reste de la méthode — zone, tri — inchangé)
```

Mettre à jour `employeeCanCoverZone()` de la même façon (remplacer `->where('role','employe')` par le même `whereHas('providerProfile', ...)` — extraire dans un closure privé `private function applyProviderEligibility(Builder $q, string $providerType = 'any'): void` pour DRY, et l'appeler dans les deux méthodes). Ajouter le helper de mapping :

```php
    /** @return list<string> */
    private function providerTypeValues(string $providerType): array
    {
        return match ($providerType) {
            'independent' => [ProviderType::INDEPENDENT->value, ProviderType::INDIVIDUAL->value],
            'company' => [ProviderType::COMPANY_WORKER->value, ProviderType::COMPANY->value],
            default => [
                ProviderType::INDEPENDENT->value, ProviderType::INDIVIDUAL->value,
                ProviderType::COMPANY_WORKER->value, ProviderType::COMPANY->value,
            ],
        };
    }
```

Propager le paramètre optionnel `string $providerType = 'any'` dans `sortedEligibleEmployeesForZone(int $zoneId, string $providerType = 'any')` (et le passer à `eligibleEmployeesQuery`).

- [ ] **Step 4 : Backward-compat — factory `employe()`**

Le passage de `role='employe'` à « profil actif+vérifié » exclut les users de test sans profil vérifié. Mettre à jour l'état `employe()` de `database/factories/UserFactory.php` pour créer un `ProviderProfile` actif+vérifié après création :

```php
    public function employe(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'employe',
            'platform_role' => 'employe',
            'is_active' => true,
        ])->afterCreating(function (User $user) {
            \App\Models\ProviderProfile::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'provider_type' => \App\Enums\ProviderType::INDEPENDENT->value,
                    'status' => 'active',
                    'verification_status' => 'verified',
                ],
            );
        });
    }
```

(Garder le contenu existant de l'état `employe()` ; n'ajouter que le `afterCreating`. Vérifier la signature exacte de l'état actuel et ne pas casser ses autres attributs.)

- [ ] **Step 5 : Lancer → PASS.** `php artisan test --filter=ProviderEligibilityTest`

- [ ] **Step 6 : Non-régression matching** (le filtre métier MatchingV2 doit toujours marcher au-dessus)

```bash
php artisan test --filter='MatchingV2|Dispatch|Availability'
```
Tout doit rester vert. Si un test crée un `employe` sans profil et casse, corriger sa fixture pour utiliser `User::factory()->employe()` (qui crée désormais le profil) ou seeder un ProviderProfile actif+vérifié.

- [ ] **Step 7 : pint + commit**

```bash
vendor/bin/pint app/Services/Booking/EmployeeAvailabilityService.php database/factories/UserFactory.php tests/Feature/Relations/ProviderEligibilityTest.php
git add app/Services/Booking/EmployeeAvailabilityService.php database/factories/UserFactory.php tests/Feature/Relations/ProviderEligibilityTest.php
git commit -m "feat(relations): éligibilité prestataire type-aware (provider_profiles) remplace role=employe"
```

---

## Task 4 : Inscription prestataire matchable + métiers

**Files :**
- Modify : `app/Actions/Fortify/CreateNewUser.php`
- Test : `tests/Feature/Relations/ProviderRegistrationTest.php`

- [ ] **Step 1 : Test qui échoue**

```php
<?php

namespace Tests\Feature\Relations;

use App\Actions\Fortify\CreateNewUser;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registered_independent_provider_has_employe_role_and_trades(): void
    {
        $trade = Trade::factory()->create();

        $user = app(CreateNewUser::class)->create([
            'name' => 'Indep Test',
            'email' => 'indep@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'account_type' => 'provider_independent',
            'trade_ids' => [$trade->id],
        ]);

        $this->assertSame('employe', $user->fresh()->role);
        $this->assertTrue($user->isProviderIndependent());
        $this->assertTrue($user->trades()->where('trades.id', $trade->id)->exists());
    }
}
```

(Vérifier que `Trade::factory()` existe ; sinon créer la Trade via `Trade::create([...])` avec les colonnes requises.)

- [ ] **Step 2 : Lancer → FAIL** (role reste `client`, pas de métiers). `php artisan test --filter=ProviderRegistrationTest`

- [ ] **Step 3 : Corriger l'inscription**

Dans `app/Actions/Fortify/CreateNewUser.php` :

1. Déterminer le rôle selon le type de compte. Remplacer `'role' => $input['role'] ?? 'client',` par :

```php
                'role' => $input['role'] ?? (in_array($accountType, ['provider_independent', 'provider_company'], true) ? 'employe' : 'client'),
```

2. Rattacher les métiers : à la fin de `createProviderIndependent()` et `createProviderCompany()`, après la création du `ProviderProfile`, ajouter un appel `$this->attachTrades($user, $input);`. Et ajouter le helper :

```php
    private function attachTrades(User $user, array $input): void
    {
        $tradeIds = array_filter((array) ($input['trade_ids'] ?? []));
        if ($tradeIds !== []) {
            $user->trades()->syncWithoutDetaching($tradeIds);
        }
    }
```

(Les méthodes `createProviderIndependent`/`Company` ont déjà `$user` ; passer `$input` à `createProviderIndependent` — adapter sa signature en `createProviderIndependent(User $user, array $input)` et le call-site du `match` ligne ~99.)

- [ ] **Step 4 : Lancer → PASS.** `php artisan test --filter=ProviderRegistrationTest`

- [ ] **Step 5 : pint + commit**

```bash
vendor/bin/pint app/Actions/Fortify/CreateNewUser.php tests/Feature/Relations/ProviderRegistrationTest.php
git add app/Actions/Fortify/CreateNewUser.php tests/Feature/Relations/ProviderRegistrationTest.php
git commit -m "fix(relations): inscription prestataire pose role=employe + rattache les métiers (trade_user)"
```

---

## Task 5 : Propagation société booking→mission (création + dispatch)

**Files :**
- Modify : `app/Actions/Booking/CreateBookingFromApiAction.php:77-81`
- Modify : `app/Console/Commands/ProcessRecurringBookings.php:136-140`
- Modify : `app/Services/Dispatch/MissionDispatchService.php:204-208`
- Test : `tests/Feature/Relations/ProviderOrgPropagationTest.php`

- [ ] **Step 1 : Test qui échoue**

```php
<?php

namespace Tests\Feature\Relations;

use App\Enums\ProviderType;
use App\Models\Booking;
use App\Models\Mission;
use App\Models\OrganizationAccount;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Dispatch\MissionDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderOrgPropagationTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatch_writes_provider_organization_from_worker_profile(): void
    {
        $org = OrganizationAccount::factory()->create();
        $worker = User::factory()->create();
        ProviderProfile::create([
            'user_id' => $worker->id,
            'organization_account_id' => $org->id,
            'provider_type' => ProviderType::COMPANY_WORKER->value,
            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        $booking = Booking::factory()->create();
        $mission = Mission::create(['booking_id' => $booking->id, 'status' => 'planned']);

        // Simuler une assignment acceptée pour ce worker (selon la fixture du projet)
        $assignment = \App\Models\MissionAssignment::create([
            'mission_id' => $mission->id,
            'user_id' => $worker->id,
            'status' => 'accepted',
        ]);

        app(MissionDispatchService::class)->accept($assignment);

        $this->assertSame($org->id, $mission->fresh()->provider_organization_id);
        $this->assertSame($worker->id, $mission->fresh()->lead_provider_user_id);
    }
}
```

(Adapter la création de `MissionAssignment` + l'appel `accept()` à la signature réelle de `MissionDispatchService` — lire la méthode `accept()` autour de `:200` pour les paramètres exacts. Si `accept()` exige une assignment en statut précis, l'ajuster.)

- [ ] **Step 2 : Lancer → FAIL** (`provider_organization_id` reste null). `php artisan test --filter=ProviderOrgPropagationTest`

- [ ] **Step 3 : Dispatch écrit l'org dérivée**

Dans `app/Services/Dispatch/MissionDispatchService.php`, au bloc `$mission->update([... 'lead_provider_user_id' => $assignment->user_id ...])` (`:204-208`), dériver et écrire l'org :

```php
                $assignedUser = \App\Models\User::find($assignment->user_id);
                $providerOrgId = $assignedUser?->providerProfile?->organization_account_id;

                $mission->update([
                    'status' => 'assigned',
                    'lead_provider_user_id' => $assignment->user_id,
                    'lead_employee_id' => $assignment->user_id,
                    'provider_organization_id' => $providerOrgId,
                ]);
```

- [ ] **Step 4 : Création mission copie les champs prestataire de la réservation**

Dans `app/Actions/Booking/CreateBookingFromApiAction.php` (`:77-81`) ET `app/Console/Commands/ProcessRecurringBookings.php` (`:136-140`), ajouter au `Mission::create([...])` :

```php
            'lead_provider_user_id' => $booking->assigned_provider_user_id,
            'lead_employee_id' => $booking->assigned_provider_user_id,
            'provider_organization_id' => $booking->assigned_provider_organization_id,
            'provider_team_id' => $booking->provider_team_id,
            'organization_account_id' => $booking->customer_organization_id,
```

(Ces valeurs sont souvent null à la création — le dispatch les complète. Pour une réservation pré-assignée — favori/premium/portail société — elles sont propagées immédiatement.)

- [ ] **Step 5 : Lancer → PASS.** `php artisan test --filter=ProviderOrgPropagationTest`

- [ ] **Step 6 : pint + commit**

```bash
vendor/bin/pint app/Services/Dispatch/MissionDispatchService.php app/Actions/Booking/CreateBookingFromApiAction.php app/Console/Commands/ProcessRecurringBookings.php tests/Feature/Relations/ProviderOrgPropagationTest.php
git add app/Services/Dispatch/MissionDispatchService.php app/Actions/Booking/CreateBookingFromApiAction.php app/Console/Commands/ProcessRecurringBookings.php tests/Feature/Relations/ProviderOrgPropagationTest.php
git commit -m "feat(relations): propage la société prestataire booking→mission (création + dispatch)"
```

---

## Task 6 : Dashboards société-prestataire cohérents

**Files :**
- Modify : `app/Livewire/ProviderCompany/DispatchCenter.php` (filtres `:44`, `:81`, `:84`)
- Test : `tests/Feature/Relations/ProviderCompanyDashboardTest.php`

- [ ] **Step 1 : Test qui échoue**

```php
<?php

namespace Tests\Feature\Relations;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\OrganizationAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderCompanyDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatch_center_lists_missions_of_the_provider_org(): void
    {
        $providerOrg = OrganizationAccount::factory()->create();
        $otherOrg = OrganizationAccount::factory()->create();
        $booking = Booking::factory()->create();

        $mine = Mission::create(['booking_id' => $booking->id, 'status' => 'planned', 'provider_organization_id' => $providerOrg->id]);
        $notMine = Mission::create(['booking_id' => $booking->id, 'status' => 'planned', 'provider_organization_id' => $otherOrg->id]);

        $ids = Mission::where('provider_organization_id', $providerOrg->id)->pluck('id');

        $this->assertTrue($ids->contains($mine->id));
        $this->assertFalse($ids->contains($notMine->id));
    }
}
```

- [ ] **Step 2 : Lancer → PASS attendu pour l'assertion DB**, mais corriger le composant. `php artisan test --filter=ProviderCompanyDashboardTest`

- [ ] **Step 3 : Corriger `DispatchCenter`**

Dans `app/Livewire/ProviderCompany/DispatchCenter.php`, remplacer les filtres `Mission::where('organization_account_id', $orgId)` (`:44`, `:81`, `:84`) par `Mission::where('provider_organization_id', $orgId)` — la société-prestataire voit **les missions qu'elle exécute**, pas celles dont elle serait le client. (`ProviderDashboard` filtre déjà `provider_organization_id`, désormais rempli — vérifier qu'il rend non-vide avec une mission seedée.)

- [ ] **Step 4 : Lancer → PASS** + vérif rendu :
```bash
php artisan test --filter=ProviderCompanyDashboardTest
```

- [ ] **Step 5 : pint + commit**

```bash
vendor/bin/pint app/Livewire/ProviderCompany/DispatchCenter.php tests/Feature/Relations/ProviderCompanyDashboardTest.php
git add app/Livewire/ProviderCompany/DispatchCenter.php tests/Feature/Relations/ProviderCompanyDashboardTest.php
git commit -m "fix(relations): DispatchCenter filtre provider_organization_id (missions exécutées par la société)"
```

---

## Task 7 : Tests E2E des 4 relations × multi-métiers

**Files :**
- Test : `tests/Feature/Relations/FourRelationsE2ETest.php`

**Principe :** chaque test crée une réservation pour un **métier précis**, ne seede qu'un prestataire du **bon type** **possédant ce métier** (via `trade_user`) dans la zone, lance le matching canonique (`MatchingV2Service` ou le service de dispatch utilisé par le projet — lire comment une réservation obtient son prestataire assigné), et vérifie que la **mission** enregistre le bon `lead_provider_user_id` + le bon `provider_organization_id`.

- [ ] **Step 1 : Écrire les tests**

Créer `tests/Feature/Relations/FourRelationsE2ETest.php` avec un helper de seed (zone + métier + service_catalog lié au métier + prestataire taggé) puis :

```php
<?php

namespace Tests\Feature\Relations;

use App\Enums\ProviderType;
use App\Models\OrganizationAccount;
use App\Models\ProviderProfile;
use App\Models\Trade;
use App\Models\User;
use App\Services\Matching\MatchingV2Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FourRelationsE2ETest extends TestCase
{
    use RefreshDatabase;

    /** Crée un prestataire du type donné, taggé sur $trade, actif+vérifié, dans $zoneId. */
    private function provider(string $type, Trade $trade, int $zoneId, ?int $orgId = null): User
    {
        $user = User::factory()->create(['is_active' => true, 'primary_service_zone_id' => $zoneId]);
        ProviderProfile::create([
            'user_id' => $user->id, 'organization_account_id' => $orgId,
            'provider_type' => $type, 'status' => 'active', 'verification_status' => 'verified',
        ]);
        $user->trades()->syncWithoutDetaching([$trade->id]);

        return $user;
    }

    public function test_c2i_particulier_matche_un_independant_du_bon_metier(): void
    {
        [$zone, $trade, $booking] = $this->seedBookingForTrade(/* customerOrg */ null);
        $indep = $this->provider(ProviderType::INDEPENDENT->value, $trade, $zone->id);

        $best = app(MatchingV2Service::class)->bestCandidate($booking); // adapter au nom réel
        $this->assertSame($indep->id, $best?->id);
        // puis dispatcher → mission ; provider_organization_id doit être null (indépendant)
    }

    // test_c2b_particulier_matche_une_societe : provider company_worker (orgId), mission.provider_organization_id == orgId
    // test_b2i_societe_matche_un_independant : booking.customer_organization_id = clientOrg, provider indépendant, org null
    // test_b2b_societe_matche_une_societe : booking.customer_organization_id = clientOrg, provider company_worker, org == providerOrg

    public function test_negatif_metier_exclut_un_presta_sans_le_metier(): void
    {
        [$zone, $trade, $booking] = $this->seedBookingForTrade(null);
        $autreMetier = Trade::factory()->create();
        $hasTrade = $this->provider(ProviderType::INDEPENDENT->value, $trade, $zone->id);
        $wrongTrade = $this->provider(ProviderType::INDEPENDENT->value, $autreMetier, $zone->id);

        $best = app(MatchingV2Service::class)->bestCandidate($booking);
        $this->assertSame($hasTrade->id, $best?->id);
        $this->assertNotSame($wrongTrade->id, $best?->id);
    }
}
```

Écrire le helper `seedBookingForTrade(?int $customerOrgId)` qui crée : un `Trade`, une `ServiceZone`, un `ServiceCatalog` lié au trade, une `Booking` (`service_catalog_id`, `service_zone_id`, `customer_organization_id`) dans cette zone. Compléter les 4 méthodes C2I/C2B/B2I/B2B + le cas négatif type (réserver, seeder un company_worker, matcher avec préférence `independent` via `EmployeeAvailabilityService::eligibleEmployeesQuery($zone,'independent')`, asserter l'exclusion).

- [ ] **Step 2 : Lancer → itérer jusqu'au vert.** `php artisan test --filter=FourRelationsE2ETest`
Adapter les noms de méthodes du matcher/dispatch (`bestCandidate`/`rankCandidates`/`topN`) et le chemin booking→mission au code réel (lire `MatchingV2Service` + le service de dispatch). Ne pas affaiblir les assertions (bon user + bonne org).

- [ ] **Step 3 : pint + commit**

```bash
vendor/bin/pint tests/Feature/Relations/FourRelationsE2ETest.php
git add tests/Feature/Relations/FourRelationsE2ETest.php
git commit -m "test(relations): E2E C2I/C2B/B2I/B2B + cas négatifs métier/type"
```

---

## Task 8 : Vérification complète + DoD

**Files :** aucun (vérif + éventuel commit pint).

- [ ] **Step 1 : Suite complète + gates**

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=1G --no-progress
```
- Toute la suite verte. **Risque principal** : des tests/seeders créaient des `employe` sans `ProviderProfile` actif+vérifié → la nouvelle éligibilité les exclut. Corriger leurs fixtures (utiliser `User::factory()->employe()` qui crée le profil, ou seeder un `ProviderProfile` actif+vérifié). Ne PAS réintroduire le filtre `role='employe'`.
- **PHPStan run COMPLET** `[OK]` (leçon SP4 : les runs path-scopés masquent des erreurs). Corriger les nouvelles erreurs avec de vraies annotations (`@property-read`, types), sans suppression ni baseline.
- Pint propre.

- [ ] **Step 2 : Confirmer le DoD**
1. Éligibilité unique type×métier×zone×dispo×vérif, sans lire `role`. ✓
2. Inscription presta → matchable + métiers dans `trade_user`. ✓
3. `isClientCompany()` gate sur le type d'org ; routage presta-société prioritaire ; testé. ✓
4. Colonnes booking/mission présentes (migration idempotente) ; migration `trade_id` corrigée. ✓
5. Propagation booking→mission (user+org+team) ; dispatch écrit l'org dérivée ; dashboards filtrent la bonne colonne. ✓
6. 4 E2E (C2I/C2B/B2I/B2B) + négatifs métier/type + inscription + désambiguïsation, verts. ✓
7. Suite + PHPStan full + Pint verts, 0 skip injustifié. ✓

- [ ] **Step 3 : commit (si pint a reformaté)**

```bash
git add -A
git commit -m "chore(relations): pint formatting on SP1 files"
```

---

## Self-review (déjà appliqué)

- **Couverture spec :** éligibilité type×métier → Task 3 (+ métier conservé dans MatchingV2) ; inscription + désambiguïsation → Tasks 4 & 2 ; schéma + propagation + dashboards → Tasks 1, 5, 6 ; tests E2E → Task 7 ; vérif → Task 8. Le choix client (préférence sur la résa) reste SP2 — l'éligibilité expose déjà le paramètre `$providerType` prêt à être branché.
- **Backward-compat explicite :** le passage `role='employe'` → profil actif+vérifié est traité (factory `employe()` Task 3 Step 4 + correction fixtures Task 8) — c'est le risque #1.
- **Cohérence types/noms :** `eligibleEmployeesQuery(?int $zoneId, string $providerType='any')`, `providerTypeValues()`, `provider_organization_id`/`provider_team_id`, `providerOrganization()`, `isClientCompany()` gate type, `attachTrades()` — cohérents entre tasks. Les enums utilisés (`ProviderType::INDEPENDENT/INDIVIDUAL/COMPANY/COMPANY_WORKER`, `OrganizationType::isClient()`) correspondent au code lu.
- **Placeholders :** les seuls points « adapter au code réel » sont les noms de méthodes du matcher/dispatch dans les tests E2E (Task 7) et la signature de `accept()` (Task 5) — à lire au moment de l'implémentation ; tout le reste est du code complet.
