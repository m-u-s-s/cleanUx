<?php

namespace Tests\Feature\Missions\OnSite;

use App\Models\Booking;
use App\Models\ContractSignature;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\MissionReport;
use App\Models\User;
use App\Notifications\MissionReportReadyNotification;
use App\Services\Missions\MissionReportService;
use App\Services\Missions\OnSite\MissionClosureService;
use App\Support\Domain\MissionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * LE RAPPORT DE FIN (F9) ET LA SIGNATURE DU CLIENT (F10).
 *
 * DEUX GÉNÉRATEURS EXISTAIENT SANS SE CONNAÎTRE : l'un fabriquait un PDF sur le disque privé,
 * l'autre écrivait une fiche de synthèse en base. `mission_reports.pdf_path` restait vide — la fiche
 * ne savait pas où trouver le document qu'elle résume.
 *
 * ET SURTOUT, RIEN NE L'ENVOYAIT AU CLIENT. Le rapport était produit puis rangé sur un disque que le
 * destinataire ne peut pas atteindre. Un compte rendu qu'on ne reçoit pas est un fichier, pas un
 * compte rendu — et c'est exactement la pièce qu'on cherche trois semaines plus tard, quand une
 * contestation arrive et que plus personne ne se souvient de l'état du lieu.
 *
 * LA GÉNÉRATION EST EN SOFT-FAIL, ET CE FICHIER LE VÉRIFIE. Une bibliothèque PDF qui échoue ne doit
 * pas empêcher un prestataire de terminer sa journée : c'est la clôture qui compte sur le terrain,
 * pas le rendu d'un document.
 */
class RapportDeFinEtSignatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        Notification::fake();
    }

    /** @return array{0: User, 1: Mission, 2: User} */
    private function scenario(): array
    {
        $client = User::factory()->create();
        $prestataire = User::factory()->employe()->create(['is_active' => true, 'status' => 'active']);

        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'customer_user_id' => $client->id,
            'employe_id' => $prestataire->id,
        ]);

        $mission = Mission::query()->where('booking_id', $booking->id)->first()
            ?? Mission::factory()->create(['booking_id' => $booking->id]);

        $mission->forceFill([
            'status' => MissionStatus::STARTED,
            'lead_employee_id' => $prestataire->id,
            'lead_provider_user_id' => $prestataire->id,
            'actual_start_at' => now()->subHours(2),
        ])->save();

        MissionAssignment::query()->create([
            'mission_id' => $mission->id,
            'user_id' => $prestataire->id,
            'role' => 'lead',
            'role_on_mission' => 'lead',
            'status' => 'accepted',
            'assignment_status' => 'accepted',
            'assigned_at' => now()->subHours(3),
            'accepted_at' => now()->subHours(3),
        ]);

        return [$prestataire, $mission->fresh(), $client];
    }

    // ── F9 : le rapport ──────────────────────────────────────────────────────

    #[Test]
    public function la_cloture_produit_une_fiche_et_son_pdf(): void
    {
        [$prestataire, $mission] = $this->scenario();

        $rapport = app(MissionClosureService::class)->cloturer($mission, $prestataire);

        $this->assertNotNull($rapport->report_number);

        /*
         * `pdf_path` RENSEIGNÉ : la fiche de synthèse et le PDF s'ignoraient, si bien que retrouver
         * le document supposait de reconstruire son chemin de mémoire.
         */
        $this->assertNotNull($rapport->pdf_path);
        Storage::disk('private')->assertExists($rapport->pdf_path);
    }

    #[Test]
    public function le_client_recoit_son_rapport(): void
    {
        [$prestataire, $mission, $client] = $this->scenario();

        app(MissionClosureService::class)->cloturer($mission, $prestataire);

        // C'EST CE QUI MANQUAIT. Le rapport existait, rangé là où le client ne peut pas aller.
        Notification::assertSentTo($client, MissionReportReadyNotification::class);

        $this->assertNotNull(
            MissionReport::query()->where('mission_id', $mission->id)->value('sent_at'),
        );
    }

    #[Test]
    public function un_pdf_qui_echoue_ne_bloque_pas_la_cloture(): void
    {
        [$prestataire, $mission] = $this->scenario();

        // Une bibliothèque PDF qui tombe ne doit pas empêcher un prestataire de terminer sa
        // journée : la fiche existe, le PDF se rattrape.
        $this->mock(MissionReportService::class, function ($mock) {
            $mock->shouldReceive('generate')->andThrow(new \RuntimeException('dompdf indisponible'));
        });

        $rapport = app(MissionClosureService::class)->cloturer($mission, $prestataire);

        $this->assertNotNull($rapport->report_number);
        $this->assertNull($rapport->pdf_path);
        // Le motif est consigné : sans lui, un rapport sans PDF se lit comme un rapport jamais
        // généré, et on relance une génération qui échouera pareillement.
        $this->assertSame('dompdf indisponible', data_get($rapport->metadata, 'pdf_error'));
    }

    // ── F10 : la signature ───────────────────────────────────────────────────

    #[Test]
    public function le_client_signe_sur_l_ecran_du_prestataire(): void
    {
        [$prestataire, $mission] = $this->scenario();

        app(MissionClosureService::class)->cloturer($mission, $prestataire);

        $this->actingAs($prestataire, 'sanctum')
            ->postJson("/api/provider/missions/{$mission->id}/client-signature", [
                'signature' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUg==',
                'signer_name' => 'Marie Dupont',
            ])
            ->assertCreated()
            ->assertJsonPath('data.signed', true);

        /*
         * LA SIGNATURE VAUT PAR CE QUI L'ACCOMPAGNE : horodatage, empreinte du contenu signé,
         * attribution à un compte. Une case cochée ne prouverait rien le jour où le client affirme
         * n'avoir jamais validé l'intervention.
         */
        $signature = ContractSignature::query()->latest('id')->first();

        $this->assertNotNull($signature);
        $this->assertSame('Marie Dupont', $signature->signer_name);
        $this->assertNotNull($signature->signature_hash);
        $this->assertNotNull($signature->signed_at);
    }

    #[Test]
    public function la_fiche_garde_la_trace_de_la_validation(): void
    {
        [$prestataire, $mission] = $this->scenario();

        app(MissionClosureService::class)->cloturer($mission, $prestataire);

        $this->actingAs($prestataire, 'sanctum')
            ->postJson("/api/provider/missions/{$mission->id}/client-signature", [
                'signature' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUg==',
                'signer_name' => 'Marie Dupont',
            ])
            ->assertCreated();

        // C'est la fiche qu'on relit en cas de contestation, pas la table des signatures.
        $this->assertSame(
            'signed',
            MissionReport::query()->where('mission_id', $mission->id)->value('client_validation'),
        );
    }

    #[Test]
    public function on_ne_signe_pas_un_rapport_qui_n_existe_pas(): void
    {
        [$prestataire, $mission] = $this->scenario();

        // Signer avant la clôture attesterait d'un contenu qui n'a pas encore été établi :
        // l'empreinte porterait sur rien.
        $this->actingAs($prestataire, 'sanctum')
            ->postJson("/api/provider/missions/{$mission->id}/client-signature", [
                'signature' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUg==',
                'signer_name' => 'Marie Dupont',
            ])
            ->assertStatus(422);
    }

    #[Test]
    public function un_prestataire_etranger_ne_recueille_aucune_signature(): void
    {
        [$prestataire, $mission] = $this->scenario();
        app(MissionClosureService::class)->cloturer($mission, $prestataire);

        $intrus = User::factory()->employe()->create(['is_active' => true, 'status' => 'active']);

        $this->actingAs($intrus, 'sanctum')
            ->postJson("/api/provider/missions/{$mission->id}/client-signature", [
                'signature' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUg==',
                'signer_name' => 'Marie Dupont',
            ])
            ->assertForbidden();
    }
}
