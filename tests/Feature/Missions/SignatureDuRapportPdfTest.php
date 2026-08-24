<?php

namespace Tests\Feature\Missions;

use App\Models\ContractDocument;
use App\Models\ContractSignature;
use App\Models\Mission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le rapport PDF affiche la signature du client.
 *
 * Il lisait `mission->client_signature_path`, une colonne qui n'existe sur aucune table : le bloc
 * etait donc toujours vide, sans que rien ne le signale. La signature vit dans le document
 * contractuel que `MissionClosureService::signerParLeClient()` fait signer.
 */
class SignatureDuRapportPdfTest extends TestCase
{
    use RefreshDatabase;

    private const TRACE = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUg==';

    private function signer(Mission $mission, string $trace = self::TRACE, bool $invalidee = false): void
    {
        $document = ContractDocument::factory()->create(['code' => 'mission-report-'.$mission->id]);

        ContractSignature::factory()->create([
            'document_id' => $document->id,
            'signer_user_id' => User::factory()->create()->id,
            'signature_data' => $trace,
            'is_invalidated' => $invalidee,
            'signed_at' => now(),
        ]);
    }

    public function test_une_mission_signee_rend_le_trace(): void
    {
        $mission = Mission::factory()->create();
        $this->signer($mission);

        $this->assertSame(self::TRACE, $mission->traceDeLaSignatureClient());
    }

    /** TEMOIN NEGATIF — sans lui, une methode qui rendrait toujours `null` passerait pour correcte. */
    public function test_une_mission_non_signee_ne_rend_rien(): void
    {
        $this->assertNull(Mission::factory()->create()->traceDeLaSignatureClient());
    }

    public function test_une_signature_invalidee_ne_compte_pas(): void
    {
        $mission = Mission::factory()->create();
        $this->signer($mission, invalidee: true);

        $this->assertNull($mission->traceDeLaSignatureClient());
    }

    public function test_la_signature_d_une_autre_mission_ne_fuit_pas(): void
    {
        $mienne = Mission::factory()->create();
        $autre = Mission::factory()->create();
        $this->signer($autre);

        $this->assertNull($mienne->traceDeLaSignatureClient());
    }

    public function test_le_bloc_du_pdf_affiche_le_trace(): void
    {
        $mission = Mission::factory()->create();
        $this->signer($mission);

        $rendu = view('pdf.mission-report', ['mission' => $mission->fresh()->load([
            'booking.client', 'leadEmployee', 'media', 'checklists.items', 'incidents', 'events.actor',
        ])])->render();

        $this->assertStringContainsString(self::TRACE, $rendu);
        $this->assertStringNotContainsString('client_signature_path', $rendu);
    }
}
