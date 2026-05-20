<?php

namespace Tests\Feature\KybV2;

use App\Models\BusinessBeneficialOwner;
use App\Models\BusinessDocument;
use App\Models\BusinessEntity;
use App\Models\BusinessSanctionsCheck;
use App\Models\BusinessVerification;
use App\Services\KybV2\RiskScoreEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class RiskScoreEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('kyb_v2.risk_weights', [
            'sanctions_match' => 50,
            'pep_owner' => 25,
            'missing_kbis' => 10,
            'recent_incorporation' => 8,
            'unverified_vat' => 5,
            'high_risk_country' => 15,
        ]);
        Config::set('kyb_v2.risk_thresholds', [
            'low_max' => 20, 'medium_max' => 50, 'high_max' => 75,
        ]);
        Config::set('kyb_v2.high_risk_countries', ['RU', 'IR']);
    }

    private function makeEntity(array $overrides = []): BusinessEntity
    {
        return BusinessEntity::query()->create(array_merge([
            'code' => BusinessEntity::generateCode(),
            'legal_name' => 'Test SARL',
            'country_code' => 'FR',
            'identifier_type' => 'siret',
            'identifier_value' => '12345678900012',
            'status' => BusinessEntity::STATUS_PENDING,
        ], $overrides));
    }

    public function test_clean_entity_with_kbis_returns_low_risk(): void
    {
        $e = $this->makeEntity(['incorporation_date' => now()->subYears(5)]);
        BusinessDocument::query()->create([
            'entity_id' => $e->id,
            'document_type' => 'kbis',
            'file_path' => 'fake.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'uploaded_at' => now(),
            'status' => BusinessDocument::STATUS_APPROVED,
        ]);

        $result = app(RiskScoreEngine::class)->compute($e->fresh());
        $this->assertSame(0.0, $result['score']);
        $this->assertSame(BusinessEntity::RISK_LOW, $result['level']);
    }

    public function test_missing_kbis_adds_score(): void
    {
        $e = $this->makeEntity(['incorporation_date' => now()->subYears(5)]);
        $result = app(RiskScoreEngine::class)->compute($e);
        $this->assertSame(10.0, $result['score']);
        $this->assertContains('missing_kbis', $result['reasons']);
    }

    public function test_sanctions_match_pushes_to_critical(): void
    {
        $e = $this->makeEntity();
        BusinessSanctionsCheck::query()->create([
            'entity_id' => $e->id,
            'list_name' => 'eu',
            'status' => BusinessSanctionsCheck::STATUS_MATCH,
            'match_count' => 1,
            'provider' => 'mock',
            'checked_at' => now(),
        ]);
        $result = app(RiskScoreEngine::class)->compute($e->fresh());
        $this->assertGreaterThanOrEqual(50.0, $result['score']);
        $this->assertContains('sanctions_match', $result['reasons']);
    }

    public function test_pep_owner_adds_25(): void
    {
        $e = $this->makeEntity(['incorporation_date' => now()->subYears(5)]);
        BusinessDocument::query()->create([
            'entity_id' => $e->id,
            'document_type' => 'kbis',
            'file_path' => 'fake.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'uploaded_at' => now(),
            'status' => BusinessDocument::STATUS_APPROVED,
        ]);
        BusinessBeneficialOwner::query()->create([
            'entity_id' => $e->id,
            'full_name' => 'Jean Politicien',
            'is_pep' => true,
        ]);
        $result = app(RiskScoreEngine::class)->compute($e->fresh());
        $this->assertSame(25.0, $result['score']);
    }

    public function test_recent_incorporation_adds_score(): void
    {
        $e = $this->makeEntity(['incorporation_date' => now()->subMonths(3)]);
        BusinessDocument::query()->create([
            'entity_id' => $e->id,
            'document_type' => 'kbis',
            'file_path' => 'fake.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'uploaded_at' => now(),
            'status' => BusinessDocument::STATUS_APPROVED,
        ]);
        $result = app(RiskScoreEngine::class)->compute($e->fresh());
        $this->assertSame(8.0, $result['score']);
        $this->assertContains('recent_incorporation', $result['reasons']);
    }

    public function test_high_risk_country_adds_score(): void
    {
        $e = $this->makeEntity([
            'country_code' => 'RU',
            'incorporation_date' => now()->subYears(5),
        ]);
        BusinessDocument::query()->create([
            'entity_id' => $e->id,
            'document_type' => 'kbis',
            'file_path' => 'fake.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'uploaded_at' => now(),
            'status' => BusinessDocument::STATUS_APPROVED,
        ]);
        $result = app(RiskScoreEngine::class)->compute($e->fresh());
        $this->assertSame(15.0, $result['score']);
        $this->assertContains('high_risk_country', $result['reasons']);
    }

    public function test_unverified_vat_only_counts_if_vat_set(): void
    {
        // entity with vat_id but no verification
        $e = $this->makeEntity([
            'vat_id' => 'FR12345678900',
            'incorporation_date' => now()->subYears(5),
        ]);
        BusinessDocument::query()->create([
            'entity_id' => $e->id,
            'document_type' => 'kbis',
            'file_path' => 'fake.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'uploaded_at' => now(),
            'status' => BusinessDocument::STATUS_APPROVED,
        ]);
        $result = app(RiskScoreEngine::class)->compute($e->fresh());
        $this->assertSame(5.0, $result['score']);

        // After verification
        BusinessVerification::query()->create([
            'entity_id' => $e->id,
            'provider' => 'mock',
            'check_type' => 'tax_validity',
            'status' => BusinessVerification::STATUS_SUCCESS,
            'checked_at' => now(),
        ]);
        $result2 = app(RiskScoreEngine::class)->compute($e->fresh());
        $this->assertSame(0.0, $result2['score']);
    }

    public function test_score_caps_at_100(): void
    {
        $e = $this->makeEntity(['country_code' => 'IR', 'incorporation_date' => now()->subWeek()]);
        BusinessSanctionsCheck::query()->create([
            'entity_id' => $e->id, 'list_name' => 'eu', 'status' => 'match',
            'match_count' => 1, 'provider' => 'mock', 'checked_at' => now(),
        ]);
        BusinessBeneficialOwner::query()->create([
            'entity_id' => $e->id, 'full_name' => 'PEP', 'is_pep' => true,
        ]);
        $result = app(RiskScoreEngine::class)->compute($e->fresh());
        $this->assertSame(100.0, $result['score']);
        $this->assertSame(BusinessEntity::RISK_CRITICAL, $result['level']);
    }

    public function test_resolve_level_thresholds(): void
    {
        $engine = app(RiskScoreEngine::class);
        $this->assertSame(BusinessEntity::RISK_LOW, $engine->resolveLevel(20));
        $this->assertSame(BusinessEntity::RISK_MEDIUM, $engine->resolveLevel(35));
        $this->assertSame(BusinessEntity::RISK_HIGH, $engine->resolveLevel(60));
        $this->assertSame(BusinessEntity::RISK_CRITICAL, $engine->resolveLevel(90));
    }
}
