<?php

namespace Tests\Feature\Api\Provider;

use App\Models\ProviderOnboardingDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** P0.4 — downloadDocument declared return type Response but returned a BinaryFileResponse (always TypeError). */
class OnboardingDocumentDownloadP0Test extends TestCase
{
    use RefreshDatabase;

    private string $relPath = '';

    private function makeDoc(User $owner): ProviderOnboardingDocument
    {
        // The controller streams via response()->file(storage_path('app/private/'.$path)) — a real
        // filesystem read — so write to the real 'private' disk (root = storage_path('app/private')).
        $this->relPath = 'providers/'.$owner->id.'/doc.pdf';
        Storage::disk('private')->put($this->relPath, 'PDFDATA');

        return ProviderOnboardingDocument::query()->create([
            'user_id' => $owner->id,
            'document_type' => ProviderOnboardingDocument::TYPE_IDENTITY_CARD,
            'file_path' => $this->relPath,
            'status' => 'pending',
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->relPath !== '') {
            Storage::disk('private')->delete($this->relPath);
        }
        parent::tearDown();
    }

    public function test_admin_can_download_provider_document_no_type_error(): void
    {
        $provider = User::factory()->employe()->create();
        $doc = $this->makeDoc($provider);
        $admin = User::factory()->admin()->create();

        // Before P0.4 this threw a TypeError (Response vs BinaryFileResponse) instead of streaming.
        $this->actingAs($admin)
            ->get('/api/admin/onboarding-documents/'.$doc->id.'/file')
            ->assertOk();
    }

    public function test_non_admin_is_forbidden(): void
    {
        $provider = User::factory()->employe()->create();
        $doc = $this->makeDoc($provider);
        $client = User::factory()->client()->create();

        $this->actingAs($client)
            ->get('/api/admin/onboarding-documents/'.$doc->id.'/file')
            ->assertForbidden();
    }
}
