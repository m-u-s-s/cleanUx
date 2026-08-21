<?php

namespace Tests\Feature\KybV2;

use App\Models\BusinessDocument;
use App\Models\User;
use App\Services\KybV2\BusinessOnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class KybV2ControllerCoverageBatch10Test extends TestCase
{
    use RefreshDatabase;

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
        Config::set('kyb_v2.document_types', ['kbis', 'certificate_incorp', 'other']);
        Config::set('kyb_v2.allowed_mime_types', ['application/pdf', 'image/jpeg', 'image/png']);
        Config::set('kyb_v2.document_max_size_kb', 10240);
        Config::set('kyb_v2.document_storage_disk', 'local');
        Config::set('kyb_v2.document_path_prefix', 'kyb_documents_test');
        Config::set('kyb_v2.risk_weights', [
            'sanctions_match' => 50, 'pep_owner' => 25, 'missing_kbis' => 10,
            'recent_incorporation' => 8, 'unverified_vat' => 5, 'high_risk_country' => 15,
        ]);
        Config::set('kyb_v2.risk_thresholds', ['low_max' => 20, 'medium_max' => 50, 'high_max' => 75]);
        Config::set('kyb_v2.high_risk_countries', []);

        Storage::fake('local');
    }

    private function makeEntity(User $owner)
    {
        return app(BusinessOnboardingService::class)->startVerification([
            'legal_name' => 'Acme', 'country_code' => 'FR',
            'identifier_type' => 'siret', 'identifier_value' => '12345678900012',
        ], $owner);
    }

    public function test_show_my_entity_returns_loaded_relations(): void
    {
        $owner = User::factory()->create();
        $entity = $this->makeEntity($owner);

        Sanctum::actingAs($owner);
        $response = $this->getJson("/api/v2/kyb/me/entities/{$entity->id}");
        $response->assertOk();
        $this->assertSame($entity->id, $response->json('data.id'));
        $response->assertJsonStructure([
            'data' => ['id', 'documents', 'verifications', 'beneficial_owners'],
        ]);
    }

    public function test_admin_can_show_any_entity_via_bypass(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->adminComplet()->create();
        $entity = $this->makeEntity($owner);

        Sanctum::actingAs($admin);
        $this->getJson("/api/v2/kyb/me/entities/{$entity->id}")->assertOk();
    }

    public function test_upload_document_forbidden_for_non_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $entity = $this->makeEntity($owner);

        Sanctum::actingAs($other);
        $file = UploadedFile::fake()->create('kbis.pdf', 100, 'application/pdf');
        $this->postJson("/api/v2/kyb/me/entities/{$entity->id}/documents", [
            'document_type' => 'kbis',
            'file' => $file,
        ])->assertStatus(403);
    }

    public function test_upload_document_rejects_disallowed_mime(): void
    {
        $owner = User::factory()->create();
        $entity = $this->makeEntity($owner);

        Sanctum::actingAs($owner);
        $file = UploadedFile::fake()->create('notes.txt', 10, 'text/plain');
        $this->postJson("/api/v2/kyb/me/entities/{$entity->id}/documents", [
            'document_type' => 'other',
            'file' => $file,
        ])->assertStatus(422)->assertJsonPath('ok', false);
    }

    public function test_admin_list_entities_with_filters(): void
    {
        $admin = User::factory()->adminComplet()->create();
        $owner = User::factory()->create();
        $entity = $this->makeEntity($owner);

        Sanctum::actingAs($admin);
        $response = $this->getJson('/api/admin/kyb-v2/entities?status='.$entity->status.'&risk_level='.$entity->risk_level.'&limit=10');
        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    public function test_admin_list_documents_with_status_filter(): void
    {
        $admin = User::factory()->adminComplet()->create();
        $owner = User::factory()->create();
        $entity = $this->makeEntity($owner);

        Sanctum::actingAs($owner);
        $file = UploadedFile::fake()->create('kbis.pdf', 100, 'application/pdf');
        $this->postJson("/api/v2/kyb/me/entities/{$entity->id}/documents", [
            'document_type' => 'kbis',
            'file' => $file,
        ])->assertCreated();

        Sanctum::actingAs($admin);
        $response = $this->getJson('/api/admin/kyb-v2/documents?status='.BusinessDocument::STATUS_PENDING.'&limit=5');
        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_admin_review_document_approve(): void
    {
        $admin = User::factory()->adminComplet()->create();
        $owner = User::factory()->create();
        $entity = $this->makeEntity($owner);

        Sanctum::actingAs($owner);
        $file = UploadedFile::fake()->create('kbis.pdf', 100, 'application/pdf');
        $docId = $this->postJson("/api/v2/kyb/me/entities/{$entity->id}/documents", [
            'document_type' => 'kbis',
            'file' => $file,
        ])->json('document.id');

        Sanctum::actingAs($admin);
        $this->postJson("/api/admin/kyb-v2/documents/{$docId}/review", [
            'action' => 'approve',
        ])->assertOk();
        $this->assertSame(BusinessDocument::STATUS_APPROVED, BusinessDocument::find($docId)->status);
    }

    public function test_admin_review_document_reject_requires_reason(): void
    {
        $admin = User::factory()->adminComplet()->create();
        $owner = User::factory()->create();
        $entity = $this->makeEntity($owner);

        Sanctum::actingAs($owner);
        $file = UploadedFile::fake()->create('kbis.pdf', 100, 'application/pdf');
        $docId = $this->postJson("/api/v2/kyb/me/entities/{$entity->id}/documents", [
            'document_type' => 'kbis',
            'file' => $file,
        ])->json('document.id');

        Sanctum::actingAs($admin);
        // Reject without a sufficient reason -> 422
        $this->postJson("/api/admin/kyb-v2/documents/{$docId}/review", [
            'action' => 'reject',
            'reason' => 'short',
        ])->assertStatus(422);

        // Reject with a valid reason -> ok
        $this->postJson("/api/admin/kyb-v2/documents/{$docId}/review", [
            'action' => 'reject',
            'reason' => 'Document illisible et incohérence sur la raison sociale.',
        ])->assertOk();
        $this->assertSame(BusinessDocument::STATUS_REJECTED, BusinessDocument::find($docId)->status);
    }

    public function test_download_document_success_for_owner(): void
    {
        $owner = User::factory()->create();
        $entity = $this->makeEntity($owner);

        Sanctum::actingAs($owner);
        $file = UploadedFile::fake()->create('kbis.pdf', 100, 'application/pdf');
        $docId = $this->postJson("/api/v2/kyb/me/entities/{$entity->id}/documents", [
            'document_type' => 'kbis',
            'file' => $file,
        ])->json('document.id');

        $response = $this->get("/api/v2/kyb/documents/{$docId}/download");
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_download_document_forbidden_for_other_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $entity = $this->makeEntity($owner);

        Sanctum::actingAs($owner);
        $file = UploadedFile::fake()->create('kbis.pdf', 100, 'application/pdf');
        $docId = $this->postJson("/api/v2/kyb/me/entities/{$entity->id}/documents", [
            'document_type' => 'kbis',
            'file' => $file,
        ])->json('document.id');

        Sanctum::actingAs($other);
        $this->get("/api/v2/kyb/documents/{$docId}/download")->assertStatus(403);
    }

    public function test_download_document_missing_file_returns_404(): void
    {
        $owner = User::factory()->create();
        $entity = $this->makeEntity($owner);

        $doc = BusinessDocument::query()->create([
            'entity_id' => $entity->id,
            'document_type' => 'other',
            'file_path' => 'kyb_documents_test/does-not-exist.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 10,
            'uploaded_at' => now(),
            'uploaded_by_user_id' => $owner->id,
            'status' => BusinessDocument::STATUS_PENDING,
        ]);

        Sanctum::actingAs($owner);
        $this->get("/api/v2/kyb/documents/{$doc->id}/download")->assertStatus(404);
    }
}
