<?php

namespace Tests\Feature\Integration;

use App\Models\ContractDocument;
use App\Models\ContractSignature;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\ContractsV2\ContractService;
use Database\Seeders\ContractTemplatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Test E2E Contract v2 : render → sign → vérifier hash + webhook + audit.
 */
class ContractSignatureCascadeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        $this->seed(ContractTemplatesSeeder::class);

        Config::set('contracts_v2.pdf_engine', 'disabled');
        Config::set('contracts_v2.signature_required', true);

        Config::set('webhooks_v2.enabled', true);
        Config::set('webhooks_v2.allowed_events', ['contract.signed']);
    }

    public function test_sign_document_creates_signature_with_hash_and_emits_webhook(): void
    {
        $user = User::factory()->create();
        $svc = app(ContractService::class);

        $doc = $svc->renderDocumentFor('client_tos', $user);
        $this->assertSame(ContractDocument::STATUS_PENDING_SIGNATURE, $doc->status);

        $signature = $svc->signDocument(
            document: $doc,
            signer: $user,
            signatureData: 'data:image/png;base64,SIGNATURE',
            signerName: 'Test User',
            request: Request::create('/test'),
        );

        // Signature persistée avec hash
        $this->assertInstanceOf(ContractSignature::class, $signature);
        $this->assertNotEmpty($signature->signature_hash);
        $this->assertSame(64, strlen((string) $signature->signature_hash));

        // Document marqué signed
        $this->assertSame(ContractDocument::STATUS_SIGNED, $doc->fresh()->status);

        // Webhook contract.signed émis
        $this->assertSame(1, WebhookEvent::query()
            ->where('event_code', 'contract.signed')
            ->where('source_id', $signature->id)
            ->count());
        $event = WebhookEvent::query()->where('event_code', 'contract.signed')->first();
        $this->assertSame($signature->signature_hash, $event->payload['signature_hash']);
        $this->assertSame('client_tos', $event->payload['template_code']);
    }

    public function test_signature_hash_unique_per_signature(): void
    {
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $svc = app(ContractService::class);

        $doc1 = $svc->renderDocumentFor('client_tos', $u1);
        $sig1 = $svc->signDocument($doc1, $u1, 'sig:1', 'A');

        $doc2 = $svc->renderDocumentFor('client_tos', $u2);
        $sig2 = $svc->signDocument($doc2, $u2, 'sig:2', 'B');

        $this->assertNotSame($sig1->signature_hash, $sig2->signature_hash);
    }
}
