<?php

namespace Tests\Feature\ContractsV2;

use App\Models\ContractDocument;
use App\Models\ContractSignature;
use App\Models\ContractSignatureAudit;
use App\Models\User;
use App\Services\ContractsV2\ContractService;
use Database\Seeders\ContractTemplatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ContractServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContractTemplatesSeeder::class);
        Config::set('contracts_v2.enabled', true);
        Config::set('contracts_v2.pdf_engine', 'disabled');
        Config::set('contracts_v2.signature_required', true);
    }

    public function test_render_document_for_creates_pending_document(): void
    {
        $user = User::factory()->client()->create(['name' => 'Alice', 'email' => 'alice@test.com']);

        $doc = app(ContractService::class)->renderDocumentFor('client_tos', $user);

        $this->assertInstanceOf(ContractDocument::class, $doc);
        $this->assertSame(ContractDocument::STATUS_PENDING_SIGNATURE, $doc->status);
        $this->assertStringContainsString('Alice', $doc->body_rendered_html);
        $this->assertSame($user->id, $doc->user_id);

        // Sent audit recorded
        $this->assertSame(1, ContractSignatureAudit::query()
            ->where('document_id', $doc->id)
            ->where('event', 'sent')
            ->count());
    }

    public function test_render_rejects_unknown_template(): void
    {
        $user = User::factory()->client()->create();

        $this->expectException(ValidationException::class);
        app(ContractService::class)->renderDocumentFor('nonexistent', $user);
    }

    public function test_sign_document_creates_signature_with_hash(): void
    {
        $user = User::factory()->client()->create();
        $doc = app(ContractService::class)->renderDocumentFor('client_tos', $user);

        $sig = app(ContractService::class)->signDocument(
            document: $doc,
            signer: $user,
            signatureData: 'data:image/png;base64,iVBORw0KGgo=',
            signerName: 'Alice Dupont',
        );

        $this->assertInstanceOf(ContractSignature::class, $sig);
        $this->assertSame(64, strlen($sig->signature_hash));
        $this->assertSame('Alice Dupont', $sig->signer_name);
        $this->assertNotNull($sig->signer_email_hash);
        $this->assertFalse((bool) $sig->is_invalidated);

        $doc->refresh();
        $this->assertSame(ContractDocument::STATUS_SIGNED, $doc->status);
    }

    public function test_sign_rejects_other_user(): void
    {
        $alice = User::factory()->client()->create();
        $bob = User::factory()->client()->create();
        $doc = app(ContractService::class)->renderDocumentFor('client_tos', $alice);

        $this->expectException(ValidationException::class);
        app(ContractService::class)->signDocument($doc, $bob, 'data:base64,xyz', 'Bob');
    }

    public function test_sign_rejects_already_signed_document(): void
    {
        $user = User::factory()->client()->create();
        $doc = app(ContractService::class)->renderDocumentFor('client_tos', $user);

        app(ContractService::class)->signDocument($doc, $user, 'data:base64,xyz', 'Alice');

        $this->expectException(ValidationException::class);
        app(ContractService::class)->signDocument($doc->fresh(), $user, 'data:base64,abc', 'Alice');
    }

    public function test_sign_rejects_empty_signature_when_required(): void
    {
        $user = User::factory()->client()->create();
        $doc = app(ContractService::class)->renderDocumentFor('client_tos', $user);

        $this->expectException(ValidationException::class);
        app(ContractService::class)->signDocument($doc, $user, '', 'Alice');
    }

    public function test_invalidate_signature_records_audit(): void
    {
        $user = User::factory()->client()->create();
        $admin = User::factory()->admin()->create();
        $doc = app(ContractService::class)->renderDocumentFor('client_tos', $user);
        $sig = app(ContractService::class)->signDocument($doc, $user, 'data:base64,xyz', 'Alice');

        $invalidated = app(ContractService::class)->invalidateSignature(
            $sig, $admin, 'Signature contestée par le user, RGPD request.',
        );

        $this->assertTrue((bool) $invalidated->is_invalidated);
        $this->assertSame($admin->id, (int) $invalidated->invalidated_by_user_id);
        $this->assertFalse($invalidated->isValid());

        $this->assertSame(1, ContractSignatureAudit::query()
            ->where('signature_id', $sig->id)
            ->where('event', 'invalidated')
            ->count());
    }

    public function test_invalidate_rejects_short_reason(): void
    {
        $user = User::factory()->client()->create();
        $admin = User::factory()->admin()->create();
        $doc = app(ContractService::class)->renderDocumentFor('client_tos', $user);
        $sig = app(ContractService::class)->signDocument($doc, $user, 'x', 'A');

        $this->expectException(ValidationException::class);
        app(ContractService::class)->invalidateSignature($sig, $admin, 'short');
    }

    public function test_user_has_valid_signature_for_template_code(): void
    {
        $user = User::factory()->client()->create();
        $svc = app(ContractService::class);

        $this->assertFalse($svc->userHasValidSignatureFor($user, 'client_tos'));

        $doc = $svc->renderDocumentFor('client_tos', $user);
        $svc->signDocument($doc, $user, 'data:base64,xyz', 'Alice');

        $this->assertTrue($svc->userHasValidSignatureFor($user, 'client_tos'));

        // Different template → still false
        $this->assertFalse($svc->userHasValidSignatureFor($user, 'provider_agreement'));
    }
}
