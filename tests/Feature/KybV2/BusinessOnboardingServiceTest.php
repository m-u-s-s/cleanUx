<?php

namespace Tests\Feature\KybV2;

use App\Models\BusinessEntity;
use App\Models\BusinessSanctionsCheck;
use App\Models\BusinessVerification;
use App\Models\User;
use App\Services\KybV2\BusinessOnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BusinessOnboardingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('kyb_v2.identity_provider', 'mock');
        Config::set('kyb_v2.sanctions_provider', 'mock');
        Config::set('kyb_v2.identifier_types_by_country', [
            'FR' => ['siret', 'siren'],
            'BE' => ['kbo'],
        ]);
        Config::set('kyb_v2.verification_cache_days', 90);
        Config::set('kyb_v2.sanctions_cache_days', 30);
        Config::set('kyb_v2.sanctions_lists', ['eu', 'us_ofac']);
        Config::set('kyb_v2.auto_approve_enabled', false);
        Config::set('kyb_v2.risk_weights', [
            'sanctions_match' => 50, 'pep_owner' => 25, 'missing_kbis' => 10,
            'recent_incorporation' => 8, 'unverified_vat' => 5, 'high_risk_country' => 15,
        ]);
        Config::set('kyb_v2.risk_thresholds', ['low_max' => 20, 'medium_max' => 50, 'high_max' => 75]);
        Config::set('kyb_v2.high_risk_countries', []);
    }

    public function test_start_verification_creates_entity_with_pending_status(): void
    {
        $user = User::factory()->create();
        $entity = app(BusinessOnboardingService::class)->startVerification([
            'legal_name' => 'Acme SARL',
            'country_code' => 'FR',
            'identifier_type' => 'siret',
            'identifier_value' => '12345678900012',
        ], $user);

        $this->assertSame(BusinessEntity::STATUS_PENDING, $entity->status);
        $this->assertSame($user->id, $entity->owner_user_id);
        $this->assertSame('12345678900012', $entity->identifier_value);
    }

    public function test_start_verification_is_idempotent_by_identifier(): void
    {
        $user = User::factory()->create();
        $svc = app(BusinessOnboardingService::class);
        $a = $svc->startVerification([
            'legal_name' => 'Acme', 'country_code' => 'FR',
            'identifier_type' => 'siret', 'identifier_value' => '12345678900012',
        ], $user);
        $b = $svc->startVerification([
            'legal_name' => 'Acme (renamed)', 'country_code' => 'FR',
            'identifier_type' => 'siret', 'identifier_value' => '12345678900012',
        ], $user);

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, BusinessEntity::query()->count());
    }

    public function test_start_verification_rejects_unsupported_identifier_for_country(): void
    {
        $this->expectException(ValidationException::class);
        app(BusinessOnboardingService::class)->startVerification([
            'legal_name' => 'X', 'country_code' => 'FR',
            'identifier_type' => 'kbo', 'identifier_value' => '01234',
        ]);
    }

    public function test_run_verifications_persists_identity_check(): void
    {
        $entity = app(BusinessOnboardingService::class)->startVerification([
            'legal_name' => 'Acme', 'country_code' => 'FR',
            'identifier_type' => 'siret', 'identifier_value' => '12345678900012',
        ]);
        $updated = app(BusinessOnboardingService::class)->runVerifications($entity);

        $this->assertSame(1, BusinessVerification::query()
            ->where('entity_id', $entity->id)
            ->where('check_type', 'identity')
            ->where('status', BusinessVerification::STATUS_SUCCESS)
            ->count());
        $this->assertNotNull($updated->risk_score);
    }

    public function test_run_verifications_idempotent_via_cache(): void
    {
        $entity = app(BusinessOnboardingService::class)->startVerification([
            'legal_name' => 'Acme', 'country_code' => 'FR',
            'identifier_type' => 'siret', 'identifier_value' => '12345678900012',
        ]);
        $svc = app(BusinessOnboardingService::class);
        $svc->runVerifications($entity);
        $countBefore = BusinessVerification::query()->count();
        $svc->runVerifications($entity->fresh());
        $this->assertSame($countBefore, BusinessVerification::query()->count());
    }

    public function test_run_sanctions_screening_creates_rows_per_list(): void
    {
        $entity = app(BusinessOnboardingService::class)->startVerification([
            'legal_name' => 'Clean Co SARL', 'country_code' => 'FR',
            'identifier_type' => 'siret', 'identifier_value' => '12345678900012',
        ]);
        app(BusinessOnboardingService::class)->runSanctionsScreening($entity);

        $this->assertSame(2, BusinessSanctionsCheck::query()->where('entity_id', $entity->id)->count());
        $statuses = BusinessSanctionsCheck::query()
            ->where('entity_id', $entity->id)
            ->pluck('status')->all();
        $this->assertContains(BusinessSanctionsCheck::STATUS_CLEAR, $statuses);
    }

    public function test_run_sanctions_screening_detects_match_via_mock(): void
    {
        $entity = app(BusinessOnboardingService::class)->startVerification([
            'legal_name' => 'Sanctioned Holdings Ltd', 'country_code' => 'FR',
            'identifier_type' => 'siret', 'identifier_value' => '99999999999999',
        ]);
        $updated = app(BusinessOnboardingService::class)->runSanctionsScreening($entity);

        $this->assertTrue(
            BusinessSanctionsCheck::query()
                ->where('entity_id', $entity->id)
                ->where('status', BusinessSanctionsCheck::STATUS_MATCH)
                ->exists()
        );
        // risk score >= 50 because of sanctions
        $this->assertGreaterThanOrEqual(50.0, (float) $updated->risk_score);
        $this->assertSame(BusinessEntity::STATUS_NEEDS_REVIEW, $updated->status);
    }

    public function test_approve_marks_verified(): void
    {
        $admin = User::factory()->admin()->create();
        $entity = app(BusinessOnboardingService::class)->startVerification([
            'legal_name' => 'Acme', 'country_code' => 'FR',
            'identifier_type' => 'siret', 'identifier_value' => '12345678900012',
        ]);
        $approved = app(BusinessOnboardingService::class)->approve($entity, $admin);

        $this->assertSame(BusinessEntity::STATUS_VERIFIED, $approved->status);
        $this->assertSame($admin->id, $approved->verified_by_user_id);
        $this->assertNotNull($approved->verified_at);
    }

    public function test_reject_requires_min_reason_length(): void
    {
        $admin = User::factory()->admin()->create();
        $entity = app(BusinessOnboardingService::class)->startVerification([
            'legal_name' => 'Acme', 'country_code' => 'FR',
            'identifier_type' => 'siret', 'identifier_value' => '12345678900012',
        ]);
        $this->expectException(ValidationException::class);
        app(BusinessOnboardingService::class)->reject($entity, 'short', $admin);
    }

    public function test_auto_approve_works_when_enabled_and_score_low(): void
    {
        Config::set('kyb_v2.auto_approve_enabled', true);
        Config::set('kyb_v2.auto_approve_score_max', 30);
        // Pas de vat_id, pas de doc kbis → score = 10 (missing_kbis only)
        $entity = app(BusinessOnboardingService::class)->startVerification([
            'legal_name' => 'Acme', 'country_code' => 'FR',
            'identifier_type' => 'siret', 'identifier_value' => '12345678900012',
            'incorporation_date' => '2018-01-01',
        ]);
        $updated = app(BusinessOnboardingService::class)->runVerifications($entity);
        // Identity vérifiée → status doit être verified après refreshRiskAndStatus
        $this->assertSame(BusinessEntity::STATUS_VERIFIED, $updated->status);
    }
}
