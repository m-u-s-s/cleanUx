<?php

namespace Tests\Feature\Missions\OnSite;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\MissionMedia;
use App\Models\NpsResponse;
use App\Models\User;
use App\Notifications\MissionCheckInPingNotification;
use App\Services\Missions\OnSite\MissionCheckInService;
use App\Support\Domain\MissionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * LE MODE « JE NE SUIS PAS LÀ » (F14) ET LE PING DE MI-MISSION (F15).
 *
 * F14 — LA PREUVE DE PRÉSENCE SUPPOSAIT UN CLIENT PRÉSENT. Le code à six chiffres est affiché par le
 * client et saisi par le prestataire : il atteste que les deux personnes sont face à face. Parfait
 * quand c'est vrai, impossible quand le client travaille et laisse la clé chez la voisine — le cas
 * ordinaire du ménage à domicile. Ces interventions se déroulaient donc HORS du dispositif : soit le
 * prestataire ne pouvait pas démarrer, soit quelqu'un contournait.
 *
 * CE QUE CE FICHIER PROTÈGE AVANT TOUT : la déclaration vient du CLIENT, jamais du prestataire. Si
 * celui qui doit prouver sa présence pouvait décider que la preuve ne s'applique pas, il n'y aurait
 * plus de preuve du tout — et la photo d'arrivée deviendrait un bouton « je suis arrivé » que rien
 * ne contredit.
 *
 * F15 — LE PING VAUT PAR SON MOMENT. Posé au milieu, il laisse le temps de corriger ; posé à la fin,
 * il ne reste que l'avis à écrire et le litige à ouvrir — et les deux coûtent bien plus cher à tout
 * le monde qu'un mot dit au prestataire pendant qu'il est encore là.
 */
class ModeAbsentEtPingTest extends TestCase
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
            'status' => MissionStatus::ARRIVED,
            'lead_employee_id' => $prestataire->id,
            'lead_provider_user_id' => $prestataire->id,
        ])->save();

        MissionAssignment::query()->create([
            'mission_id' => $mission->id,
            'user_id' => $prestataire->id,
            'role_on_mission' => 'lead',
            'assignment_status' => 'accepted',
            'assigned_at' => now()->subHour(),
            'accepted_at' => now()->subHour(),
        ]);

        return [$prestataire, $mission->fresh(), $client, $booking->fresh()];
    }

    // ── F14 : le mode absent ─────────────────────────────────────────────────

    #[Test]
    public function le_client_declare_son_absence_et_la_preuve_bascule(): void
    {
        [, , $client, $booking] = $this->scenario();

        $this->actingAs($client, 'sanctum')
            ->postJson("/api/client/bookings/{$booking->id}/onsite/absence", [
                'absent' => true,
                'instructions' => 'Clé chez la voisine du 3e, Mme Lambert.',
                'backup_contact_name' => 'Mme Lambert',
                'backup_contact_phone' => '+32470999888',
            ])
            ->assertOk()
            ->assertJsonPath('data.client_absent', true)
            // Le client doit VOIR quelle preuve s'appliquera : c'est ce qui lui évite d'attendre un
            // appel pour un code qu'on ne lui demandera pas.
            ->assertJsonPath('data.presence_proof', 'photo');
    }

    #[Test]
    public function une_absence_sans_consigne_est_refusee(): void
    {
        [, , $client, $booking] = $this->scenario();

        // Sans instruction d'accès, le prestataire arrive devant une porte fermée et rentre chez
        // lui : le mode absent produirait exactement l'échec qu'il devait éviter.
        $this->actingAs($client, 'sanctum')
            ->postJson("/api/client/bookings/{$booking->id}/onsite/absence", ['absent' => true])
            ->assertStatus(422);

        $this->assertFalse((bool) $booking->fresh()->client_absent);
    }

    #[Test]
    public function le_prestataire_sait_avant_de_sonner_ce_qui_l_attend(): void
    {
        [$prestataire, $mission, , $booking] = $this->scenario();

        app(MissionCheckInService::class)->declarerAbsence(
            $booking,
            'Clé chez la voisine.',
            'Mme Lambert',
            '+32470999888',
        );

        // Attendre un code qui ne viendra pas fait perdre dix minutes devant une porte, puis
        // repartir. Et le contact de secours est la seule information qui débloque la situation.
        $this->actingAs($prestataire, 'sanctum')
            ->getJson("/api/provider/missions/{$mission->id}/presence-mode")
            ->assertOk()
            ->assertJsonPath('data.mode', 'photo')
            ->assertJsonPath('data.instructions', 'Clé chez la voisine.')
            ->assertJsonPath('data.backup_contact_name', 'Mme Lambert');
    }

    #[Test]
    public function la_photo_d_arrivee_est_horodatee_et_empreintee(): void
    {
        [$prestataire, $mission, , $booking] = $this->scenario();

        app(MissionCheckInService::class)->declarerAbsence($booking, 'Clé sous le paillasson.');

        $this->actingAs($prestataire, 'sanctum')
            ->postJson("/api/provider/missions/{$mission->id}/arrival-proof", [
                'photo' => UploadedFile::fake()->create('arrivee.jpg', 90, 'image/jpeg'),
                'lat' => 50.8467,
                'lng' => 4.3525,
            ])
            ->assertCreated();

        $media = MissionMedia::query()->where('mission_id', $mission->id)->latest('id')->firstOrFail();

        /*
         * C'EST L'EMPREINTE QUI FAIT LA DIFFÉRENCE entre un démarrage TRACÉ et un bouton « je suis
         * arrivé » que rien ne contredit. Sans elle, le mode absent serait un contournement
         * officialisé.
         */
        $this->assertNotNull($media->sha256);
        $this->assertNotNull($media->taken_at ?? $media->created_at);
    }

    #[Test]
    public function le_prestataire_ne_peut_pas_se_dispenser_du_code_tout_seul(): void
    {
        [$prestataire, $mission] = $this->scenario();

        /*
         * L'ASSERTION QUI PORTE F14. Le client n'a rien déclaré : la preuve reste le code. Si celui
         * qui doit prouver sa présence pouvait décider que la preuve ne s'applique pas, il n'y
         * aurait plus de preuve du tout.
         */
        $this->actingAs($prestataire, 'sanctum')
            ->postJson("/api/provider/missions/{$mission->id}/arrival-proof", [
                'photo' => UploadedFile::fake()->create('arrivee.jpg', 90, 'image/jpeg'),
            ])
            ->assertStatus(422);
    }

    // ── F15 : le ping ────────────────────────────────────────────────────────

    #[Test]
    public function le_ping_part_une_seule_fois(): void
    {
        [, $mission, $client] = $this->scenario();

        $service = app(MissionCheckInService::class);

        $this->assertTrue($service->envoyerLePing($mission));
        Notification::assertSentTo($client, MissionCheckInPingNotification::class);

        // Répéter la question transforme une attention en harcèlement, et personne ne répond à la
        // troisième.
        $this->assertFalse($service->envoyerLePing($mission->fresh()));
    }

    #[Test]
    public function la_reponse_alimente_le_nps(): void
    {
        [, , $client, $booking] = $this->scenario();

        $this->actingAs($client, 'sanctum')
            ->postJson("/api/client/bookings/{$booking->id}/onsite/checkin", ['answer' => 'ok'])
            ->assertOk()
            ->assertJsonPath('data.answer', 'ok');

        $this->assertDatabaseHas('nps_responses', [
            'booking_id' => $booking->id,
            'survey_code' => 'mission_checkin',
            'category' => NpsResponse::CATEGORY_PROMOTER,
        ]);
    }

    #[Test]
    public function un_probleme_signale_est_enregistre_comme_tel(): void
    {
        [, , $client, $booking] = $this->scenario();

        $this->actingAs($client, 'sanctum')
            ->postJson("/api/client/bookings/{$booking->id}/onsite/checkin", ['answer' => 'probleme'])
            ->assertOk();

        // Deux réponses ne font pas une note sur dix : on enregistre les extrêmes, et la nuance
        // viendra de l'avis. Prétendre à une précision qu'on n'a pas fausserait la moyenne.
        $this->assertDatabaseHas('nps_responses', [
            'booking_id' => $booking->id,
            'category' => NpsResponse::CATEGORY_DETRACTOR,
        ]);
    }

    #[Test]
    public function on_ne_repond_qu_une_fois(): void
    {
        [, , $client, $booking] = $this->scenario();

        $this->actingAs($client, 'sanctum')
            ->postJson("/api/client/bookings/{$booking->id}/onsite/checkin", ['answer' => 'ok'])
            ->assertOk();

        $this->actingAs($client, 'sanctum')
            ->postJson("/api/client/bookings/{$booking->id}/onsite/checkin", ['answer' => 'probleme'])
            ->assertStatus(422);

        $this->assertSame('ok', $booking->fresh()->checkin_ping_answer);
    }

    #[Test]
    public function la_reservation_d_autrui_reste_hors_de_portee(): void
    {
        [, , , $booking] = $this->scenario();
        $curieux = User::factory()->create();

        $this->actingAs($curieux, 'sanctum')
            ->postJson("/api/client/bookings/{$booking->id}/onsite/absence", [
                'absent' => true,
                'instructions' => 'Entrez par la fenêtre.',
            ])
            ->assertForbidden();
    }
}
