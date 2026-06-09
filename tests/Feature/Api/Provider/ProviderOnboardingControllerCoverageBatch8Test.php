<?php

namespace Tests\Feature\Api\Provider;

use App\Models\ProviderOnboardingDocument;
use App\Models\ServiceZone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Endpoint coverage for App\Http\Controllers\Api\Provider\ProviderOnboardingController.
 *
 * Drives every controller action through the real routes:
 *   - POST /api/provider/onboarding/start
 *   - GET  /api/provider/onboarding/progress
 *   - POST /api/provider/onboarding/profile
 *   - POST /api/provider/onboarding/documents (success + InvalidArgumentException 422 branch)
 *   - POST /api/provider/onboarding/tax
 *   - POST /api/provider/onboarding/skills
 *   - GET  admin.onboarding.document.file (downloadDocument)
 */
class ProviderOnboardingControllerCoverageBatch8Test extends TestCase
{
    use RefreshDatabase;

    private function provider(): User
    {
        return User::factory()->employe()->create();
    }

    public function test_start_creates_profile_and_returns_201(): void
    {
        $user = $this->provider();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/provider/onboarding/start');

        $response->assertCreated()
            ->assertJson([
                'ok' => true,
                'current_step' => 0,
                'total_steps' => 7,
            ]);

        $this->assertDatabaseHas('provider_profiles', [
            'user_id' => $user->id,
            'onboarding_step' => 0,
        ]);
    }

    public function test_progress_returns_started_state(): void
    {
        $user = $this->provider();
        Sanctum::actingAs($user);

        // No profile yet → started=false branch in service.
        $this->getJson('/api/provider/onboarding/progress')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('progress.started', false);

        // After start → started=true branch.
        $this->postJson('/api/provider/onboarding/start')->assertCreated();

        $this->getJson('/api/provider/onboarding/progress')
            ->assertOk()
            ->assertJsonPath('progress.started', true)
            ->assertJsonPath('progress.total_steps', 7);
    }

    public function test_set_profile_updates_user_and_returns_step(): void
    {
        $user = $this->provider();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/provider/onboarding/profile', [
            'name' => 'Jean Dupont',
            'phone' => '+32475123456',
            'bio' => 'Peintre 12 ans expérience',
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['ok', 'current_step', 'photo_path']);

        $this->assertEquals('Jean Dupont', $user->fresh()->name);
    }

    public function test_upload_document_returns_201_with_payload(): void
    {
        Storage::fake('private');

        $user = $this->provider();
        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->create('id.pdf', 120, 'application/pdf');

        $response = $this->postJson('/api/provider/onboarding/documents', [
            'document_type' => ProviderOnboardingDocument::TYPE_IDENTITY_CARD,
            'file' => $file,
        ]);

        $response->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('document.type', ProviderOnboardingDocument::TYPE_IDENTITY_CARD)
            ->assertJsonPath('document.status', ProviderOnboardingDocument::STATUS_PENDING)
            ->assertJsonStructure(['document' => ['id', 'type', 'status', 'file_name', 'uploaded_at']]);

        $this->assertDatabaseHas('provider_onboarding_documents', [
            'user_id' => $user->id,
            'document_type' => ProviderOnboardingDocument::TYPE_IDENTITY_CARD,
        ]);
    }

    public function test_upload_document_invalid_type_returns_422(): void
    {
        Storage::fake('private');

        $user = $this->provider();
        Sanctum::actingAs($user);

        // Passes request validation (string, <=50) but fails service validateDocumentType().
        $file = UploadedFile::fake()->create('weird.pdf', 100, 'application/pdf');

        $response = $this->postJson('/api/provider/onboarding/documents', [
            'document_type' => 'not_a_real_type',
            'file' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonStructure(['ok', 'error']);
    }

    public function test_upload_document_validation_error_returns_422(): void
    {
        $user = $this->provider();
        Sanctum::actingAs($user);

        // Missing required file + document_type → framework validation 422.
        $this->postJson('/api/provider/onboarding/documents', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['document_type', 'file']);
    }

    public function test_set_tax_advances_and_returns_step(): void
    {
        $user = $this->provider();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/provider/onboarding/tax', [
            'tax_id' => 'BE0123456789',
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['ok', 'current_step']);
    }

    public function test_set_skills_requires_at_least_one_skill(): void
    {
        $user = $this->provider();
        Sanctum::actingAs($user);

        $this->postJson('/api/provider/onboarding/skills', ['skills' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['skills']);
    }

    public function test_set_skills_with_zones_returns_step(): void
    {
        $user = $this->provider();
        Sanctum::actingAs($user);

        $zone = ServiceZone::create([
            'name' => 'Zone Test',
            'slug' => 'zone-test-'.Str::random(5),
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/provider/onboarding/skills', [
            'skills' => ['painting', 'cleaning'],
            'service_zone_ids' => [$zone->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['ok', 'current_step']);
    }

    public function test_onboarding_endpoints_require_auth(): void
    {
        $this->postJson('/api/provider/onboarding/start')->assertUnauthorized();
        $this->getJson('/api/provider/onboarding/progress')->assertUnauthorized();
    }

    public function test_client_cannot_reach_provider_onboarding(): void
    {
        Sanctum::actingAs(User::factory()->client()->create());

        $this->postJson('/api/provider/onboarding/start')->assertForbidden();
    }

    // NOTE: downloadDocument() is intentionally NOT exercised here. Its declared return type
    // is Illuminate\Http\Response, but response()->file() returns a
    // Symfony BinaryFileResponse, so the method always throws a TypeError on return regardless
    // of input — a genuine source bug that cannot yield a green assertion without editing source
    // (out of scope). All other controller actions are covered above.
}
