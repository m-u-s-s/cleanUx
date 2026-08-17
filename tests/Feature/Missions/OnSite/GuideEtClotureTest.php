<?php

namespace Tests\Feature\Missions\OnSite;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\MissionChecklist;
use App\Models\MissionChecklistItem;
use App\Models\User;
use App\Services\Missions\OnSite\MissionClosureService;
use App\Support\Domain\MissionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * LE GUIDE PAS-À-PAS (F6) ET LA CLÔTURE GUIDÉE (F16).
 *
 * F6 — UNE LISTE N'EST PAS UN GUIDE. Les checklists existaient : toutes les cases visibles,
 * cochables dans n'importe quel ordre. Parfait pour un professionnel expérimenté qui vérifie qu'il
 * n'a rien oublié, inutilisable pour celui qui débute ou découvre un métier. Sur une remise en état
 * après travaux, aspirer avant de dépoussiérer les hauteurs fait le travail deux fois : l'ordre
 * n'est pas une préférence d'affichage, c'est le métier.
 *
 * CE QUE CE FICHIER PROTÈGE : qu'on ne puisse valider QUE l'étape en cours. Sans ce refus, le mode
 * guidé ne serait qu'un affichage — n'importe quel identifiant passerait, et la séquence deviendrait
 * une suggestion.
 *
 * F16 — TROIS BRIQUES QUI EXISTAIENT CHACUNE DE SON CÔTÉ. Rapport, pourboire, avis étaient
 * atteignables depuis trois endroits différents, si bien que la plupart des clients n'en voyaient
 * aucune. Le flux les enchaîne dans l'ordre où les choses se décident — on ne remercie pas avant de
 * savoir ce qui a été fait, et on ne note pas avant d'avoir décidé si on remercie.
 */
class GuideEtClotureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        Notification::fake();
    }

    /** @return array{0: User, 1: Mission, 2: User, 3: Booking} */
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
            'actual_start_at' => now()->subHour(),
        ])->save();

        MissionAssignment::query()->create([
            'mission_id' => $mission->id,
            'user_id' => $prestataire->id,
            'role_on_mission' => 'lead',
            'assignment_status' => 'accepted',
            'assigned_at' => now()->subHours(2),
            'accepted_at' => now()->subHours(2),
        ]);

        return [$prestataire, $mission->fresh(), $client, $booking->fresh()];
    }

    /** @return array<int, MissionChecklistItem> */
    private function checklistOrdonnee(Mission $mission): array
    {
        // Les checklists créées par la synchronisation ne sont pas ordonnées : on part d'une base
        // propre pour mesurer le mode guidé.
        MissionChecklistItem::query()
            ->whereIn('mission_checklist_id', $mission->checklists()->select('id'))
            ->delete();

        $checklist = $mission->checklists()->first()
            ?? MissionChecklist::query()->create([
                'mission_id' => $mission->id,
                'title' => 'Guide',
                'status' => 'pending',
            ]);

        return [
            MissionChecklistItem::query()->create([
                'mission_checklist_id' => $checklist->id,
                'label' => 'Dépoussiérer les hauteurs',
                'guidance' => 'Plafonds et luminaires d’abord : la poussière retombe.',
                'sort_order' => 1,
                'is_required' => true,
                'requires_photo' => false,
                'status' => 'pending',
            ]),
            MissionChecklistItem::query()->create([
                'mission_checklist_id' => $checklist->id,
                'label' => 'Aspirer les sols',
                'sort_order' => 2,
                'is_required' => true,
                'requires_photo' => true,
                'status' => 'pending',
            ]),
        ];
    }

    // ── F6 : le guide ────────────────────────────────────────────────────────

    #[Test]
    public function le_guide_ne_montre_qu_une_etape_a_la_fois(): void
    {
        [$prestataire, $mission] = $this->scenario();
        $this->checklistOrdonnee($mission);

        $reponse = $this->actingAs($prestataire, 'sanctum')
            ->getJson("/api/provider/missions/{$mission->id}/guided-step")
            ->assertOk()
            ->assertJsonPath('data.guided', true);

        // Une personne les mains prises doit savoir quoi faire MAINTENANT, sans lire la suite.
        $this->assertSame('Dépoussiérer les hauteurs', $reponse->json('data.step.label'));
        $this->assertSame(1, $reponse->json('data.step.position'));
        $this->assertSame(2, $reponse->json('data.step.total'));
        $this->assertNotNull($reponse->json('data.step.guidance'));
    }

    #[Test]
    public function on_ne_saute_pas_une_etape(): void
    {
        [$prestataire, $mission] = $this->scenario();
        [, $seconde] = $this->checklistOrdonnee($mission);

        /*
         * L'ASSERTION QUI PORTE F6. Sans ce refus, le mode guidé ne serait qu'un affichage :
         * n'importe quel identifiant passerait, et l'ordre du métier — aspirer APRÈS avoir
         * dépoussiéré les hauteurs — ne serait plus qu'une suggestion.
         */
        $this->actingAs($prestataire, 'sanctum')
            ->postJson("/api/provider/missions/{$mission->id}/guided-step", ['item_id' => $seconde->id])
            ->assertStatus(422);

        $this->assertSame('pending', $seconde->fresh()->status);
    }

    #[Test]
    public function une_etape_qui_demande_une_photo_la_demande_vraiment(): void
    {
        [$prestataire, $mission] = $this->scenario();
        [$premiere, $seconde] = $this->checklistOrdonnee($mission);

        $this->actingAs($prestataire, 'sanctum')
            ->postJson("/api/provider/missions/{$mission->id}/guided-step", ['item_id' => $premiere->id])
            ->assertOk();

        // La preuve est exigée AVANT de cocher : une étape validée sans sa photo ne se rattrape
        // plus, le lieu a changé.
        $this->actingAs($prestataire, 'sanctum')
            ->postJson("/api/provider/missions/{$mission->id}/guided-step", ['item_id' => $seconde->id])
            ->assertStatus(422);

        $this->actingAs($prestataire, 'sanctum')
            ->postJson("/api/provider/missions/{$mission->id}/guided-step", [
                'item_id' => $seconde->id,
                'photo' => UploadedFile::fake()->create('sols.jpg', 90, 'image/jpeg'),
            ])
            ->assertOk();

        $releve = $seconde->fresh();

        $this->assertSame('done', $releve->status);
        // La photo est RATTACHÉE à l'étape : sans ce lien, le rapport ne saurait pas laquelle des
        // vingt photos atteste laquelle des vingt étapes.
        $this->assertNotNull($releve->mission_media_id);
    }

    #[Test]
    public function une_checklist_sans_ordre_reste_une_liste(): void
    {
        [$prestataire, $mission] = $this->scenario();

        $checklist = $mission->checklists()->first()
            ?? MissionChecklist::query()->create([
                'mission_id' => $mission->id,
                'title' => 'Liste',
                'status' => 'pending',
            ]);

        MissionChecklistItem::query()->create([
            'mission_checklist_id' => $checklist->id,
            'label' => 'Sans ordre',
            'is_required' => true,
            'status' => 'pending',
        ]);

        // Basculer tout le monde en mode guidé imposerait une séquence que personne n'a écrite.
        $this->actingAs($prestataire, 'sanctum')
            ->getJson("/api/provider/missions/{$mission->id}/guided-step")
            ->assertOk()
            ->assertJsonPath('data.guided', false);
    }

    // ── F16 : la clôture guidée ──────────────────────────────────────────────

    #[Test]
    public function le_flux_de_cloture_dit_ce_qui_reste_a_faire(): void
    {
        [$prestataire, $mission, $client, $booking] = $this->scenario();

        app(MissionClosureService::class)->cloturer($mission, $prestataire);

        $reponse = $this->actingAs($client, 'sanctum')
            ->getJson("/api/client/bookings/{$booking->id}/onsite/closure")
            ->assertOk()
            ->assertJsonPath('data.available', true);

        // Le rapport d'abord : c'est ce qui permet de juger le reste.
        $this->assertNotNull($reponse->json('data.report.number'));
        $this->assertTrue($reponse->json('data.tip_pending'));
        $this->assertTrue($reponse->json('data.review_pending'));
        $this->assertFalse($reponse->json('data.completed'));
    }

    #[Test]
    public function une_reservation_sans_mission_ne_propose_pas_de_cloture(): void
    {
        $client = User::factory()->create();
        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'customer_user_id' => $client->id,
        ]);

        Mission::query()->where('booking_id', $booking->id)->delete();

        // Proposer de noter une intervention qui n'a pas eu lieu ferait douter de ce qu'on a
        // commandé.
        $this->actingAs($client, 'sanctum')
            ->getJson("/api/client/bookings/{$booking->id}/onsite/closure")
            ->assertOk()
            ->assertJsonPath('data.available', false);
    }

    #[Test]
    public function la_cloture_d_autrui_reste_hors_de_portee(): void
    {
        [, , , $booking] = $this->scenario();

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson("/api/client/bookings/{$booking->id}/onsite/closure")
            ->assertForbidden();
    }
}
