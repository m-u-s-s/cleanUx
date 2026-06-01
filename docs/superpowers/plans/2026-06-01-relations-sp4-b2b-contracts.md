# SP4 — Contrats B2B / partenaires — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rendre fonctionnel le B2B *contractuel* : un contrat-cadre `CLIENT_COMPANY`↔`PROVIDER_COMPANY` qui, dès qu'un membre du client réserve (ou qu'une Work Order est émise), applique un tarif négocié, route vers le partenaire (machinerie SP3), enforce ses policies, et arme un suivi SLA.

**Architecture:** Un **hook contrat** unique et ordonné (`ContractResolver` → `ContractPolicyEnforcer` → `ContractRoutingService` → tarif via `ContractPricingResolver` → dispatch SP3 inchangé → mission + snapshot SLA) inséré dans les 3 chemins de création (`CreateBookingAction` web, `CreateBookingFromApiAction` API/mobile, `PrendreRendezVous` Livewire), **no-op sans contrat**. Le partenaire est un `OrganizationAccount` PROVIDER_COMPANY (aligné SP1-SP3) ; le routage pose `assigned_provider_organization_id` que le dispatch SP3 honore déjà. Le tarif négocié s'applique au `devis_estime` réel (le booking n'utilise PAS PricingEngine v2) ET est branché dans PricingEngine v2 pour l'API quote + les Work Orders. Le monde `ServicePartner` reste legacy/hors-scope.

**Tech Stack:** Laravel 10, Eloquent, Livewire 3, PHPUnit, larastan/PHPStan (full, 0 suppression), Pint ; Expo/RN (booking mobile contract-aware côté serveur).

**Faits terrain vérifiés (à NE PAS re-supposer) :**
- `OrganizationContract` ($fillable réel) a déjà : `organization_account_id` (= l'org cliente), `status` (actif si ∈ `active|signed|pilot` via `isActiveOn()`), `effective_from`/`effective_to` (cast `date`), `approval_mode` (`auto`|`manual`), `requires_purchase_order` (bool), `default_cost_center`, `negotiated_discount_percent` (decimal:2), `sla_response_hours`/`sla_resolution_hours` (int), `allowed_service_catalog_ids` (array), `default_service_partner_id` (legacy, on n'y touche pas), relations `organizationAccount()`, `workOrders()`. **Manque** `provider_organization_id`.
- `OrganizationContract::isActiveOn(?DateTimeInterface $date=null): bool` existe (ligne ~80) — vérifie status ∈ actifs + fenêtre effective.
- `ServiceCatalog` (v1, consommé par le booking) : `id`, `code`, `trade_id`, `base_price`, `name`. `ServiceCatalogV2` (consommé par PricingEngine) : `code`, `base_price_cents`, table `service_catalog_v2`.
- `PricingEngine::quote(string $serviceCode, array $variables=[], ?User $user=null, ?string $idempotencyKey=null, ?int $bookingId=null): PriceQuote` ; rules triées `priority` ASC ; `applied_rules[]` = `{code, priority, price_before_cents, price_after_cents, delta_cents}`. **Le flux booking n'appelle PAS quote()** (il utilise `snapshotFactory->makePricingSnapshot` → `devis_estime`). quote() n'est appelé que par `PricingV2Controller`.
- `CreateBookingAction::execute(User $client, PostalCode $postal, ServiceZone $zone, ServiceCatalog $catalog, ZoneServiceRule $rule, User $assignedEmployee, array $data, ?OrganizationSite $organizationSite=null, ?ZoneCoverageResult $resolution=null): Booking`. Le booking est créé ~ligne 120-180 (pose `assigned_provider_organization_id` depuis `$data` ligne ~129) ; `SmartDispatchService::assignBestEmployee($freshRdv)` ~ligne 266 RE-résout le worker en honorant `assigned_provider_organization_id`. Le pipeline entreprise est déjà déclenché ~ligne 255 via `EnterpriseBookingApprovalService::createForBooking` si `$client->isEntreprise()`/contexte org.
- `CreateBookingFromApiAction::execute(object $user, array $data): Booking` (flux minimal, pas de snapshotFactory ; pose `assigned_provider_organization_id` depuis `$data` ~ligne 65 ; mission ASAP créée ~ligne 84-95).
- `PrendreRendezVous` + trait `app/Support/Livewire/Concerns/Booking/HandlesBookingCreation.php` : `validerRdv()` ; `applyProviderSelectionToBookingData($bookingData)` (SP3, ~ligne 310) pose `assigned_provider_organization_id` dans `$bookingData` avant `bookingCreator()->execute(...)`.
- `EnterpriseBookingApprovalService` : `createForBooking(Booking $rendezVous, ?User $requestedBy=null, ?string $note=null): EnterpriseBookingApproval` (status `pending_manager`, met booking `en_attente`), `approveManager(...)`, `approveFinance(...)` (met booking `confirme`), `reject(...)`.
- `ContractPolicyService` (`app/Services/Enterprise/ContractPolicyService.php`) : `validateBooking(Booking $rdv, OrganizationAccount $org): array` (retourne `['valid'=>bool, 'message'=>?]`), `applyDiscount(Booking $rdv, OrganizationAccount $org): void` (modifie `devis_estime`). **À étendre**, pas réécrire.
- `EnterpriseRoutingService` (`app/Services/Enterprise/EnterpriseRoutingService.php`) : `buildContractSnapshot(...)`, `resolvePriorityZoneIds(...)`, `allowedSiteIdsForUser(...)`. `buildContractSnapshot` n'est appelé nulle part.
- Work Orders : `EnterpriseWorkOrder` ($fillable a `organization_contract_id`, `assigned_field_team_id`, `assigned_service_partner_id`, `service_catalog_id`, `service_zone_id`, `purchase_order_number`, `cost_center`, `budget_amount`, `approval_status`, `status`, relations `lines()`, `approvals()`, `contract()`, `organizationAccount()`, `missions()`, `missionBatches()`) ; `WorkOrderLine` ($fillable : `enterprise_work_order_id`, `service_catalog_id`, `quantity`, `unit_price`, `line_total`, `surface_value`, `unit`, `metadata` — **pas** de `agreed_unit_price`) ; `WorkOrderApproval` (`approval_status` pending/approved/rejected). `EnterpriseWorkOrderMissionGeneratorService` (`app/Services/Missions/`) : `ensureBatchForWorkOrder(EnterpriseWorkOrder): MissionBatch`, `materializePendingMissionsForBatch(MissionBatch): Collection`, `runForApprovedWorkOrder(EnterpriseWorkOrder): array`. Les missions générées passent par `MissionBatch`/`MissionTaskSegment` puis `Mission::create([...])` (~ligne 159-215) — **ne posent pas** `provider_organization_id` ni SLA.
- `Mission` $fillable a déjà : `booking_id`, `rendez_vous_id`, `provider_organization_id`, `organization_account_id`, `lead_provider_user_id`, `lead_employee_id`, `planned_start_at`, `status`, `enterprise_work_order_id`, `mission_batch_id`, `mission_task_segment_id`. **Manque** `organization_contract_id`, `sla_response_due_at`, `sla_resolution_due_at`. Relation `providerOrganization()`, `booking()`.
- `User` (trait `HasOrganizationContext`) : `currentOrganization(): BelongsTo` (FK `current_organization_id`), `organizationMemberships(): HasMany(OrganizationMember)`, `organizationContextId(): ?int` (fallback chain), `membershipIn(?$org=null): ?OrganizationMember`, `hasOrganizationContext(): bool`, `isEntreprise(): bool`.
- `OrganizationMember` : `organization_account_id`, `user_id`, `role`, `status` ('active'…), scope `active()`.
- `OrganizationAccount` : `type` (`OrganizationType` : CLIENT_COMPANY/PROVIDER_COMPANY/PROVIDER_SOLO/HYBRID), relations `users()`, `rating_avg`/`rating_count` (SP3).
- UI : admin `app/Livewire/Admin/B2BOperationsCenter.php` (props `$contractForm` avec déjà `sla_response_hours`, `sla_resolution_hours`, `negotiated_discount_percent` ; vues `resources/views/livewire/admin/b2b/operations/*.blade.php`) ; portail prestataire `app/Livewire/ProviderCompany/DispatchCenter.php` (`getMissionsProperty` filtre `provider_organization_id = current_organization_id`) ; portail client société `app/Livewire/ClientCompany/BookingHub.php` (+ routes `routes/company-dashboards.php`). API mobile : `ClientBookingController::store` → `serialize(Booking): array` (~ligne 358).

**Conventions de gates (chaque tâche) :** TDD (test rouge d'abord). À la fin de chaque tâche : `php artisan test --filter=<ciblé>` vert, `vendor/bin/pint <fichiers>`. La suite complète + PHPStan full + mobile sont le gate de la **Task 13** (vérification finale). Premium éventuel = `CustomerProfile::isPremium`. **Jamais `git add -A`** (Expo `mobile/.expo/*` non-tracké). Commits finissent par `Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>`.

---

### Task 1: Modèle de données (migrations idempotentes + modèles)

**Files:**
- Create: `database/migrations/2026_06_05_000001_add_provider_org_and_contract_links_for_sp4.php`
- Create: `database/migrations/2026_06_05_000002_create_contract_rate_cards_table.php`
- Create: `database/migrations/2026_06_05_000003_create_contract_sla_events_table.php`
- Create: `app/Models/ContractRateCard.php`
- Create: `app/Models/ContractSlaEvent.php`
- Modify: `app/Models/OrganizationContract.php` (relations + fillable)
- Modify: `app/Models/Booking.php` (fillable + relation)
- Modify: `app/Models/Mission.php` (fillable + casts + relations)
- Test: `tests/Feature/Relations/Sp4SchemaTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Relations;

use App\Models\Booking;
use App\Models\ContractRateCard;
use App\Models\ContractSlaEvent;
use App\Models\Mission;
use App\Models\OrganizationAccount;
use App\Models\OrganizationContract;
use App\Models\ServiceCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Sp4SchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_contract_has_provider_organization_and_rate_cards(): void
    {
        $client = OrganizationAccount::factory()->create();
        $provider = OrganizationAccount::factory()->create();
        $service = ServiceCatalog::factory()->create();

        $contract = OrganizationContract::factory()->create([
            'organization_account_id' => $client->id,
            'provider_organization_id' => $provider->id,
        ]);

        $card = ContractRateCard::create([
            'organization_contract_id' => $contract->id,
            'service_catalog_id' => $service->id,
            'negotiated_unit_price_cents' => 1800,
            'currency' => 'EUR',
        ]);

        $this->assertSame($provider->id, $contract->fresh()->providerOrganization->id);
        $this->assertTrue($contract->rateCards->contains('id', $card->id));
    }

    public function test_booking_and_mission_carry_contract_and_sla_columns(): void
    {
        $contract = OrganizationContract::factory()->create();

        $booking = Booking::factory()->create(['organization_contract_id' => $contract->id]);
        $this->assertSame($contract->id, $booking->fresh()->organization_contract_id);

        $mission = Mission::create([
            'booking_id' => $booking->id,
            'status' => 'planned',
            'organization_contract_id' => $contract->id,
            'sla_response_due_at' => now()->addHours(4),
            'sla_resolution_due_at' => now()->addHours(24),
            'planned_start_at' => now(),
        ]);

        $event = ContractSlaEvent::create([
            'mission_id' => $mission->id,
            'organization_contract_id' => $contract->id,
            'kind' => 'response',
            'due_at' => now()->addHours(4),
            'status' => 'pending',
        ]);

        $this->assertNotNull($mission->fresh()->sla_response_due_at);
        $this->assertSame('pending', $event->fresh()->status);
        $this->assertSame($mission->id, $event->mission->id);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=Sp4SchemaTest`
Expected: FAIL (unknown column `provider_organization_id` / class `ContractRateCard` not found).

- [ ] **Step 3: Write the migrations**

`2026_06_05_000001_add_provider_org_and_contract_links_for_sp4.php` :

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('organization_contracts') && ! Schema::hasColumn('organization_contracts', 'provider_organization_id')) {
            Schema::table('organization_contracts', function (Blueprint $table) {
                $table->unsignedBigInteger('provider_organization_id')->nullable()->after('organization_account_id')->index();
            });
        }

        if (Schema::hasTable('bookings') && ! Schema::hasColumn('bookings', 'organization_contract_id')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->unsignedBigInteger('organization_contract_id')->nullable()->index();
            });
        }

        if (Schema::hasTable('missions')) {
            Schema::table('missions', function (Blueprint $table) {
                if (! Schema::hasColumn('missions', 'organization_contract_id')) {
                    $table->unsignedBigInteger('organization_contract_id')->nullable()->index();
                }
                if (! Schema::hasColumn('missions', 'sla_response_due_at')) {
                    $table->dateTime('sla_response_due_at')->nullable();
                }
                if (! Schema::hasColumn('missions', 'sla_resolution_due_at')) {
                    $table->dateTime('sla_resolution_due_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        // Idempotent additive migration — no destructive rollback (cohérent avec le projet).
    }
};
```

`2026_06_05_000002_create_contract_rate_cards_table.php` :

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contract_rate_cards')) {
            return;
        }

        Schema::create('contract_rate_cards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_contract_id')->index();
            $table->unsignedBigInteger('service_catalog_id')->index();
            $table->unsignedBigInteger('negotiated_unit_price_cents');
            $table->string('currency', 3)->default('EUR');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['organization_contract_id', 'service_catalog_id'], 'contract_rate_card_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_rate_cards');
    }
};
```

`2026_06_05_000003_create_contract_sla_events_table.php` :

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contract_sla_events')) {
            return;
        }

        Schema::create('contract_sla_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mission_id')->index();
            $table->unsignedBigInteger('organization_contract_id')->index();
            $table->string('kind', 16); // response | resolution
            $table->dateTime('due_at');
            $table->dateTime('breached_at')->nullable();
            $table->dateTime('escalated_at')->nullable();
            $table->string('status', 16)->default('pending'); // pending | met | breached | escalated
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['mission_id', 'kind'], 'contract_sla_event_unique');
            $table->index(['status', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_sla_events');
    }
};
```

- [ ] **Step 4: Write the models**

`app/Models/ContractRateCard.php` :

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractRateCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_contract_id',
        'service_catalog_id',
        'negotiated_unit_price_cents',
        'currency',
        'metadata',
    ];

    protected $casts = [
        'negotiated_unit_price_cents' => 'integer',
        'metadata' => 'array',
    ];

    /** @return BelongsTo<OrganizationContract, $this> */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(OrganizationContract::class, 'organization_contract_id');
    }

    /** @return BelongsTo<ServiceCatalog, $this> */
    public function serviceCatalog(): BelongsTo
    {
        return $this->belongsTo(ServiceCatalog::class);
    }
}
```

`app/Models/ContractSlaEvent.php` :

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractSlaEvent extends Model
{
    use HasFactory;

    public const KIND_RESPONSE = 'response';
    public const KIND_RESOLUTION = 'resolution';

    public const STATUS_PENDING = 'pending';
    public const STATUS_MET = 'met';
    public const STATUS_BREACHED = 'breached';
    public const STATUS_ESCALATED = 'escalated';

    protected $fillable = [
        'mission_id',
        'organization_contract_id',
        'kind',
        'due_at',
        'breached_at',
        'escalated_at',
        'status',
        'metadata',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'breached_at' => 'datetime',
        'escalated_at' => 'datetime',
        'metadata' => 'array',
    ];

    /** @return BelongsTo<Mission, $this> */
    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    /** @return BelongsTo<OrganizationContract, $this> */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(OrganizationContract::class, 'organization_contract_id');
    }
}
```

- [ ] **Step 5: Wire relations + fillable into existing models**

Dans `app/Models/OrganizationContract.php` : ajoute `'provider_organization_id'` à `$fillable`, et ces relations :

```php
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @return BelongsTo<OrganizationAccount, $this> */
public function providerOrganization(): BelongsTo
{
    return $this->belongsTo(OrganizationAccount::class, 'provider_organization_id');
}

/** @return HasMany<ContractRateCard> */
public function rateCards(): HasMany
{
    return $this->hasMany(ContractRateCard::class);
}
```

Dans `app/Models/Booking.php` : ajoute `'organization_contract_id'` à `$fillable`, et :

```php
/** @return BelongsTo<OrganizationContract, $this> */
public function organizationContract(): BelongsTo
{
    return $this->belongsTo(OrganizationContract::class, 'organization_contract_id');
}
```

Dans `app/Models/Mission.php` : ajoute `'organization_contract_id'`, `'sla_response_due_at'`, `'sla_resolution_due_at'` à `$fillable` ; ajoute aux `$casts` `'sla_response_due_at' => 'datetime'`, `'sla_resolution_due_at' => 'datetime'` ; ajoute :

```php
/** @return BelongsTo<OrganizationContract, $this> */
public function organizationContract(): BelongsTo
{
    return $this->belongsTo(OrganizationContract::class, 'organization_contract_id');
}
```

- [ ] **Step 6: Add factory for ContractRateCard if needed by later tests**

Crée `database/factories/ContractRateCardFactory.php` minimal (les tests ci-dessus utilisent `::create` direct ; la factory sert aux tâches suivantes) :

```php
<?php

namespace Database\Factories;

use App\Models\ContractRateCard;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContractRateCard> */
class ContractRateCardFactory extends Factory
{
    protected $model = ContractRateCard::class;

    public function definition(): array
    {
        return [
            'negotiated_unit_price_cents' => 1800,
            'currency' => 'EUR',
        ];
    }
}
```

Vérifie qu'`OrganizationContract` a une factory avec un état renseignant `provider_organization_id` ou que la factory accepte l'override (les tests le passent en attribut). Si `OrganizationContractFactory` n'existe pas, crée-la minimale (status `active`, `organization_account_id` via `OrganizationAccount::factory()`).

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test --filter=Sp4SchemaTest`
Expected: PASS (2 tests).

- [ ] **Step 8: Pint + commit**

```bash
vendor/bin/pint app/Models/ContractRateCard.php app/Models/ContractSlaEvent.php app/Models/OrganizationContract.php app/Models/Booking.php app/Models/Mission.php database/migrations/2026_06_05_000001_add_provider_org_and_contract_links_for_sp4.php database/migrations/2026_06_05_000002_create_contract_rate_cards_table.php database/migrations/2026_06_05_000003_create_contract_sla_events_table.php database/factories/ContractRateCardFactory.php tests/Feature/Relations/Sp4SchemaTest.php
git add app/Models/ContractRateCard.php app/Models/ContractSlaEvent.php app/Models/OrganizationContract.php app/Models/Booking.php app/Models/Mission.php database/migrations/2026_06_05_000001_add_provider_org_and_contract_links_for_sp4.php database/migrations/2026_06_05_000002_create_contract_rate_cards_table.php database/migrations/2026_06_05_000003_create_contract_sla_events_table.php database/factories/ContractRateCardFactory.php tests/Feature/Relations/Sp4SchemaTest.php
git commit -m "feat(relations): SP4 data model — contract provider org + rate cards + booking/mission contract links + SLA events"
```

---

### Task 2: ContractResolver (détection du contrat applicable)

**Files:**
- Create: `app/Services/Contracts/ContractResolver.php`
- Test: `tests/Feature/Relations/ContractResolverTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Relations;

use App\Models\OrganizationAccount;
use App\Models\OrganizationContract;
use App\Models\ServiceCatalog;
use App\Services\Contracts\ContractResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_active_contract_for_client_org_service_and_date(): void
    {
        $client = OrganizationAccount::factory()->create();
        $provider = OrganizationAccount::factory()->create();
        $service = ServiceCatalog::factory()->create();

        $contract = OrganizationContract::factory()->create([
            'organization_account_id' => $client->id,
            'provider_organization_id' => $provider->id,
            'status' => 'active',
            'effective_from' => now()->subDay()->toDateString(),
            'effective_to' => now()->addMonth()->toDateString(),
            'allowed_service_catalog_ids' => [$service->id],
        ]);

        $resolved = app(ContractResolver::class)
            ->resolveForBooking($client, $service->id, null, now()->toDateString());

        $this->assertNotNull($resolved);
        $this->assertSame($contract->id, $resolved->id);
    }

    public function test_returns_null_when_service_not_allowed(): void
    {
        $client = OrganizationAccount::factory()->create();
        $provider = OrganizationAccount::factory()->create();
        $allowed = ServiceCatalog::factory()->create();
        $other = ServiceCatalog::factory()->create();

        OrganizationContract::factory()->create([
            'organization_account_id' => $client->id,
            'provider_organization_id' => $provider->id,
            'status' => 'active',
            'allowed_service_catalog_ids' => [$allowed->id],
        ]);

        $resolved = app(ContractResolver::class)
            ->resolveForBooking($client, $other->id, null, now()->toDateString());

        $this->assertNull($resolved);
    }

    public function test_returns_null_without_provider_org_or_inactive_or_out_of_window(): void
    {
        $client = OrganizationAccount::factory()->create();
        $service = ServiceCatalog::factory()->create();

        // Pas de provider_organization_id → inutilisable pour le routage.
        OrganizationContract::factory()->create([
            'organization_account_id' => $client->id,
            'provider_organization_id' => null,
            'status' => 'active',
        ]);
        // Statut draft.
        OrganizationContract::factory()->create([
            'organization_account_id' => $client->id,
            'provider_organization_id' => OrganizationAccount::factory()->create()->id,
            'status' => 'draft',
        ]);
        // Hors fenêtre.
        OrganizationContract::factory()->create([
            'organization_account_id' => $client->id,
            'provider_organization_id' => OrganizationAccount::factory()->create()->id,
            'status' => 'active',
            'effective_from' => now()->addMonth()->toDateString(),
            'effective_to' => now()->addMonths(2)->toDateString(),
        ]);

        $resolved = app(ContractResolver::class)
            ->resolveForBooking($client, $service->id, null, now()->toDateString());

        $this->assertNull($resolved);
    }

    public function test_picks_most_recent_when_several_apply(): void
    {
        $client = OrganizationAccount::factory()->create();
        $service = ServiceCatalog::factory()->create();

        $older = OrganizationContract::factory()->create([
            'organization_account_id' => $client->id,
            'provider_organization_id' => OrganizationAccount::factory()->create()->id,
            'status' => 'active',
            'effective_from' => now()->subYear()->toDateString(),
            'allowed_service_catalog_ids' => [],
        ]);
        $newer = OrganizationContract::factory()->create([
            'organization_account_id' => $client->id,
            'provider_organization_id' => OrganizationAccount::factory()->create()->id,
            'status' => 'active',
            'effective_from' => now()->subMonth()->toDateString(),
            'allowed_service_catalog_ids' => [],
        ]);

        $resolved = app(ContractResolver::class)
            ->resolveForBooking($client, $service->id, null, now()->toDateString());

        $this->assertSame($newer->id, $resolved->id);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ContractResolverTest`
Expected: FAIL (class `ContractResolver` not found).

- [ ] **Step 3: Implement ContractResolver**

```php
<?php

namespace App\Services\Contracts;

use App\Models\OrganizationAccount;
use App\Models\OrganizationContract;
use App\Models\User;
use Illuminate\Support\Carbon;

class ContractResolver
{
    /**
     * Contrat-cadre ACTIF applicable pour une org cliente, un service et une date.
     * Critères : status actif (isActiveOn), provider_organization_id renseigné,
     * service ∈ allowed_service_catalog_ids SI la liste est non vide (vide = tous).
     * Multi-contrats : le plus récent (orderByDesc effective_from puis id).
     */
    public function resolveForBooking(
        OrganizationAccount $clientOrg,
        ?int $serviceCatalogId,
        ?int $zoneId,
        string $date,
    ): ?OrganizationContract {
        $at = Carbon::parse($date);

        $candidates = OrganizationContract::query()
            ->where('organization_account_id', $clientOrg->id)
            ->whereNotNull('provider_organization_id')
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get();

        return $candidates->first(function (OrganizationContract $contract) use ($at, $serviceCatalogId) {
            if (! $contract->isActiveOn($at)) {
                return false;
            }

            $allowed = $contract->allowed_service_catalog_ids ?? [];
            if (! empty($allowed) && $serviceCatalogId !== null && ! in_array($serviceCatalogId, $allowed, false)) {
                return false;
            }

            return true;
        });
    }

    /**
     * Variante depuis un User : dérive l'org cliente (current_organization_id /
     * organizationContextId) puis délègue. Soft : null si pas membre d'une org.
     */
    public function resolveForClientUser(
        User $client,
        ?int $serviceCatalogId,
        ?int $zoneId,
        string $date,
    ): ?OrganizationContract {
        $orgId = $client->organizationContextId();
        if (! $orgId) {
            return null;
        }

        $org = OrganizationAccount::find($orgId);
        if (! $org) {
            return null;
        }

        return $this->resolveForBooking($org, $serviceCatalogId, $zoneId, $date);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ContractResolverTest`
Expected: PASS (4 tests).

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint app/Services/Contracts/ContractResolver.php tests/Feature/Relations/ContractResolverTest.php
git add app/Services/Contracts/ContractResolver.php tests/Feature/Relations/ContractResolverTest.php
git commit -m "feat(relations): ContractResolver — active applicable framework contract (window + allowed services), single source for booking + WO"
```

---

### Task 3: ContractPricingResolver (tarif négocié, cœur partagé + intégration PricingEngine)

**Files:**
- Create: `app/Services/Contracts/ContractPricingResolver.php`
- Modify: `app/Services/PricingV2/PricingEngine.php` (hook contrat-scopé prioritaire)
- Test: `tests/Feature/Relations/ContractPricingResolverTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Relations;

use App\Models\ContractRateCard;
use App\Models\OrganizationAccount;
use App\Models\OrganizationContract;
use App\Models\ServiceCatalog;
use App\Services\Contracts\ContractPricingResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractPricingResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_rate_card_overrides_base_price(): void
    {
        $contract = OrganizationContract::factory()->create([
            'provider_organization_id' => OrganizationAccount::factory()->create()->id,
            'negotiated_discount_percent' => 10,
        ]);
        $service = ServiceCatalog::factory()->create();
        ContractRateCard::create([
            'organization_contract_id' => $contract->id,
            'service_catalog_id' => $service->id,
            'negotiated_unit_price_cents' => 1800,
            'currency' => 'EUR',
        ]);

        $result = app(ContractPricingResolver::class)
            ->resolveCents($contract, $service->id, 2500);

        // Grille prioritaire : prix unitaire négocié, PAS la remise %.
        $this->assertSame(1800, $result['price_cents']);
        $this->assertSame('contract:rate_card', $result['label']);
    }

    public function test_falls_back_to_discount_percent_without_rate_card(): void
    {
        $contract = OrganizationContract::factory()->create([
            'provider_organization_id' => OrganizationAccount::factory()->create()->id,
            'negotiated_discount_percent' => 20,
        ]);
        $service = ServiceCatalog::factory()->create();

        $result = app(ContractPricingResolver::class)
            ->resolveCents($contract, $service->id, 2500);

        // 2500 - 20% = 2000.
        $this->assertSame(2000, $result['price_cents']);
        $this->assertSame('contract:discount', $result['label']);
    }

    public function test_no_op_without_rate_card_and_zero_discount(): void
    {
        $contract = OrganizationContract::factory()->create([
            'provider_organization_id' => OrganizationAccount::factory()->create()->id,
            'negotiated_discount_percent' => 0,
        ]);
        $service = ServiceCatalog::factory()->create();

        $result = app(ContractPricingResolver::class)
            ->resolveCents($contract, $service->id, 2500);

        $this->assertSame(2500, $result['price_cents']);
        $this->assertNull($result['label']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ContractPricingResolverTest`
Expected: FAIL (class not found).

- [ ] **Step 3: Implement ContractPricingResolver**

```php
<?php

namespace App\Services\Contracts;

use App\Models\ContractRateCard;
use App\Models\OrganizationContract;

class ContractPricingResolver
{
    /**
     * Applique le tarif négocié d'un contrat à un prix de base EN CENTS.
     * Grille (ContractRateCard) prioritaire → sinon remise negotiated_discount_percent
     * → sinon no-op. Retourne le prix résultant + un label de traçabilité.
     *
     * @return array{price_cents:int, label:?string}
     */
    public function resolveCents(OrganizationContract $contract, ?int $serviceCatalogId, int $baseCents): array
    {
        if ($serviceCatalogId !== null) {
            $card = ContractRateCard::query()
                ->where('organization_contract_id', $contract->id)
                ->where('service_catalog_id', $serviceCatalogId)
                ->first();

            if ($card) {
                return ['price_cents' => (int) $card->negotiated_unit_price_cents, 'label' => 'contract:rate_card'];
            }
        }

        $discount = (float) ($contract->negotiated_discount_percent ?? 0);
        if ($discount > 0) {
            $discounted = (int) round($baseCents * (1 - $discount / 100));

            return ['price_cents' => max(0, $discounted), 'label' => 'contract:discount'];
        }

        return ['price_cents' => $baseCents, 'label' => null];
    }

    /**
     * Variante en unités décimales (€) pour le chemin booking (devis_estime).
     *
     * @return array{amount:float, label:?string}
     */
    public function resolveAmount(OrganizationContract $contract, ?int $serviceCatalogId, float $baseAmount): array
    {
        $res = $this->resolveCents($contract, $serviceCatalogId, (int) round($baseAmount * 100));

        return ['amount' => $res['price_cents'] / 100, 'label' => $res['label']];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ContractPricingResolverTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Integrate into PricingEngine v2 (contract-scoped adjustment, traced)**

Lis `app/Services/PricingV2/PricingEngine.php::quote()`. Le contrat applicable est passé via une variable de contexte `__contract_id` dans `$variables` (le WO et l'API quote pourront la fournir). APRÈS le clamp final (`$currentPrice = $this->clamp(...)`) et AVANT le `PriceQuote::create(...)`, insère :

```php
// SP4 — adjustment contrat-scopé prioritaire (grille → remise), tracé.
$contractId = $variables['__contract_id'] ?? null;
if ($contractId) {
    $contract = \App\Models\OrganizationContract::find((int) $contractId);
    if ($contract) {
        $serviceCatalogId = isset($variables['__service_catalog_id']) ? (int) $variables['__service_catalog_id'] : null;
        $before = $currentPrice;
        $res = app(\App\Services\Contracts\ContractPricingResolver::class)
            ->resolveCents($contract, $serviceCatalogId, $currentPrice);
        if ($res['label'] !== null) {
            $currentPrice = $res['price_cents'];
            $appliedRules[] = [
                'code' => $res['label'],
                'priority' => -1, // prioritaire / dernier mot contractuel
                'price_before_cents' => $before,
                'price_after_cents' => $currentPrice,
                'delta_cents' => $currentPrice - $before,
            ];
        }
    }
}
```

(Adapte les noms de variables locales `$currentPrice`/`$appliedRules` à ceux réellement utilisés dans `quote()`.) Ajoute `__contract_id` et `__service_catalog_id` à la whitelist de `sanitizeVariables` si la whitelist supprime les clés inconnues — OU fais en sorte que le hook lise ces clés depuis le `$variables` ORIGINAL (avant sanitize), pour ne pas dépendre de la config. Préfère lire depuis l'original.

- [ ] **Step 6: Add a PricingEngine integration test**

Ajoute à `ContractPricingResolverTest` un test qui appelle `app(PricingEngine::class)->quote($serviceV2Code, ['__contract_id'=>$contract->id,'__service_catalog_id'=>$service->id], ...)` et asserte que `applied_rules` contient un élément `code='contract:rate_card'` et que `computed_price_cents` = prix négocié. Monte un `ServiceCatalogV2` avec `code` + `base_price_cents`. Si PricingEngine est désactivé en test (`config pricing_v2.enabled`), force `config(['pricing_v2.enabled' => true])` dans le test.

- [ ] **Step 7: Run + commit**

```bash
php artisan test --filter=ContractPricingResolverTest
vendor/bin/pint app/Services/Contracts/ContractPricingResolver.php app/Services/PricingV2/PricingEngine.php tests/Feature/Relations/ContractPricingResolverTest.php
git add app/Services/Contracts/ContractPricingResolver.php app/Services/PricingV2/PricingEngine.php tests/Feature/Relations/ContractPricingResolverTest.php
git commit -m "feat(relations): ContractPricingResolver (rate card -> discount fallback) + wired into PricingEngine v2 (traced in price_quotes)"
```

---

### Task 4: ContractRoutingService (routage préféré + repli)

**Files:**
- Create: `app/Services/Contracts/ContractRoutingService.php`
- Test: `tests/Feature/Relations/ContractRoutingServiceTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Relations;

use App\Models\OrganizationAccount;
use App\Models\OrganizationContract;
use App\Services\Contracts\ContractRoutingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractRoutingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_applies_partner_org_and_contract_to_booking_data(): void
    {
        $provider = OrganizationAccount::factory()->create();
        $contract = OrganizationContract::factory()->create([
            'provider_organization_id' => $provider->id,
        ]);

        $data = ['service_catalog_id' => 1];
        $out = app(ContractRoutingService::class)->applyToBookingData($data, $contract);

        $this->assertSame($provider->id, $out['assigned_provider_organization_id']);
        $this->assertSame($contract->id, $out['organization_contract_id']);
    }

    public function test_does_not_override_explicit_client_choice(): void
    {
        $provider = OrganizationAccount::factory()->create();
        $otherOrg = OrganizationAccount::factory()->create();
        $contract = OrganizationContract::factory()->create([
            'provider_organization_id' => $provider->id,
        ]);

        // Le client a explicitement choisi une AUTRE org (SP3) → le contrat ne l'écrase pas,
        // mais stampe quand même le contrat (traçabilité).
        $data = ['assigned_provider_organization_id' => $otherOrg->id];
        $out = app(ContractRoutingService::class)->applyToBookingData($data, $contract);

        $this->assertSame($otherOrg->id, $out['assigned_provider_organization_id']);
        $this->assertSame($contract->id, $out['organization_contract_id']);
    }

    public function test_does_not_override_explicit_preferred_provider(): void
    {
        $provider = OrganizationAccount::factory()->create();
        $contract = OrganizationContract::factory()->create([
            'provider_organization_id' => $provider->id,
        ]);

        $data = ['preferred_provider_user_id' => 999];
        $out = app(ContractRoutingService::class)->applyToBookingData($data, $contract);

        // Un presta précis choisi (SP2) prime : pas d'org de contrat imposée.
        $this->assertArrayNotHasKey('assigned_provider_organization_id', $out);
        $this->assertSame($contract->id, $out['organization_contract_id']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ContractRoutingServiceTest`
Expected: FAIL (class not found).

- [ ] **Step 3: Implement ContractRoutingService**

```php
<?php

namespace App\Services\Contracts;

use App\Models\OrganizationContract;

class ContractRoutingService
{
    /**
     * Pose le routage contractuel dans les DATA d'un booking (avant création).
     * Le contrat est le DÉFAUT : il n'écrase NI un choix d'org explicite (SP3)
     * NI un presta précis (SP2). Il stampe toujours organization_contract_id.
     * Le dispatch SP3 honore ensuite assigned_provider_organization_id (repli
     * partenaire indispo via PreferredCompanyResolver + marché ouvert).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function applyToBookingData(array $data, OrganizationContract $contract): array
    {
        $data['organization_contract_id'] = $contract->id;

        $hasExplicitOrg = ! empty($data['assigned_provider_organization_id']);
        $hasExplicitProvider = ! empty($data['preferred_provider_user_id']);

        if (! $hasExplicitOrg && ! $hasExplicitProvider && $contract->provider_organization_id) {
            $data['assigned_provider_organization_id'] = (int) $contract->provider_organization_id;
        }

        return $data;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ContractRoutingServiceTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint app/Services/Contracts/ContractRoutingService.php tests/Feature/Relations/ContractRoutingServiceTest.php
git add app/Services/Contracts/ContractRoutingService.php tests/Feature/Relations/ContractRoutingServiceTest.php
git commit -m "feat(relations): ContractRoutingService — default partner routing without overriding explicit client choice"
```

---

### Task 5: ContractPolicyEnforcer (policies dures)

**Files:**
- Create: `app/Services/Contracts/ContractPolicyEnforcer.php`
- Create: `app/Exceptions/ContractPolicyException.php`
- Test: `tests/Feature/Relations/ContractPolicyEnforcerTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Relations;

use App\Exceptions\ContractPolicyException;
use App\Models\OrganizationAccount;
use App\Models\OrganizationContract;
use App\Models\ServiceCatalog;
use App\Services\Contracts\ContractPolicyEnforcer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractPolicyEnforcerTest extends TestCase
{
    use RefreshDatabase;

    private function contract(array $attrs = []): OrganizationContract
    {
        return OrganizationContract::factory()->create(array_merge([
            'provider_organization_id' => OrganizationAccount::factory()->create()->id,
            'status' => 'active',
        ], $attrs));
    }

    public function test_blocks_when_service_not_allowed(): void
    {
        $allowed = ServiceCatalog::factory()->create();
        $other = ServiceCatalog::factory()->create();
        $contract = $this->contract(['allowed_service_catalog_ids' => [$allowed->id]]);

        $this->expectException(ContractPolicyException::class);
        app(ContractPolicyEnforcer::class)->enforceForBookingData(
            ['service_catalog_id' => $other->id],
            $contract,
        );
    }

    public function test_blocks_when_po_required_and_missing(): void
    {
        $contract = $this->contract(['requires_purchase_order' => true]);

        $this->expectException(ContractPolicyException::class);
        app(ContractPolicyEnforcer::class)->enforceForBookingData(
            ['service_catalog_id' => ServiceCatalog::factory()->create()->id],
            $contract,
        );
    }

    public function test_forces_default_cost_center_when_absent(): void
    {
        $contract = $this->contract(['default_cost_center' => 'CC-42']);

        $out = app(ContractPolicyEnforcer::class)->enforceForBookingData(
            ['service_catalog_id' => ServiceCatalog::factory()->create()->id],
            $contract,
        );

        $this->assertSame('CC-42', $out['cost_center']);
    }

    public function test_flags_manual_approval(): void
    {
        $contract = $this->contract(['approval_mode' => 'manual']);

        $out = app(ContractPolicyEnforcer::class)->enforceForBookingData(
            ['service_catalog_id' => ServiceCatalog::factory()->create()->id, 'purchase_order_number' => 'PO-1'],
            $contract,
        );

        $this->assertTrue($out['entreprise_approval_required']);
    }

    public function test_passes_when_all_satisfied(): void
    {
        $service = ServiceCatalog::factory()->create();
        $contract = $this->contract([
            'allowed_service_catalog_ids' => [$service->id],
            'requires_purchase_order' => true,
            'approval_mode' => 'auto',
        ]);

        $out = app(ContractPolicyEnforcer::class)->enforceForBookingData(
            ['service_catalog_id' => $service->id, 'purchase_order_number' => 'PO-9'],
            $contract,
        );

        $this->assertArrayNotHasKey('entreprise_approval_required', $out);
        $this->assertSame('PO-9', $out['purchase_order_number']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ContractPolicyEnforcerTest`
Expected: FAIL (classes not found).

- [ ] **Step 3: Implement exception + enforcer**

`app/Exceptions/ContractPolicyException.php` :

```php
<?php

namespace App\Exceptions;

use RuntimeException;

class ContractPolicyException extends RuntimeException
{
}
```

`app/Services/Contracts/ContractPolicyEnforcer.php` :

```php
<?php

namespace App\Services\Contracts;

use App\Exceptions\ContractPolicyException;
use App\Models\OrganizationContract;
use Illuminate\Support\Arr;

class ContractPolicyEnforcer
{
    /**
     * Enforce les policies dures d'un contrat sur les DATA d'un booking.
     * Hard-fail (ContractPolicyException) : service non autorisé, PO requis manquant.
     * Soft : cost center défaut forcé, approbation manuelle signalée
     * (entreprise_approval_required) pour que le chemin de création route vers
     * EnterpriseBookingApprovalService.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function enforceForBookingData(array $data, OrganizationContract $contract): array
    {
        $serviceCatalogId = Arr::get($data, 'service_catalog_id');

        $allowed = $contract->allowed_service_catalog_ids ?? [];
        if (! empty($allowed) && $serviceCatalogId !== null && ! in_array((int) $serviceCatalogId, $allowed, false)) {
            throw new ContractPolicyException('Ce service n’est pas couvert par votre contrat.');
        }

        if ($contract->requires_purchase_order && blank(Arr::get($data, 'purchase_order_number'))) {
            throw new ContractPolicyException('Un numéro de bon de commande (PO) est requis par votre contrat.');
        }

        if (filled($contract->default_cost_center) && blank(Arr::get($data, 'cost_center'))) {
            $data['cost_center'] = $contract->default_cost_center;
        }

        if ($contract->approval_mode === 'manual') {
            $data['entreprise_approval_required'] = true;
        }

        return $data;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ContractPolicyEnforcerTest`
Expected: PASS (5 tests).

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint app/Services/Contracts/ContractPolicyEnforcer.php app/Exceptions/ContractPolicyException.php tests/Feature/Relations/ContractPolicyEnforcerTest.php
git add app/Services/Contracts/ContractPolicyEnforcer.php app/Exceptions/ContractPolicyException.php tests/Feature/Relations/ContractPolicyEnforcerTest.php
git commit -m "feat(relations): ContractPolicyEnforcer — allowed services, blocking PO, forced cost center, manual-approval flag"
```

---

### Task 6: Hook contrat unifié + intégration dans les 3 chemins de création

**Files:**
- Create: `app/Services/Contracts/ContractBookingHook.php`
- Modify: `app/Services/Booking/CreateBookingAction.php`
- Modify: `app/Actions/Booking/CreateBookingFromApiAction.php`
- Modify: `app/Support/Livewire/Concerns/Booking/HandlesBookingCreation.php`
- Test: `tests/Feature/Relations/ContractBookingHookTest.php`

Ce hook orchestre Resolver → Policy → Routing → (signal pricing). Il opère sur le tableau `$data` (mutations) AVANT la création du booking. Le tarif négocié au `devis_estime` est appliqué dans la même passe (le hook expose le contrat + le label pour que le chemin l'applique au devis).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Relations;

use App\Exceptions\ContractPolicyException;
use App\Models\OrganizationAccount;
use App\Models\OrganizationContract;
use App\Models\OrganizationMember;
use App\Models\ServiceCatalog;
use App\Models\User;
use App\Services\Contracts\ContractBookingHook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractBookingHookTest extends TestCase
{
    use RefreshDatabase;

    private function clientUserInOrg(OrganizationAccount $org): User
    {
        $u = User::factory()->create(['current_organization_id' => $org->id]);
        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $u->id,
            'role' => 'member',
            'status' => 'active',
        ]);

        return $u;
    }

    public function test_hook_applies_routing_and_pricing_for_contracted_client(): void
    {
        $clientOrg = OrganizationAccount::factory()->create();
        $provider = OrganizationAccount::factory()->create();
        $service = ServiceCatalog::factory()->create();
        $contract = OrganizationContract::factory()->create([
            'organization_account_id' => $clientOrg->id,
            'provider_organization_id' => $provider->id,
            'status' => 'active',
            'negotiated_discount_percent' => 20,
            'allowed_service_catalog_ids' => [$service->id],
        ]);

        $client = $this->clientUserInOrg($clientOrg);

        $data = ['service_catalog_id' => $service->id, 'devis_estime' => 100.0];
        $out = app(ContractBookingHook::class)->apply($client, $data, now()->toDateString());

        $this->assertSame($provider->id, $out['assigned_provider_organization_id']);
        $this->assertSame($contract->id, $out['organization_contract_id']);
        $this->assertSame(80.0, $out['devis_estime']); // 100 - 20%
    }

    public function test_hook_is_noop_without_contract(): void
    {
        $client = User::factory()->create(); // pas d'org
        $data = ['service_catalog_id' => 1, 'devis_estime' => 100.0];

        $out = app(ContractBookingHook::class)->apply($client, $data, now()->toDateString());

        $this->assertSame($data, $out);
    }

    public function test_hook_blocks_when_po_required_and_missing(): void
    {
        $clientOrg = OrganizationAccount::factory()->create();
        $contract = OrganizationContract::factory()->create([
            'organization_account_id' => $clientOrg->id,
            'provider_organization_id' => OrganizationAccount::factory()->create()->id,
            'status' => 'active',
            'requires_purchase_order' => true,
        ]);
        $client = $this->clientUserInOrg($clientOrg);

        $this->expectException(ContractPolicyException::class);
        app(ContractBookingHook::class)->apply(
            $client,
            ['service_catalog_id' => ServiceCatalog::factory()->create()->id, 'devis_estime' => 100.0],
            now()->toDateString(),
        );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ContractBookingHookTest`
Expected: FAIL (class not found).

- [ ] **Step 3: Implement ContractBookingHook**

```php
<?php

namespace App\Services\Contracts;

use App\Models\User;
use Illuminate\Support\Arr;

class ContractBookingHook
{
    public function __construct(
        private ContractResolver $resolver,
        private ContractPolicyEnforcer $policy,
        private ContractRoutingService $routing,
        private ContractPricingResolver $pricing,
    ) {}

    /**
     * Hook contrat unifié, appliqué sur $data AVANT création du booking.
     * No-op si pas de contrat applicable. Lève ContractPolicyException sur
     * violation dure (service non autorisé, PO manquant).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function apply(User $client, array $data, string $date): array
    {
        $serviceCatalogId = Arr::get($data, 'service_catalog_id');
        $zoneId = Arr::get($data, 'service_zone_id');

        $contract = $this->resolver->resolveForClientUser(
            $client,
            $serviceCatalogId !== null ? (int) $serviceCatalogId : null,
            $zoneId !== null ? (int) $zoneId : null,
            $date,
        );

        if (! $contract) {
            return $data;
        }

        $data = $this->policy->enforceForBookingData($data, $contract);
        $data = $this->routing->applyToBookingData($data, $contract);

        if (isset($data['devis_estime'])) {
            $res = $this->pricing->resolveAmount(
                $contract,
                $serviceCatalogId !== null ? (int) $serviceCatalogId : null,
                (float) $data['devis_estime'],
            );
            if ($res['label'] !== null) {
                $data['devis_estime'] = $res['amount'];
                $data['contract_price_label'] = $res['label'];
            }
        }

        return $data;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ContractBookingHookTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Wire the hook into the 3 creation paths**

**(a) `CreateBookingFromApiAction::execute` (API/mobile)** — c'est le point serveur unique du booking mobile. Lis la méthode ; AVANT la construction du `Booking::create([...])` (~ligne 32-66), applique le hook sur `$data` :

```php
// SP4 — hook contrat (no-op sans contrat). $user est le client.
$date = (string) ($data['date'] ?? $data['scheduled_date'] ?? now()->toDateString());
$data = app(\App\Services\Contracts\ContractBookingHook::class)->apply($user, $data, $date);
```

Assure-toi que `assigned_provider_organization_id`, `organization_contract_id`, `cost_center`, `purchase_order_number`, `devis_estime` issus de `$data` sont bien persistés dans le `Booking::create` (ajoute les clés manquantes au tableau de création, en lisant via `Arr::get($data, ...)`). Si `entreprise_approval_required` est posé, laisse le code entreprise existant le consommer (ou route vers `EnterpriseBookingApprovalService::createForBooking` comme `CreateBookingAction` le fait).

**(b) `CreateBookingAction::execute` (web/société)** — Lis la séquence. Le `$data` arrive déjà construit par l'appelant. Applique le hook EN TÊTE de `execute` (après extraction de `$client`), AVANT le `Booking::create` et AVANT le calcul du devis final, pour que `assigned_provider_organization_id` + `devis_estime` négocié soient pris en compte par la création et par `SmartDispatchService::assignBestEmployee` (qui honore l'org) :

```php
// SP4 — hook contrat en amont (no-op sans contrat).
$date = (string) ($data['date'] ?? $catalog ? ($data['date'] ?? now()->toDateString()) : now()->toDateString());
$data = app(\App\Services\Contracts\ContractBookingHook::class)->apply($client, $data, (string) ($data['date'] ?? now()->toDateString()));
```

Vérifie que la valeur `devis_estime` du hook n'est pas écrasée par le snapshot de pricing en aval : si `CreateBookingAction` calcule `$adjustedEstimate` depuis le snapshot, applique le tarif contrat APRÈS ce calcul. Le plus robuste : si `Arr::get($data, 'contract_price_label')` est présent, ré-appliquer `ContractPricingResolver::resolveAmount` sur l'estimation finale juste avant `Booking::create`. Documente ce choix dans un commentaire.

**(c) `PrendreRendezVous` / `HandlesBookingCreation::validerRdv`** — Lis `applyProviderSelectionToBookingData($bookingData)` (SP3). Juste APRÈS son appel et AVANT `bookingCreator()->execute(...)`, applique le hook sur `$bookingData` :

```php
// SP4 — hook contrat (après la sélection SP2/SP3, avant création).
$bookingData = app(\App\Services\Contracts\ContractBookingHook::class)
    ->apply($client, $bookingData, (string) ($bookingData['date'] ?? now()->toDateString()));
```

Gère la `ContractPolicyException` : capture-la et expose un message d'erreur Livewire (`$this->addError('po', $e->getMessage())` ou équivalent au champ pertinent) au lieu de planter.

- [ ] **Step 6: Add an end-to-end web booking test proving persistence**

Crée `tests/Feature/Relations/ContractAwareBookingTest.php` : un membre d'une `CLIENT_COMPANY` sous contrat actif (provider org éligible avec un worker dispo, calque `CreatesZoneAwareFixtures` + le pattern `companyWorker` de `EligibleCompaniesResolverTest`) crée une réservation via `PrendreRendezVous` (calque `BrowseCompaniesSelectionTest` e2e de SP3). Assert : `assertDatabaseHas('bookings', ['organization_contract_id'=>$contract->id, 'assigned_provider_organization_id'=>$provider->id])` + `devis_estime` reflète le tarif négocié. Un cas : `requires_purchase_order=true` sans PO → la réservation est refusée (erreur, pas de booking). Si monter le e2e web complet est trop lourd, replie sur un appel direct à `ContractBookingHook::apply` suivi de `CreateBookingFromApiAction::execute` et asserte la persistance.

- [ ] **Step 7: Run + commit**

```bash
php artisan test --filter='ContractBookingHook|ContractAwareBooking'
vendor/bin/pint app/Services/Contracts/ContractBookingHook.php app/Services/Booking/CreateBookingAction.php app/Actions/Booking/CreateBookingFromApiAction.php app/Support/Livewire/Concerns/Booking/HandlesBookingCreation.php tests/Feature/Relations/ContractBookingHookTest.php tests/Feature/Relations/ContractAwareBookingTest.php
git add app/Services/Contracts/ContractBookingHook.php app/Services/Booking/CreateBookingAction.php app/Actions/Booking/CreateBookingFromApiAction.php app/Support/Livewire/Concerns/Booking/HandlesBookingCreation.php tests/Feature/Relations/ContractBookingHookTest.php tests/Feature/Relations/ContractAwareBookingTest.php
git commit -m "feat(relations): unified contract hook wired into the 3 booking creation paths (resolve/policy/route/price, no-op without contract)"
```

---

### Task 7: SLA snapshot à la création de mission + ContractSlaMonitor + commande

**Files:**
- Create: `app/Services/Contracts/ContractSlaService.php`
- Create: `app/Console/Commands/ScanContractSla.php`
- Modify: le service de création de mission depuis booking (à localiser : `app/Services/Dispatch/MissionDispatchService.php` et/ou là où `Mission::create([...'booking_id'...])` se fait pour un booking) pour armer le snapshot.
- Modify: `app/Console/Kernel.php` (planification)
- Test: `tests/Feature/Relations/ContractSlaTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Relations;

use App\Models\ContractSlaEvent;
use App\Models\Mission;
use App\Models\OrganizationAccount;
use App\Models\OrganizationContract;
use App\Services\Contracts\ContractSlaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractSlaTest extends TestCase
{
    use RefreshDatabase;

    private function missionUnderContract(int $responseHours, int $resolutionHours): Mission
    {
        $contract = OrganizationContract::factory()->create([
            'provider_organization_id' => OrganizationAccount::factory()->create()->id,
            'sla_response_hours' => $responseHours,
            'sla_resolution_hours' => $resolutionHours,
        ]);

        return Mission::create([
            'status' => 'planned',
            'organization_contract_id' => $contract->id,
            'planned_start_at' => now()->addDay(),
        ]);
    }

    public function test_arming_sla_sets_due_dates_and_pending_events(): void
    {
        $mission = $this->missionUnderContract(4, 24);

        app(ContractSlaService::class)->armForMission($mission);

        $mission->refresh();
        $this->assertNotNull($mission->sla_response_due_at);
        $this->assertNotNull($mission->sla_resolution_due_at);
        $this->assertSame(2, ContractSlaEvent::where('mission_id', $mission->id)->where('status', 'pending')->count());
    }

    public function test_scan_marks_breached_and_escalates_once(): void
    {
        $mission = $this->missionUnderContract(4, 24);
        app(ContractSlaService::class)->armForMission($mission);

        // Force l'échéance dans le passé sans satisfaction.
        ContractSlaEvent::where('mission_id', $mission->id)->update(['due_at' => now()->subHour()]);

        app(ContractSlaService::class)->scan();
        $this->assertSame(2, ContractSlaEvent::where('mission_id', $mission->id)->where('status', 'escalated')->count());

        // Idempotent : un 2e scan ne ré-escalade pas (escalated_at déjà posé).
        $before = ContractSlaEvent::where('mission_id', $mission->id)->pluck('escalated_at')->toArray();
        app(ContractSlaService::class)->scan();
        $after = ContractSlaEvent::where('mission_id', $mission->id)->pluck('escalated_at')->toArray();
        $this->assertEquals($before, $after);
    }

    public function test_scan_marks_met_when_resolved_before_due(): void
    {
        $mission = $this->missionUnderContract(4, 24);
        app(ContractSlaService::class)->armForMission($mission);

        $mission->update(['status' => 'termine', 'actual_end_at' => now()]);

        app(ContractSlaService::class)->scan();

        $this->assertSame('met', ContractSlaEvent::where('mission_id', $mission->id)->where('kind', 'resolution')->value('status'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ContractSlaTest`
Expected: FAIL (class not found).

- [ ] **Step 3: Implement ContractSlaService**

```php
<?php

namespace App\Services\Contracts;

use App\Models\ContractSlaEvent;
use App\Models\Mission;
use App\Models\OrganizationContract;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ContractSlaService
{
    /**
     * Arme le snapshot SLA d'une mission sous contrat (idempotent par mission+kind).
     * Soft-fail : ne casse jamais la création de mission.
     */
    public function armForMission(Mission $mission): void
    {
        try {
            if (! $mission->organization_contract_id) {
                return;
            }

            $contract = OrganizationContract::find($mission->organization_contract_id);
            if (! $contract) {
                return;
            }

            $base = $mission->created_at ? Carbon::parse($mission->created_at) : now();
            $start = $mission->planned_start_at ? Carbon::parse($mission->planned_start_at) : $base;

            $responseDue = $contract->sla_response_hours ? $base->copy()->addHours((int) $contract->sla_response_hours) : null;
            $resolutionDue = $contract->sla_resolution_hours ? $start->copy()->addHours((int) $contract->sla_resolution_hours) : null;

            $mission->forceFill([
                'sla_response_due_at' => $responseDue,
                'sla_resolution_due_at' => $resolutionDue,
            ])->save();

            if ($responseDue) {
                ContractSlaEvent::updateOrCreate(
                    ['mission_id' => $mission->id, 'kind' => ContractSlaEvent::KIND_RESPONSE],
                    ['organization_contract_id' => $contract->id, 'due_at' => $responseDue, 'status' => ContractSlaEvent::STATUS_PENDING],
                );
            }
            if ($resolutionDue) {
                ContractSlaEvent::updateOrCreate(
                    ['mission_id' => $mission->id, 'kind' => ContractSlaEvent::KIND_RESOLUTION],
                    ['organization_contract_id' => $contract->id, 'due_at' => $resolutionDue, 'status' => ContractSlaEvent::STATUS_PENDING],
                );
            }
        } catch (\Throwable $e) {
            Log::warning('SLA arming failed for mission '.$mission->id.': '.$e->getMessage());
        }
    }

    /**
     * Scanne les événements SLA pending : met si satisfait avant échéance,
     * breached/escalated si dépassé. Idempotent (escalade une seule fois).
     */
    public function scan(): void
    {
        ContractSlaEvent::query()
            ->where('status', ContractSlaEvent::STATUS_PENDING)
            ->with('mission')
            ->chunkById(200, function ($events) {
                foreach ($events as $event) {
                    try {
                        $this->scanOne($event);
                    } catch (\Throwable $e) {
                        Log::warning('SLA scan failed for event '.$event->id.': '.$e->getMessage());
                    }
                }
            });
    }

    private function scanOne(ContractSlaEvent $event): void
    {
        $mission = $event->mission;
        if (! $mission) {
            return;
        }

        $satisfied = $event->kind === ContractSlaEvent::KIND_RESPONSE
            ? in_array($mission->status, ['assigned', 'en_route', 'sur_place', 'termine'], true)
            : in_array($mission->status, ['termine'], true) || $mission->actual_end_at !== null;

        if ($satisfied) {
            $event->update(['status' => ContractSlaEvent::STATUS_MET]);

            return;
        }

        if (now()->greaterThan($event->due_at)) {
            $event->update([
                'status' => ContractSlaEvent::STATUS_ESCALATED,
                'breached_at' => $event->breached_at ?? now(),
                'escalated_at' => now(),
            ]);
            $this->escalate($event);
        }
    }

    private function escalate(ContractSlaEvent $event): void
    {
        // Réutilise le système de notifications existant (soft). À brancher sur
        // les responsables de l'org partenaire / cliente. Volontairement minimal.
        Log::info('SLA breach escalated', ['event_id' => $event->id, 'mission_id' => $event->mission_id, 'kind' => $event->kind]);
    }
}
```

- [ ] **Step 4: Implement the artisan command**

`app/Console/Commands/ScanContractSla.php` :

```php
<?php

namespace App\Console\Commands;

use App\Services\Contracts\ContractSlaService;
use Illuminate\Console\Command;

class ScanContractSla extends Command
{
    protected $signature = 'contract:scan-sla';

    protected $description = 'Scan contract SLA events: mark met / breached and escalate once.';

    public function handle(ContractSlaService $service): int
    {
        $service->scan();
        $this->info('Contract SLA scan complete.');

        return self::SUCCESS;
    }
}
```

Planifie dans `app/Console/Kernel.php::schedule()` : `$schedule->command('contract:scan-sla')->everyFifteenMinutes();` (calque la syntaxe des commandes déjà planifiées, ex. `presence:scan-stale`).

- [ ] **Step 5: Arm SLA at mission creation**

Localise le(s) endroit(s) où une `Mission` est créée pour un booking (grep `Mission::create(` dans `app/Services/Dispatch/MissionDispatchService.php`, `app/Actions/Booking/CreateBookingFromApiAction.php`, et tout `MissionService`). Après chaque création de mission issue d'un booking, copie le contrat depuis le booking et arme le SLA :

```php
// SP4 — propage le contrat du booking à la mission + arme le SLA (soft).
if ($booking->organization_contract_id) {
    $mission->forceFill(['organization_contract_id' => $booking->organization_contract_id])->save();
    app(\App\Services\Contracts\ContractSlaService::class)->armForMission($mission);
}
```

(Pour le chemin WO, l'armement se fait en Task 8.)

- [ ] **Step 6: Run + commit**

```bash
php artisan test --filter=ContractSlaTest
vendor/bin/pint app/Services/Contracts/ContractSlaService.php app/Console/Commands/ScanContractSla.php app/Console/Kernel.php app/Services/Dispatch/MissionDispatchService.php tests/Feature/Relations/ContractSlaTest.php
git add app/Services/Contracts/ContractSlaService.php app/Console/Commands/ScanContractSla.php app/Console/Kernel.php app/Services/Dispatch/MissionDispatchService.php tests/Feature/Relations/ContractSlaTest.php
git commit -m "feat(relations): contract SLA — arm snapshot at mission creation + contract:scan-sla (met/breached/escalate, idempotent, soft-fail)"
```

---

### Task 8: Cycle Work Order câblé (tarif contrat + routage + SLA + approbation)

**Files:**
- Modify: `app/Services/Missions/EnterpriseWorkOrderMissionGeneratorService.php`
- Modify: `app/Models/WorkOrderLine.php` (+ migration `agreed_unit_price` si retenu)
- Create: `database/migrations/2026_06_05_000004_add_agreed_unit_price_to_work_order_lines.php`
- Create: `app/Services/Contracts/WorkOrderContractService.php`
- Test: `tests/Feature/Relations/WorkOrderContractTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Relations;

use App\Models\ContractRateCard;
use App\Models\EnterpriseWorkOrder;
use App\Models\OrganizationAccount;
use App\Models\OrganizationContract;
use App\Models\ServiceCatalog;
use App\Models\WorkOrderLine;
use App\Services\Contracts\WorkOrderContractService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkOrderContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_pricing_lines_uses_contract_rate_card(): void
    {
        $clientOrg = OrganizationAccount::factory()->create();
        $provider = OrganizationAccount::factory()->create();
        $service = ServiceCatalog::factory()->create();
        $contract = OrganizationContract::factory()->create([
            'organization_account_id' => $clientOrg->id,
            'provider_organization_id' => $provider->id,
            'status' => 'active',
        ]);
        ContractRateCard::create([
            'organization_contract_id' => $contract->id,
            'service_catalog_id' => $service->id,
            'negotiated_unit_price_cents' => 1500,
            'currency' => 'EUR',
        ]);

        $wo = EnterpriseWorkOrder::create([
            'organization_account_id' => $clientOrg->id,
            'organization_contract_id' => $contract->id,
            'service_catalog_id' => $service->id,
            'title' => 'WO test',
            'reference' => 'WO-TEST-1',
            'status' => 'draft',
            'approval_status' => 'pending',
        ]);
        $line = WorkOrderLine::create([
            'enterprise_work_order_id' => $wo->id,
            'service_catalog_id' => $service->id,
            'quantity' => 3,
            'unit_price' => 25.0,
        ]);

        app(WorkOrderContractService::class)->priceLines($wo->fresh());

        $line->refresh();
        $this->assertSame('15.00', (string) $line->agreed_unit_price); // 1500 cents
        $this->assertSame('45.00', (string) $line->line_total); // 3 × 15
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=WorkOrderContractTest`
Expected: FAIL (class/column not found).

- [ ] **Step 3: Migration + model for agreed_unit_price**

`2026_06_05_000004_add_agreed_unit_price_to_work_order_lines.php` :

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('work_order_lines') && ! Schema::hasColumn('work_order_lines', 'agreed_unit_price')) {
            Schema::table('work_order_lines', function (Blueprint $table) {
                $table->decimal('agreed_unit_price', 10, 2)->nullable();
            });
        }
    }

    public function down(): void
    {
        // additive
    }
};
```

Ajoute `'agreed_unit_price'` à `$fillable` et `'agreed_unit_price' => 'decimal:2'` à `$casts` dans `WorkOrderLine`.

- [ ] **Step 4: Implement WorkOrderContractService**

```php
<?php

namespace App\Services\Contracts;

use App\Models\EnterpriseWorkOrder;
use App\Models\WorkOrderLine;

class WorkOrderContractService
{
    public function __construct(private ContractPricingResolver $pricing) {}

    /**
     * Applique le tarif contrat aux lignes d'une WO : agreed_unit_price (grille →
     * sinon remise sur unit_price) + recalcul line_total. No-op sans contrat.
     */
    public function priceLines(EnterpriseWorkOrder $workOrder): void
    {
        $contract = $workOrder->contract;
        if (! $contract) {
            return;
        }

        foreach ($workOrder->lines as $line) {
            /** @var WorkOrderLine $line */
            $serviceId = $line->service_catalog_id ?: $workOrder->service_catalog_id;
            $base = (float) ($line->unit_price ?? 0);
            $res = $this->pricing->resolveAmount($contract, $serviceId ? (int) $serviceId : null, $base);

            $agreed = $res['label'] !== null ? $res['amount'] : $base;
            $qty = (float) ($line->quantity ?? 1);

            $line->forceFill([
                'agreed_unit_price' => $agreed,
                'line_total' => round($agreed * $qty, 2),
            ])->save();
        }
    }
}
```

- [ ] **Step 5: Wire into the mission generator (routing + SLA on generated missions)**

Lis `EnterpriseWorkOrderMissionGeneratorService::materializePendingMissionsForBatch` (le `Mission::create([...])` ~ligne 159-215). Après chaque création de mission, si la WO est sous contrat : pose `provider_organization_id` + `organization_contract_id` et arme le SLA :

```php
// SP4 — mission de WO sous contrat : routage partenaire + SLA.
if ($batch->enterprise_work_order_id) {
    $wo = \App\Models\EnterpriseWorkOrder::find($batch->enterprise_work_order_id);
    if ($wo && $wo->organization_contract_id && $wo->contract && $wo->contract->provider_organization_id) {
        $mission->forceFill([
            'organization_contract_id' => $wo->organization_contract_id,
            'provider_organization_id' => $wo->contract->provider_organization_id,
        ])->save();
        app(\App\Services\Contracts\ContractSlaService::class)->armForMission($mission);
    }
}
```

Et au début de `runForApprovedWorkOrder` (ou `ensureBatchForWorkOrder`), appelle `app(WorkOrderContractService::class)->priceLines($workOrder)` pour peupler `agreed_unit_price` avant la génération.

- [ ] **Step 6: WorkOrder approval → manager/finance pipeline + PO enforced**

Lis le flux d'approbation WO actuel (`WorkOrderApproval`, et l'UI `B2BOperationsCenter` qui crée/valide). Ajoute une méthode `WorkOrderContractService::assertApprovable(EnterpriseWorkOrder $wo): void` qui lève `ContractPolicyException` si `wo.contract.requires_purchase_order` et `wo.purchase_order_number` vide. Appelle-la avant de passer `approval_status='approved'`. Le pipeline manager→finance complet sur WO est calqué sur `EnterpriseBookingApprovalService` : si tu juges l'effort UI trop large, implémente au MINIMUM le gate PO bloquant + un test, et documente que le double palier manager→finance sur WO réutilise le même pattern (à câbler dans l'UI Task 9). Ajoute un test `test_work_order_cannot_be_approved_without_po_when_required`.

- [ ] **Step 7: Run + commit**

```bash
php artisan test --filter=WorkOrderContractTest
vendor/bin/pint app/Services/Contracts/WorkOrderContractService.php app/Services/Missions/EnterpriseWorkOrderMissionGeneratorService.php app/Models/WorkOrderLine.php database/migrations/2026_06_05_000004_add_agreed_unit_price_to_work_order_lines.php tests/Feature/Relations/WorkOrderContractTest.php
git add app/Services/Contracts/WorkOrderContractService.php app/Services/Missions/EnterpriseWorkOrderMissionGeneratorService.php app/Models/WorkOrderLine.php database/migrations/2026_06_05_000004_add_agreed_unit_price_to_work_order_lines.php tests/Feature/Relations/WorkOrderContractTest.php
git commit -m "feat(relations): work orders honor contract — agreed line pricing, partner-routed + SLA-armed missions, PO-gated approval"
```

---

### Task 9: Admin B2BOperationsCenter étendu (provider org + grille + dashboard SLA)

**Files:**
- Modify: `app/Livewire/Admin/B2BOperationsCenter.php`
- Modify: `resources/views/livewire/admin/b2b/operations/*.blade.php` (contract-form + un onglet/section SLA)
- Test: `tests/Feature/Relations/B2BOperationsContractTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Relations;

use App\Livewire\Admin\B2BOperationsCenter;
use App\Models\ContractRateCard;
use App\Models\OrganizationAccount;
use App\Models\OrganizationContract;
use App\Models\ServiceCatalog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class B2BOperationsContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_set_provider_org_and_rate_card_on_contract(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN ?? 'admin']);
        $clientOrg = OrganizationAccount::factory()->create();
        $provider = OrganizationAccount::factory()->create();
        $service = ServiceCatalog::factory()->create();

        Livewire::actingAs($admin)
            ->test(B2BOperationsCenter::class)
            ->set('contractForm.organization_account_id', $clientOrg->id)
            ->set('contractForm.provider_organization_id', $provider->id)
            ->set('contractForm.contract_reference', 'CT-SP4-1')
            ->set('contractForm.status', 'active')
            ->call('saveContract')
            ->assertHasNoErrors();

        $contract = OrganizationContract::where('contract_reference', 'CT-SP4-1')->first();
        $this->assertNotNull($contract);
        $this->assertSame($provider->id, $contract->provider_organization_id);

        Livewire::actingAs($admin)
            ->test(B2BOperationsCenter::class)
            ->call('addRateCard', $contract->id, $service->id, 1800)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('contract_rate_cards', [
            'organization_contract_id' => $contract->id,
            'service_catalog_id' => $service->id,
            'negotiated_unit_price_cents' => 1800,
        ]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=B2BOperationsContractTest`
Expected: FAIL (method `provider_organization_id`/`addRateCard` not handled).

- [ ] **Step 3: Implement**

Lis `B2BOperationsCenter`. Ajoute `'provider_organization_id' => null` à `$contractForm` + à `rules()` (`'contractForm.provider_organization_id' => ['nullable','integer','exists:organization_accounts,id']`). Dans la méthode de sauvegarde du contrat (`saveContract`/équivalent — lis le vrai nom), inclus `provider_organization_id` dans le `updateOrCreate`/`create`. Ajoute :

```php
public function addRateCard(int $contractId, int $serviceCatalogId, int $unitPriceCents): void
{
    \App\Models\ContractRateCard::updateOrCreate(
        ['organization_contract_id' => $contractId, 'service_catalog_id' => $serviceCatalogId],
        ['negotiated_unit_price_cents' => $unitPriceCents, 'currency' => 'EUR'],
    );
}

/** @return \Illuminate\Support\Collection<int, \App\Models\ContractSlaEvent> */
public function getSlaBreachesProperty()
{
    return \App\Models\ContractSlaEvent::query()
        ->whereIn('status', ['breached', 'escalated'])
        ->latest('due_at')
        ->limit(50)
        ->get();
}
```

Vue : ajoute un champ `provider_organization_id` (select des `OrganizationAccount` type PROVIDER_COMPANY) au `contract-form.blade.php` ; une section grille tarifaire (liste `contract_rate_cards` + form `addRateCard`) ; un onglet/section « SLA » qui itère `$this->slaBreaches`. Calque le style des sections existantes.

- [ ] **Step 4: Run + commit**

```bash
php artisan test --filter=B2BOperationsContractTest
vendor/bin/pint app/Livewire/Admin/B2BOperationsCenter.php resources/views/livewire/admin/b2b/operations tests/Feature/Relations/B2BOperationsContractTest.php
git add app/Livewire/Admin/B2BOperationsCenter.php resources/views/livewire/admin/b2b/operations tests/Feature/Relations/B2BOperationsContractTest.php
git commit -m "feat(relations): admin B2B center — provider org + rate cards + SLA breaches dashboard"
```

---

### Task 10: Portail client société (ClientContractsCenter, lecture)

**Files:**
- Create: `app/Livewire/ClientCompany/ClientContractsCenter.php`
- Create: `resources/views/livewire/client-company/client-contracts-center.blade.php`
- Modify: `routes/company-dashboards.php`
- Test: `tests/Feature/Relations/ClientContractsCenterTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Relations;

use App\Livewire\ClientCompany\ClientContractsCenter;
use App\Models\OrganizationAccount;
use App\Models\OrganizationContract;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClientContractsCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_member_sees_only_their_org_contracts(): void
    {
        $org = OrganizationAccount::factory()->create();
        $otherOrg = OrganizationAccount::factory()->create();

        $mine = OrganizationContract::factory()->create([
            'organization_account_id' => $org->id,
            'provider_organization_id' => OrganizationAccount::factory()->create()->id,
            'status' => 'active',
        ]);
        $foreign = OrganizationContract::factory()->create([
            'organization_account_id' => $otherOrg->id,
            'provider_organization_id' => OrganizationAccount::factory()->create()->id,
            'status' => 'active',
        ]);

        $user = User::factory()->create(['current_organization_id' => $org->id]);
        OrganizationMember::create([
            'organization_account_id' => $org->id, 'user_id' => $user->id, 'role' => 'member', 'status' => 'active',
        ]);

        Livewire::actingAs($user)
            ->test(ClientContractsCenter::class)
            ->assertSee($mine->contract_reference)
            ->assertDontSee($foreign->contract_reference);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ClientContractsCenterTest`
Expected: FAIL (class not found).

- [ ] **Step 3: Implement the component**

```php
<?php

namespace App\Livewire\ClientCompany;

use App\Models\OrganizationContract;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;
use Livewire\Component;

class ClientContractsCenter extends Component
{
    /** @return Collection<int, OrganizationContract> */
    public function getContractsProperty(): Collection
    {
        $orgId = Auth::user()?->organizationContextId();
        if (! $orgId) {
            return collect();
        }

        return OrganizationContract::query()
            ->where('organization_account_id', $orgId)
            ->with(['providerOrganization:id,name', 'rateCards', 'workOrders'])
            ->orderByDesc('effective_from')
            ->get();
    }

    public function render()
    {
        return view('livewire.client-company.client-contracts-center');
    }
}
```

Vue `client-contracts-center.blade.php` : itère `$this->contracts` — affiche `contract_reference`, partenaire (`providerOrganization?->name`), statut, remise/grille, fenêtre, et un sous-bloc Work Orders + statut SLA (compte des `contract_sla_events` breached). Read-only. Calque le style de `BookingHub`.

- [ ] **Step 4: Route**

Dans `routes/company-dashboards.php`, dans le groupe client société (calque les routes `BookingHub`/`SiteManager`), ajoute :

```php
Route::get('/contrats', \App\Livewire\ClientCompany\ClientContractsCenter::class)->name('client-company.contracts');
```

- [ ] **Step 5: Run + commit**

```bash
php artisan test --filter=ClientContractsCenterTest
vendor/bin/pint app/Livewire/ClientCompany/ClientContractsCenter.php resources/views/livewire/client-company/client-contracts-center.blade.php routes/company-dashboards.php tests/Feature/Relations/ClientContractsCenterTest.php
git add app/Livewire/ClientCompany/ClientContractsCenter.php resources/views/livewire/client-company/client-contracts-center.blade.php routes/company-dashboards.php tests/Feature/Relations/ClientContractsCenterTest.php
git commit -m "feat(relations): client company portal — read-only contracts + work orders + SLA view"
```

---

### Task 11: Portail partenaire société (DispatchCenter — lecture contrats où je suis partenaire)

**Files:**
- Modify: `app/Livewire/ProviderCompany/DispatchCenter.php`
- Modify: la vue blade de `DispatchCenter`
- Test: `tests/Feature/Relations/PartnerContractsViewTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Relations;

use App\Livewire\ProviderCompany\DispatchCenter;
use App\Models\OrganizationAccount;
use App\Models\OrganizationContract;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PartnerContractsViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_partner_sees_contracts_where_it_is_provider(): void
    {
        $providerOrg = OrganizationAccount::factory()->create();
        $clientOrg = OrganizationAccount::factory()->create();

        $contract = OrganizationContract::factory()->create([
            'organization_account_id' => $clientOrg->id,
            'provider_organization_id' => $providerOrg->id,
            'status' => 'active',
        ]);

        $user = User::factory()->create(['current_organization_id' => $providerOrg->id]);
        OrganizationMember::create([
            'organization_account_id' => $providerOrg->id, 'user_id' => $user->id, 'role' => 'owner', 'status' => 'active',
        ]);

        Livewire::actingAs($user)
            ->test(DispatchCenter::class)
            ->assertViewHas('partnerContracts', fn ($c) => $c->contains('id', $contract->id));
    }
}
```

(Si `DispatchCenter` n'expose pas de variables de vue nommées, asserte plutôt via une computed `assertSet`/`assertSee` du `contract_reference`.)

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=PartnerContractsViewTest`
Expected: FAIL.

- [ ] **Step 3: Implement**

Dans `DispatchCenter`, ajoute :

```php
/** @return \Illuminate\Support\Collection<int, \App\Models\OrganizationContract> */
public function getPartnerContractsProperty()
{
    $orgId = \Illuminate\Support\Facades\Auth::user()?->current_organization_id;
    if (! $orgId) {
        return collect();
    }

    return \App\Models\OrganizationContract::query()
        ->where('provider_organization_id', $orgId)
        ->with(['organizationAccount:id,name', 'rateCards'])
        ->orderByDesc('effective_from')
        ->get();
}
```

Dans la vue, ajoute une section read-only « Mes contrats partenaires » qui itère `$this->partnerContracts` (client, statut, grille/remise, fenêtre) + un compteur de missions/SLA entrants (réutilise `$this->missions` filtrées par `organization_contract_id`). La réassignation worker reste le mécanisme SP3 existant.

- [ ] **Step 4: Run + commit**

```bash
php artisan test --filter=PartnerContractsViewTest
vendor/bin/pint app/Livewire/ProviderCompany/DispatchCenter.php resources/views/livewire/provider-company tests/Feature/Relations/PartnerContractsViewTest.php
git add app/Livewire/ProviderCompany/DispatchCenter.php resources/views/livewire/provider-company tests/Feature/Relations/PartnerContractsViewTest.php
git commit -m "feat(relations): provider company portal — read-only view of contracts where the org is the partner"
```

---

### Task 12: Mobile contract-aware (badge dans la réponse API)

**Files:**
- Modify: `app/Http/Controllers/Api/Client/ClientBookingController.php` (`serialize`)
- Modify (mobile, optionnel display): `mobile/client/src/...` (badge "couvert par votre contrat")
- Test: `tests/Feature/Relations/ContractApiResponseTest.php` (+ éventuel jest)

Le routage + tarif mobile sont déjà appliqués côté serveur par le hook (Task 6, dans `CreateBookingFromApiAction`). Ici on EXPOSE l'info au client.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Relations;

use App\Models\Booking;
use App\Models\OrganizationAccount;
use App\Models\OrganizationContract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContractApiResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_show_exposes_contract_coverage(): void
    {
        $contract = OrganizationContract::factory()->create([
            'provider_organization_id' => OrganizationAccount::factory()->create()->id,
            'contract_reference' => 'CT-API-1',
            'status' => 'active',
        ]);
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'organization_contract_id' => $contract->id,
        ]);

        Sanctum::actingAs($client);

        $this->getJson("/api/client/bookings/{$booking->id}")
            ->assertOk()
            ->assertJsonPath('data.contract_covered', true)
            ->assertJsonPath('data.contract_label', 'CT-API-1');
    }
}
```

(Adapte la route exacte de show booking à celle réellement définie — lis `routes/api/client.php`.)

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ContractApiResponseTest`
Expected: FAIL (clé absente).

- [ ] **Step 3: Implement**

Dans `ClientBookingController::serialize(Booking $b, ...)`, ajoute au tableau de base :

```php
'contract_covered' => (bool) $b->organization_contract_id,
'contract_label' => $b->organization_contract_id ? optional($b->organizationContract)->contract_reference : null,
```

(Charge la relation `organizationContract` si nécessaire pour éviter le N+1 : `->load('organizationContract:id,contract_reference')` dans le show, ou `loadMissing`.)

- [ ] **Step 4: Mobile display (optionnel, léger)**

Dans l'écran de confirmation/détail booking mobile (`mobile/client/src/screens/booking/BookingStep5Confirmation.tsx` ou le détail), si la réponse contient `contract_covered`, affiche un badge « Couvert par votre contrat ». Ajoute le champ `contract_covered?: boolean; contract_label?: string|null` au type booking partagé. Test jest minimal si un composant de badge est ajouté ; sinon, skip (le serveur reste la frontière). Garde-fou : pas de `git add -A`, pas de `mobile/.expo`.

- [ ] **Step 5: Run + commit**

```bash
php artisan test --filter=ContractApiResponseTest
vendor/bin/pint app/Http/Controllers/Api/Client/ClientBookingController.php tests/Feature/Relations/ContractApiResponseTest.php
git add app/Http/Controllers/Api/Client/ClientBookingController.php tests/Feature/Relations/ContractApiResponseTest.php
# + fichiers mobile réellement modifiés si Step 4 fait
git commit -m "feat(relations): expose contract coverage on booking API (+ optional mobile badge)"
```

---

### Task 13: Vérification finale (gates complets)

**Files:** aucun nouveau (corrections de régression/typage/test uniquement si nécessaire).

- [ ] **Step 1: Suite PHP complète**

Run: `php artisan test`
Expected: **0 failed**. Corrige toute régression SP4 (fixtures, drift) au plus juste, relance.

- [ ] **Step 2: PHPStan FULL**

Run: `vendor/bin/phpstan analyse --memory-limit=1G`
Expected: `[OK] No errors`. Corrige avec de VRAIES annotations (`@return array{...}`, `Builder<Model>`, `instanceof`, relations typées `BelongsTo<X, $this>`). **0 suppression, 0 ajout au baseline** (réduire le baseline si des entrées deviennent obsolètes est OK).

- [ ] **Step 3: Pint**

Run: `vendor/bin/pint --dirty`
Expected: clean (commit si des fichiers sont reformatés).

- [ ] **Step 4: Mobile**

Run (depuis `mobile/client`): `npx tsc --noEmit` puis `npx jest --watchAll=false`
Expected: tsc 0 erreur ; jest vert (note les pré-existants).

- [ ] **Step 5: Vérification fonctionnelle de bout en bout**

Confirme (cite fichier:ligne) que : (a) un booking d'un client sous contrat porte `organization_contract_id` + `assigned_provider_organization_id` = partenaire + `devis_estime` négocié ; (b) la mission générée porte `provider_organization_id` + SLA armé ; (c) `contract:scan-sla` marque met/breached ; (d) le DispatchCenter partenaire voit le contrat et peut réassigner (test SP3 `DispatchCenterReassignmentTest` toujours vert) ; (e) PO manquant bloque. Si un maillon manque, ajoute un test e2e léger le prouvant.

- [ ] **Step 6: Commit final si corrections**

```bash
git add <chemins précis>
git commit -m "test(relations): SP4 final verification — full suite + phpstan green; end-to-end contract path covered"
```

---

## Self-Review (effectué)

**Spec coverage :** DoD 1→Task 1 ; DoD 2→Task 2 ; DoD 3→Task 3 ; DoD 4→Task 4 ; DoD 5→Task 5 ; DoD 6 (hook 3 chemins)→Task 6 ; DoD 7 (Work Orders)→Task 8 ; DoD 8 (SLA monitor)→Task 7 ; DoD 9 (UI admin + portails + mobile)→Tasks 9/10/11/12 ; DoD 10 (tests)→répartis + Task 13 ; DoD 11 (gates)→Task 13. Aucune section de spec sans tâche.

**Note de réalité (importante pour l'exécutant) :** le booking n'utilise PAS PricingEngine v2 — le tarif négocié réel est appliqué au `devis_estime` via `ContractPricingResolver::resolveAmount` dans le hook (Task 6). L'intégration PricingEngine v2 (Task 3) sert l'API quote + les Work Orders (Task 8). Les deux partagent le même `ContractPricingResolver` (DRY).

**Type consistency :** `ContractPricingResolver::resolveCents(...): array{price_cents:int, label:?string}` et `resolveAmount(...): array{amount:float, label:?string}` cohérents partout. `ContractResolver::resolveForBooking/resolveForClientUser` cohérents. `ContractBookingHook::apply(User, array, string): array` consommé identiquement dans les 3 chemins. `ContractSlaEvent` constantes (KIND_*/STATUS_*) utilisées partout.

**Placeholders :** aucun « TBD/TODO ». Les tâches d'intégration (6, 7, 8, 9, 10, 11) ont des étapes « lire+adapter » explicites avec le contrat exact à respecter et un snippet représentatif — pattern validé sur SP1-SP3.
