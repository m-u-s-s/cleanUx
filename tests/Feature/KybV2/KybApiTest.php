<?php

namespace Tests\Feature\KybV2;

use App\Models\BusinessEntity;
use App\Models\User;
use App\Services\KybV2\BusinessOnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class KybApiTest extends TestCase
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

    public function test_start_verification_creates_entity(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v2/kyb/me/entities', [
            'legal_name' => 'Acme SARL',
            'country_code' => 'FR',
            'identifier_type' => 'siret',
            'identifier_value' => '12345678900012',
        ]);
        $response->assertCreated();
        $this->assertSame('Acme SARL', $response->json('entity.legal_name'));
    }

    public function test_start_verification_rejects_invalid_identifier_type_for_country(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v2/kyb/me/entities', [
            'legal_name' => 'Bad',
            'country_code' => 'FR',
            'identifier_type' => 'kbo',
            'identifier_value' => '0123',
        ])->assertStatus(422);
    }

    public function test_list_my_entities_returns_own_only(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        app(BusinessOnboardingService::class)->startVerification([
            'legal_name' => 'Acme', 'country_code' => 'FR',
            'identifier_type' => 'siret', 'identifier_value' => '12345678900012',
        ], $user);
        app(BusinessOnboardingService::class)->startVerification([
            'legal_name' => 'Other', 'country_code' => 'BE',
            'identifier_type' => 'kbo', 'identifier_value' => '0123456789',
        ], $other);

        Sanctum::actingAs($user);
        $response = $this->getJson('/api/v2/kyb/me/entities');
        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_show_my_entity_forbidden_for_non_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $entity = app(BusinessOnboardingService::class)->startVerification([
            'legal_name' => 'Acme', 'country_code' => 'FR',
            'identifier_type' => 'siret', 'identifier_value' => '12345678900012',
        ], $owner);

        Sanctum::actingAs($other);
        $this->getJson("/api/v2/kyb/me/entities/{$entity->id}")->assertStatus(403);
    }

    public function test_upload_document_persists_row(): void
    {
        $user = User::factory()->create();
        $entity = app(BusinessOnboardingService::class)->startVerification([
            'legal_name' => 'Acme', 'country_code' => 'FR',
            'identifier_type' => 'siret', 'identifier_value' => '12345678900012',
        ], $user);

        Sanctum::actingAs($user);
        $file = \Illuminate\Http\UploadedFile::fake()->create('kbis.pdf', 100, 'application/pdf');
        $response = $this->postJson("/api/v2/kyb/me/entities/{$entity->id}/documents", [
            'document_type' => 'kbis',
            'file' => $file,
        ]);
        $response->assertCreated();
        $this->assertSame(1, $entity->documents()->count());
    }

    public function test_admin_run_verifications(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $entity = app(BusinessOnboardingService::class)->startVerification([
            'legal_name' => 'Acme', 'country_code' => 'FR',
            'identifier_type' => 'siret', 'identifier_value' => '12345678900012',
        ], $user);

        Sanctum::actingAs($admin);
        $response = $this->postJson("/api/admin/kyb-v2/entities/{$entity->id}/run-verifications");
        $response->assertOk();
        $this->assertNotNull($response->json('entity.risk_score'));
    }

    public function test_admin_run_sanctions(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $entity = app(BusinessOnboardingService::class)->startVerification([
            'legal_name' => 'Acme', 'country_code' => 'FR',
            'identifier_type' => 'siret', 'identifier_value' => '12345678900012',
        ], $user);

        Sanctum::actingAs($admin);
        $response = $this->postJson("/api/admin/kyb-v2/entities/{$entity->id}/run-sanctions");
        $response->assertOk();
        $this->assertSame(2, $entity->sanctionsChecks()->count());
    }

    public function test_admin_approve_entity(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $entity = app(BusinessOnboardingService::class)->startVerification([
            'legal_name' => 'Acme', 'country_code' => 'FR',
            'identifier_type' => 'siret', 'identifier_value' => '12345678900012',
        ], $user);

        Sanctum::actingAs($admin);
        $this->postJson("/api/admin/kyb-v2/entities/{$entity->id}/approve")->assertOk();
        $this->assertSame(BusinessEntity::STATUS_VERIFIED, $entity->fresh()->status);
    }

    public function test_admin_reject_entity_validates_reason_length(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $entity = app(BusinessOnboardingService::class)->startVerification([
            'legal_name' => 'Acme', 'country_code' => 'FR',
            'identifier_type' => 'siret', 'identifier_value' => '12345678900012',
        ], $user);

        Sanctum::actingAs($admin);
        $this->postJson("/api/admin/kyb-v2/entities/{$entity->id}/reject", [
            'reason' => 'short',
        ])->assertStatus(422);

        $this->postJson("/api/admin/kyb-v2/entities/{$entity->id}/reject", [
            'reason' => 'Documents fournis incomplets et incohérence sur adresse',
        ])->assertOk();
        $this->assertSame(BusinessEntity::STATUS_REJECTED, $entity->fresh()->status);
    }

    public function test_admin_add_beneficial_owner(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $entity = app(BusinessOnboardingService::class)->startVerification([
            'legal_name' => 'Acme', 'country_code' => 'FR',
            'identifier_type' => 'siret', 'identifier_value' => '12345678900012',
        ], $user);

        Sanctum::actingAs($admin);
        $response = $this->postJson("/api/admin/kyb-v2/entities/{$entity->id}/beneficial-owners", [
            'full_name' => 'Jean Dupont',
            'ownership_percent' => 75.5,
            'is_director' => true,
            'is_pep' => false,
        ]);
        $response->assertCreated();
        $this->assertSame(1, $entity->beneficialOwners()->count());
    }

    public function test_unauthenticated_routes_blocked(): void
    {
        $this->postJson('/api/v2/kyb/me/entities', [
            'legal_name' => 'X', 'country_code' => 'FR',
            'identifier_type' => 'siret', 'identifier_value' => '12345678900012',
        ])->assertStatus(401);
    }
}
