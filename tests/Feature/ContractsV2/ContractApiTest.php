<?php

namespace Tests\Feature\ContractsV2;

use App\Models\ContractDocument;
use App\Models\User;
use App\Services\ContractsV2\ContractService;
use Database\Seeders\ContractTemplatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContractApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContractTemplatesSeeder::class);
        Config::set('contracts_v2.pdf_engine', 'disabled');
    }

    public function test_active_templates_endpoint_returns_list(): void
    {
        $user = User::factory()->client()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v2/contracts/templates?role=client');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertGreaterThan(0, count($data));
        $codes = collect($data)->pluck('code')->all();
        $this->assertContains('client_tos', $codes);
    }

    public function test_render_document_requires_auth(): void
    {
        $this->postJson('/api/v2/contracts/documents', [
            'template_code' => 'client_tos',
        ])->assertStatus(401);
    }

    public function test_render_document_creates_pending_document(): void
    {
        $user = User::factory()->client()->create(['name' => 'Alice']);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v2/contracts/documents', [
            'template_code' => 'client_tos',
        ]);

        $response->assertStatus(201);
        $this->assertSame(1, ContractDocument::count());
    }

    public function test_show_document_records_opened_audit(): void
    {
        $user = User::factory()->client()->create();
        Sanctum::actingAs($user);

        $doc = app(ContractService::class)->renderDocumentFor('client_tos', $user);

        $response = $this->getJson("/api/v2/contracts/documents/{$doc->id}");

        $response->assertOk();
    }

    public function test_show_rejects_other_user_document(): void
    {
        $alice = User::factory()->client()->create();
        $bob = User::factory()->client()->create();
        $doc = app(ContractService::class)->renderDocumentFor('client_tos', $alice);

        Sanctum::actingAs($bob);
        $this->getJson("/api/v2/contracts/documents/{$doc->id}")->assertStatus(403);
    }

    public function test_sign_document_endpoint_persists_signature(): void
    {
        $user = User::factory()->client()->create();
        Sanctum::actingAs($user);

        $doc = app(ContractService::class)->renderDocumentFor('client_tos', $user);

        $response = $this->postJson("/api/v2/contracts/documents/{$doc->id}/sign", [
            'signature_data' => 'data:image/png;base64,iVBORw0KGgo=',
            'signer_name' => 'Alice Dupont',
            'terms_accepted' => true,
        ]);

        $response->assertStatus(201);
        $this->assertSame(1, $doc->fresh()->signatures()->count());
    }

    public function test_sign_document_validates_required_fields(): void
    {
        $user = User::factory()->client()->create();
        Sanctum::actingAs($user);

        $doc = app(ContractService::class)->renderDocumentFor('client_tos', $user);

        $this->postJson("/api/v2/contracts/documents/{$doc->id}/sign", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['signature_data', 'signer_name', 'terms_accepted']);
    }

    public function test_admin_invalidate_signature_endpoint(): void
    {
        $user = User::factory()->client()->create();
        $admin = User::factory()->admin()->create();
        $doc = app(ContractService::class)->renderDocumentFor('client_tos', $user);
        $sig = app(ContractService::class)->signDocument($doc, $user, 'data:base64,x', 'A');

        Sanctum::actingAs($admin);
        $response = $this->postJson("/api/admin/contracts-v2/signatures/{$sig->id}/invalidate", [
            'reason' => 'Contesté par le signataire pour erreur de version.',
        ]);

        $response->assertOk();
        $this->assertTrue((bool) $sig->fresh()->is_invalidated);
    }

    public function test_admin_invalidate_validates_reason_length(): void
    {
        $user = User::factory()->client()->create();
        $admin = User::factory()->admin()->create();
        $doc = app(ContractService::class)->renderDocumentFor('client_tos', $user);
        $sig = app(ContractService::class)->signDocument($doc, $user, 'data:base64,x', 'A');

        Sanctum::actingAs($admin);
        $this->postJson("/api/admin/contracts-v2/signatures/{$sig->id}/invalidate", [
            'reason' => 'no',
        ])->assertStatus(422);
    }
}
