<?php

namespace Tests\Feature\Client;

use App\Livewire\Client\ClientContractSign;
use App\Models\ContractDocument;
use App\Models\ContractTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClientContractSignCoverageBatch16Test extends TestCase
{
    use RefreshDatabase;

    public function test_mount_prefills_signer_name_from_authenticated_user(): void
    {
        $user = User::factory()->client()->create(['name' => 'Alice Martin']);

        Livewire::actingAs($user)
            ->test(ClientContractSign::class)
            ->assertOk()
            ->assertSet('signerName', 'Alice Martin');
    }

    public function test_select_document_rejects_foreign_document(): void
    {
        $user = User::factory()->client()->create();
        $other = User::factory()->client()->create();
        $doc = ContractDocument::factory()->pendingSignature()->create(['user_id' => $other->id]);

        Livewire::actingAs($user)
            ->test(ClientContractSign::class)
            ->call('selectDocument', $doc->id)
            ->assertDispatched('toast')
            ->assertSet('documentId', null);
    }

    public function test_select_document_accepts_own_document_and_audits_opened(): void
    {
        $user = User::factory()->client()->create();
        $doc = ContractDocument::factory()->pendingSignature()->create(['user_id' => $user->id]);

        Livewire::actingAs($user)
            ->test(ClientContractSign::class)
            ->call('selectDocument', $doc->id)
            ->assertSet('documentId', $doc->id);

        $this->assertDatabaseHas('contract_signature_audits', [
            'document_id' => $doc->id,
            'event' => 'opened',
        ]);
    }

    public function test_render_from_template_generates_pending_document(): void
    {
        $user = User::factory()->client()->create();
        $template = ContractTemplate::factory()->create([
            'code' => 'CLIENT-TOS-X',
            'role' => ContractTemplate::ROLE_CLIENT,
            'type' => ContractTemplate::TYPE_TOS,
            'is_active' => true,
            'valid_from' => now()->subDay()->toDateString(),
        ]);

        $component = Livewire::actingAs($user)
            ->test(ClientContractSign::class)
            ->call('renderFromTemplate', $template->code)
            ->assertDispatched('toast');

        $this->assertNotNull($component->get('documentId'));
        $this->assertDatabaseHas('contract_documents', [
            'id' => $component->get('documentId'),
            'user_id' => $user->id,
            'status' => ContractDocument::STATUS_PENDING_SIGNATURE,
        ]);
    }

    public function test_render_from_template_handles_unknown_template(): void
    {
        $user = User::factory()->client()->create();

        Livewire::actingAs($user)
            ->test(ClientContractSign::class)
            ->call('renderFromTemplate', 'NOPE-DOES-NOT-EXIST')
            ->assertDispatched('toast')
            ->assertSet('documentId', null);
    }

    public function test_sign_without_selected_document_dispatches_error(): void
    {
        $user = User::factory()->client()->create();

        Livewire::actingAs($user)
            ->test(ClientContractSign::class)
            ->set('documentId', null)
            ->call('sign')
            ->assertDispatched('toast');
    }

    public function test_sign_validates_required_fields(): void
    {
        $user = User::factory()->client()->create();
        $doc = ContractDocument::factory()->pendingSignature()->create(['user_id' => $user->id]);

        Livewire::actingAs($user)
            ->test(ClientContractSign::class)
            ->set('documentId', $doc->id)
            ->set('signerName', '')
            ->set('signatureData', '')
            ->set('termsAccepted', false)
            ->call('sign')
            ->assertHasErrors(['signerName', 'signatureData', 'termsAccepted']);
    }

    public function test_sign_rejects_foreign_document_after_validation(): void
    {
        $user = User::factory()->client()->create();
        $other = User::factory()->client()->create();
        $doc = ContractDocument::factory()->pendingSignature()->create(['user_id' => $other->id]);

        Livewire::actingAs($user)
            ->test(ClientContractSign::class)
            ->set('documentId', $doc->id)
            ->set('signerName', 'Hacker')
            ->set('signatureData', str_repeat('A', 80))
            ->set('termsAccepted', true)
            ->call('sign')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $this->assertDatabaseMissing('contract_signatures', ['document_id' => $doc->id]);
    }

    public function test_sign_persists_signature_for_valid_pending_document(): void
    {
        $user = User::factory()->client()->create();
        $doc = ContractDocument::factory()->pendingSignature()->create(['user_id' => $user->id]);

        Livewire::actingAs($user)
            ->test(ClientContractSign::class)
            ->set('documentId', $doc->id)
            ->set('signerName', 'Bob Client')
            ->set('signatureData', 'data:image/png;base64,'.str_repeat('Q', 120))
            ->set('termsAccepted', true)
            ->set('countryCode', 'BE')
            ->call('sign')
            ->assertHasNoErrors()
            ->assertSet('signatureData', '')
            ->assertDispatched('toast');

        $this->assertDatabaseHas('contract_signatures', [
            'document_id' => $doc->id,
            'signer_user_id' => $user->id,
            'country_code' => 'BE',
        ]);
        $this->assertSame(
            ContractDocument::STATUS_SIGNED,
            ContractDocument::find($doc->id)->status,
        );
    }

    public function test_computed_collections_scope_to_authenticated_user(): void
    {
        $user = User::factory()->client()->create();
        $other = User::factory()->client()->create();

        $pending = ContractDocument::factory()->pendingSignature()->create(['user_id' => $user->id]);
        $signed = ContractDocument::factory()->create([
            'user_id' => $user->id,
            'status' => ContractDocument::STATUS_SIGNED,
        ]);
        ContractDocument::factory()->pendingSignature()->create(['user_id' => $other->id]);

        ContractTemplate::factory()->create([
            'code' => 'AVAIL-CLIENT',
            'role' => ContractTemplate::ROLE_CLIENT,
            'is_active' => true,
            'valid_from' => now()->subDay()->toDateString(),
        ]);
        ContractTemplate::factory()->create([
            'code' => 'AVAIL-PROVIDER',
            'role' => ContractTemplate::ROLE_PROVIDER,
            'is_active' => true,
            'valid_from' => now()->subDay()->toDateString(),
        ]);

        $component = Livewire::actingAs($user)->test(ClientContractSign::class);

        $pendingIds = $component->instance()->pendingDocuments()->pluck('id')->all();
        $signedIds = $component->instance()->signedDocuments()->pluck('id')->all();
        $templateCodes = $component->instance()->availableTemplates()->pluck('code')->all();

        $this->assertContains($pending->id, $pendingIds);
        $this->assertContains($signed->id, $signedIds);
        $this->assertContains('AVAIL-CLIENT', $templateCodes);
        $this->assertNotContains('AVAIL-PROVIDER', $templateCodes);
    }

    public function test_current_document_returns_null_without_id_and_doc_when_owned(): void
    {
        $user = User::factory()->client()->create();
        $doc = ContractDocument::factory()->pendingSignature()->create(['user_id' => $user->id]);

        $component = Livewire::actingAs($user)->test(ClientContractSign::class);
        $this->assertNull($component->instance()->currentDocument());

        $owned = Livewire::actingAs($user)
            ->test(ClientContractSign::class, ['documentId' => $doc->id]);
        $resolved = $owned->instance()->currentDocument();
        $this->assertInstanceOf(ContractDocument::class, $resolved);
        $this->assertSame($doc->id, $resolved->id);
    }
}
