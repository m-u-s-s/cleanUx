<?php

namespace Tests\Feature\Livewire\Admin\KybV2;

use App\Livewire\Admin\KybV2\KybCenter;
use App\Models\BusinessDocument;
use App\Models\BusinessEntity;
use App\Models\User;
use App\Services\KybV2\BusinessOnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use Tests\TestCase;

class KybCenterCoverageBatch13Test extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('kyb_v2.identity_provider', 'mock');
        Config::set('kyb_v2.sanctions_provider', 'mock');
        Config::set('kyb_v2.identifier_types_by_country', [
            'FR' => ['siret', 'siren'], 'BE' => ['kbo'],
        ]);
        Config::set('kyb_v2.verification_cache_days', 90);
        Config::set('kyb_v2.sanctions_lists', ['eu', 'us_ofac']);
        Config::set('kyb_v2.auto_approve_enabled', false);
        Config::set('kyb_v2.risk_weights', [
            'sanctions_match' => 50, 'pep_owner' => 25, 'missing_kbis' => 10,
            'recent_incorporation' => 8, 'unverified_vat' => 5, 'high_risk_country' => 15,
        ]);
        Config::set('kyb_v2.risk_thresholds', ['low_max' => 20, 'medium_max' => 50, 'high_max' => 75]);
        Config::set('kyb_v2.high_risk_countries', []);

        $this->admin = User::factory()->admin()->create();
    }

    private function makeEntity(string $identifier = '12345678900012'): BusinessEntity
    {
        return app(BusinessOnboardingService::class)->startVerification([
            'legal_name' => 'Acme '.substr($identifier, -4),
            'country_code' => 'FR',
            'identifier_type' => 'siret',
            'identifier_value' => $identifier,
        ], $this->admin);
    }

    public function test_mount_renders_entities_tab_with_kpis(): void
    {
        $this->makeEntity();

        Livewire::actingAs($this->admin)
            ->test(KybCenter::class)
            ->assertOk()
            ->assertSet('tab', 'entities')
            ->assertViewHas('kpis')
            ->assertViewHas('items');
    }

    public function test_entities_tab_applies_status_and_risk_filters(): void
    {
        $entity = $this->makeEntity();

        Livewire::actingAs($this->admin)
            ->test(KybCenter::class)
            ->set('filterStatus', $entity->status)
            ->set('filterRiskLevel', (string) $entity->risk_level)
            ->assertOk()
            ->assertViewHas('items');
    }

    public function test_documents_tab_renders(): void
    {
        $entity = $this->makeEntity();
        BusinessDocument::query()->create([
            'entity_id' => $entity->id,
            'document_type' => 'kbis',
            'file_path' => 'kyb_documents_test/x.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 100,
            'uploaded_at' => now(),
            'uploaded_by_user_id' => $this->admin->id,
            'status' => BusinessDocument::STATUS_PENDING,
        ]);

        Livewire::actingAs($this->admin)
            ->test(KybCenter::class)
            ->set('tab', 'documents')
            ->assertOk()
            ->assertViewHas('items');
    }

    public function test_verifications_tab_renders(): void
    {
        $entity = $this->makeEntity();
        app(BusinessOnboardingService::class)->runVerifications($entity);

        Livewire::actingAs($this->admin)
            ->test(KybCenter::class)
            ->set('tab', 'verifications')
            ->assertOk()
            ->assertViewHas('items');
    }

    public function test_sanctions_tab_renders(): void
    {
        $entity = $this->makeEntity();
        app(BusinessOnboardingService::class)->runSanctionsScreening($entity);

        Livewire::actingAs($this->admin)
            ->test(KybCenter::class)
            ->set('tab', 'sanctions')
            ->assertOk()
            ->assertViewHas('items');
    }

    public function test_run_verifications_dispatches_toast(): void
    {
        $entity = $this->makeEntity();

        Livewire::actingAs($this->admin)
            ->test(KybCenter::class)
            ->call('runVerifications', $entity->id)
            ->assertDispatched('toast');

        $this->assertDatabaseHas('business_verifications', [
            'entity_id' => $entity->id,
            'check_type' => 'identity',
        ]);
    }

    public function test_run_sanctions_dispatches_toast(): void
    {
        $entity = $this->makeEntity();

        Livewire::actingAs($this->admin)
            ->test(KybCenter::class)
            ->call('runSanctions', $entity->id)
            ->assertDispatched('toast');

        $this->assertDatabaseHas('business_sanctions_checks', [
            'entity_id' => $entity->id,
        ]);
    }

    public function test_approve_entity_marks_verified(): void
    {
        $entity = $this->makeEntity();

        Livewire::actingAs($this->admin)
            ->test(KybCenter::class)
            ->call('approveEntity', $entity->id)
            ->assertDispatched('toast');

        $this->assertSame(BusinessEntity::STATUS_VERIFIED, $entity->fresh()->status);
    }

    public function test_reject_entity_with_valid_reason(): void
    {
        $entity = $this->makeEntity();

        Livewire::actingAs($this->admin)
            ->test(KybCenter::class)
            ->call('rejectEntity', $entity->id, 'Documents incohérents et identité non vérifiable.')
            ->assertDispatched('toast');

        $this->assertSame(BusinessEntity::STATUS_REJECTED, $entity->fresh()->status);
    }

    public function test_reject_entity_with_short_reason_dispatches_error_toast(): void
    {
        $entity = $this->makeEntity();

        Livewire::actingAs($this->admin)
            ->test(KybCenter::class)
            ->call('rejectEntity', $entity->id, 'short')
            ->assertDispatched('toast');

        $this->assertNotSame(BusinessEntity::STATUS_REJECTED, $entity->fresh()->status);
    }

    public function test_approve_document_updates_status(): void
    {
        $entity = $this->makeEntity();
        $doc = BusinessDocument::query()->create([
            'entity_id' => $entity->id,
            'document_type' => 'kbis',
            'file_path' => 'kyb_documents_test/x.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 100,
            'uploaded_at' => now(),
            'uploaded_by_user_id' => $this->admin->id,
            'status' => BusinessDocument::STATUS_PENDING,
        ]);

        Livewire::actingAs($this->admin)
            ->test(KybCenter::class)
            ->call('approveDocument', $doc->id)
            ->assertDispatched('toast');

        $this->assertSame(BusinessDocument::STATUS_APPROVED, $doc->fresh()->status);
    }
}
