<?php

namespace Tests\Feature\Onboarding;

use App\Models\ProviderOnboardingDocument;
use App\Models\Trade;
use App\Models\User;
use App\Services\Onboarding\ProviderDocumentRequirements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Quels justificatifs sont demandés, à qui.
 *
 * L'application n'en connaissait qu'un, écrit en dur : `identity_card`. Un électricien n'était
 * donc jamais invité à déposer sa certification, ni un peintre son attestation d'assurance —
 * alors que `approveOnboarding()` EXIGE une assurance approuvée. Le parcours menait à un dossier
 * complet côté prestataire et impossible à approuver côté admin, sans que rien ne l'indique.
 */
class ProviderDocumentRequirementsTest extends TestCase
{
    use RefreshDatabase;

    private const ROUTE = '/api/provider/onboarding/documents';

    public function test_a_trade_without_obligations_only_asks_for_identity(): void
    {
        $user = $this->providerWithTrade($this->trade('GRD', insurance: false, certification: false));

        $types = app(ProviderDocumentRequirements::class)->requiredTypesFor($user);

        $this->assertSame([ProviderOnboardingDocument::TYPE_IDENTITY_CARD], $types);
    }

    public function test_a_trade_requiring_insurance_asks_for_it(): void
    {
        $user = $this->providerWithTrade($this->trade('PNT', insurance: true, certification: false));

        $this->assertContains(
            ProviderOnboardingDocument::TYPE_INSURANCE,
            app(ProviderDocumentRequirements::class)->requiredTypesFor($user),
        );
    }

    public function test_a_regulated_trade_asks_for_its_certification(): void
    {
        $user = $this->providerWithTrade($this->trade('ELC', insurance: true, certification: true));

        $types = app(ProviderDocumentRequirements::class)->requiredTypesFor($user);

        $this->assertContains(ProviderOnboardingDocument::TYPE_DIPLOMA, $types);
        $this->assertContains(ProviderOnboardingDocument::TYPE_INSURANCE, $types);
    }

    /** Métiers auprès de personnes vulnérables : la liste est en configuration, pas en base. */
    public function test_a_childcare_trade_asks_for_a_criminal_record(): void
    {
        config()->set('onboarding_documents.criminal_record_trades', ['CHD']);
        $user = $this->providerWithTrade($this->trade('CHD', insurance: false, certification: false));

        $this->assertContains(
            ProviderOnboardingDocument::TYPE_CRIMINAL_RECORD,
            app(ProviderDocumentRequirements::class)->requiredTypesFor($user),
        );
    }

    /** Une pièce d'identité vaut carte, passeport OU titre de séjour : une seule suffit. */
    public function test_a_passport_satisfies_the_identity_requirement(): void
    {
        $requirements = app(ProviderDocumentRequirements::class);

        $this->assertTrue($requirements->isSatisfied(
            ProviderOnboardingDocument::TYPE_IDENTITY_CARD,
            [ProviderOnboardingDocument::TYPE_PASSPORT],
        ));
        $this->assertFalse($requirements->isSatisfied(
            ProviderOnboardingDocument::TYPE_INSURANCE,
            [ProviderOnboardingDocument::TYPE_PASSPORT],
        ));
    }

    public function test_the_endpoint_lists_each_requirement_with_its_document(): void
    {
        Storage::fake('private');
        $user = $this->providerWithTrade($this->trade('PNT', insurance: true, certification: false));

        $this->actingAs($user, 'sanctum')->postJson('/api/provider/onboarding/documents', [
            'document_type' => ProviderOnboardingDocument::TYPE_IDENTITY_CARD,
            'file' => UploadedFile::fake()->create('cni.pdf', 100, 'application/pdf'),
        ])->assertCreated();

        $response = $this->actingAs($user, 'sanctum')->getJson(self::ROUTE)->assertOk();

        $requirements = collect($response->json('requirements'));
        $identity = $requirements->firstWhere('type', ProviderOnboardingDocument::TYPE_IDENTITY_CARD);
        $insurance = $requirements->firstWhere('type', ProviderOnboardingDocument::TYPE_INSURANCE);

        $this->assertSame('pending_review', $identity['document']['status']);
        $this->assertNotNull($insurance, "l'assurance doit être demandée pour ce métier");
        $this->assertNull($insurance['document'], "l'assurance n'a pas encore été déposée");
    }

    /**
     * Le motif de refus est ce qui rend un rejet actionnable : sans lui, le prestataire redépose
     * la même pièce et se fait refuser une seconde fois.
     */
    public function test_a_rejected_document_carries_its_reason(): void
    {
        Storage::fake('private');
        $user = $this->providerWithTrade($this->trade('GRD', insurance: false, certification: false));

        $this->actingAs($user, 'sanctum')->postJson('/api/provider/onboarding/documents', [
            'document_type' => ProviderOnboardingDocument::TYPE_IDENTITY_CARD,
            'file' => UploadedFile::fake()->create('cni.pdf', 100, 'application/pdf'),
        ])->assertCreated();

        ProviderOnboardingDocument::query()->where('user_id', $user->id)->update([
            'status' => ProviderOnboardingDocument::STATUS_REJECTED,
            'rejection_reason' => 'Document illisible : les quatre coins doivent être visibles.',
        ]);

        $this->actingAs($user, 'sanctum')->getJson(self::ROUTE)
            ->assertOk()
            ->assertJsonPath('requirements.0.document.status', 'rejected')
            ->assertJsonPath(
                'requirements.0.document.rejection_reason',
                'Document illisible : les quatre coins doivent être visibles.',
            );
    }

    /** Le prestataire ne voit que ses propres pièces. */
    public function test_documents_are_scoped_to_their_owner(): void
    {
        Storage::fake('private');
        $trade = $this->trade('GRD', insurance: false, certification: false);
        $mine = $this->providerWithTrade($trade);
        $other = $this->providerWithTrade($trade, 'autre@prestataire.test');

        $this->actingAs($other, 'sanctum')->postJson('/api/provider/onboarding/documents', [
            'document_type' => ProviderOnboardingDocument::TYPE_IDENTITY_CARD,
            'file' => UploadedFile::fake()->create('cni.pdf', 100, 'application/pdf'),
        ])->assertCreated();

        $this->actingAs($mine, 'sanctum')->getJson(self::ROUTE)
            ->assertOk()
            ->assertJsonCount(0, 'documents');
    }

    private function trade(string $code, bool $insurance, bool $certification): Trade
    {
        return Trade::query()->create([
            'code' => $code,
            'name' => "Métier {$code}",
            'slug' => strtolower($code).'-test',
            'is_active' => true,
            'requires_insurance_proof' => $insurance,
            'requires_certification' => $certification,
        ]);
    }

    private function providerWithTrade(Trade $trade, string $email = 'prestataire@test.be'): User
    {
        $user = User::factory()->employe()->create(['email' => $email]);
        DB::table('trade_user')->insert([
            'user_id' => $user->id,
            'trade_id' => $trade->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }
}
