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

    /**
     * LE CHEMIN QUE PREND UN CLIENT MOBILE : un jeton Bearer, pas une session.
     *
     * Les deux tests ci-dessus passaient AVANT ET APRES la correction. `actingAs()` pose
     * l'utilisateur DIRECTEMENT sur le garde, et Sanctum retombe sur le garde web quand
     * aucun jeton n'est present : le chemin reel n'etait exerce dans aucun sens.
     *
     * La route portait `auth` sans garde nomme — donc `web`, pilote par la SESSION — dans un
     * groupe `api` qui n'en demarre aucune. Ce test ECHOUAIT alors avec 401.
     */
    public function test_un_jeton_bearer_ouvre_le_document(): void
    {
        $provider = User::factory()->employe()->create();
        $doc = $this->makeDoc($provider);
        $admin = User::factory()->admin()->create();

        $jeton = $admin->createToken('test-admin')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$jeton)
            ->getJson('/api/admin/onboarding-documents/'.$doc->id.'/file')
            ->assertOk();
    }

    /**
     * TEMOIN. Sans lui, le test ci-dessus passerait au vert si la route cessait de garder
     * quoi que ce soit : il mesurerait l'absence de porte, pas la bonne porte.
     */
    public function test_temoin_un_jeton_de_client_reste_refuse(): void
    {
        $provider = User::factory()->employe()->create();
        $doc = $this->makeDoc($provider);
        $client = User::factory()->client()->create();

        $jeton = $client->createToken('test-client')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$jeton)
            ->getJson('/api/admin/onboarding-documents/'.$doc->id.'/file')
            ->assertForbidden();
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
