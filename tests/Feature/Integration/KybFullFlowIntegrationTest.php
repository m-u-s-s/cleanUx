<?php

namespace Tests\Feature\Integration;

use App\Models\AuditEvent;
use App\Models\BusinessEntity;
use App\Models\BusinessSanctionsCheck;
use App\Models\BusinessVerification;
use App\Models\User;
use App\Services\KybV2\BusinessOnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Test E2E KYB v2 : start → run verifications → run sanctions → approve.
 * Vérifie que les services en cascade produisent les rows attendus + audit events.
 */
class KybFullFlowIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('kyb_v2.identity_provider', 'mock');
        Config::set('kyb_v2.sanctions_provider', 'mock');
        Config::set('kyb_v2.identifier_types_by_country', ['FR' => ['siret']]);
        Config::set('kyb_v2.sanctions_lists', ['eu', 'us_ofac']);
        Config::set('kyb_v2.verification_cache_days', 30);
        Config::set('kyb_v2.sanctions_cache_days', 30);
        Config::set('kyb_v2.auto_approve_enabled', false);
        Config::set('kyb_v2.risk_weights', [
            'sanctions_match' => 50, 'pep_owner' => 25, 'missing_kbis' => 10,
            'recent_incorporation' => 8, 'unverified_vat' => 5, 'high_risk_country' => 15,
        ]);
        Config::set('kyb_v2.risk_thresholds', ['low_max' => 20, 'medium_max' => 50, 'high_max' => 75]);
        Config::set('kyb_v2.high_risk_countries', []);
        Config::set('audit.enabled', true);
    }

    public function test_full_kyb_happy_path_creates_all_artifacts(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create();
        $svc = app(BusinessOnboardingService::class);

        // 1. Start verification
        $entity = $svc->startVerification([
            'legal_name' => 'Acme Cleaning SARL',
            'country_code' => 'FR',
            'identifier_type' => 'siret',
            'identifier_value' => '12345678900012',
            'vat_id' => 'FR12345678900',
        ], $owner);
        $this->assertSame(BusinessEntity::STATUS_PENDING, $entity->status);

        // 2. Run provider verifications (identity + tax_validity)
        $svc->runVerifications($entity);
        $this->assertSame(1, BusinessVerification::query()
            ->where('entity_id', $entity->id)
            ->where('check_type', 'identity')
            ->where('status', BusinessVerification::STATUS_SUCCESS)
            ->count());

        // 3. Run sanctions screening — clean legal_name → no match
        $svc->runSanctionsScreening($entity);
        $this->assertSame(2, BusinessSanctionsCheck::query()->where('entity_id', $entity->id)->count());
        $this->assertSame(0, BusinessSanctionsCheck::query()->matches()->count());

        // 4. Approve manuel
        $approved = $svc->approve($entity, $admin);
        $this->assertSame(BusinessEntity::STATUS_VERIFIED, $approved->status);
        $this->assertSame($admin->id, $approved->verified_by_user_id);

        // 5. Audit event créé
        if (Schema::hasTable('audit_events')) {
            $this->assertSame(1, AuditEvent::query()
                ->where('event_type', 'kyb.entity_approved')
                ->count());
        }
    }

    public function test_kyb_with_sanctions_match_forces_needs_review(): void
    {
        $owner = User::factory()->create();
        $svc = app(BusinessOnboardingService::class);

        $entity = $svc->startVerification([
            'legal_name' => 'Sanctioned Holdings Ltd',
            'country_code' => 'FR',
            'identifier_type' => 'siret',
            'identifier_value' => '99999999999999',
        ], $owner);

        $svc->runSanctionsScreening($entity);
        $fresh = $entity->fresh();

        $this->assertSame(BusinessEntity::STATUS_NEEDS_REVIEW, $fresh->status);
        $this->assertGreaterThanOrEqual(50.0, (float) $fresh->risk_score);
        $this->assertTrue(BusinessSanctionsCheck::query()
            ->where('entity_id', $entity->id)
            ->matches()
            ->exists());
    }

    public function test_kyb_reject_records_audit_event(): void
    {
        $admin = User::factory()->admin()->create();
        $svc = app(BusinessOnboardingService::class);
        $entity = $svc->startVerification([
            'legal_name' => 'X', 'country_code' => 'FR',
            'identifier_type' => 'siret', 'identifier_value' => '12345678900012',
        ]);

        $svc->reject($entity, 'Documents fournis incohérents avec l\'identité légale.', $admin);

        $this->assertSame(BusinessEntity::STATUS_REJECTED, $entity->fresh()->status);

        if (Schema::hasTable('audit_events')) {
            $audit = AuditEvent::query()
                ->where('event_type', 'kyb.entity_rejected')
                ->first();
            $this->assertNotNull($audit);
            $this->assertSame($admin->id, $audit->actor_id);
        }
    }
}
