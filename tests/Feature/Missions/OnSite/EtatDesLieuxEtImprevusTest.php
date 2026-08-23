<?php

namespace Tests\Feature\Missions\OnSite;

use App\Events\Missions\MissionIncidentReported;
use App\Events\Missions\MissionMediaAdded;
use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\MissionIncident;
use App\Models\MissionMedia;
use App\Models\User;
use App\Notifications\MissionIncidentNotification;
use App\Services\Missions\OnSite\MissionMediaService;
use App\Support\Domain\MissionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Le kit « sur place » : la preuve horodatée, l'imprévu qui se dit, et ce que le client en voit. */
class EtatDesLieuxEtImprevusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        Notification::fake();
    }

    public function test_une_photo_prise_sur_place_est_gardee_avec_son_empreinte(): void
    {
        [$provider, $mission] = $this->scenario();

        $this->actingAs($provider)
            ->postJson("/api/provider/missions/{$mission->id}/media", [
                'type' => MissionMedia::TYPE_BEFORE_PHOTO,
                'photo' => UploadedFile::fake()->create('avant.jpg', 120, 'image/jpeg'),
                'lat' => 50.8467,
                'lng' => 4.3525,
                'accuracy_m' => 8.5,
            ])
            ->assertCreated()
            ->assertJsonPath('data.type', MissionMedia::TYPE_BEFORE_PHOTO);

        $media = MissionMedia::query()->where('mission_id', $mission->id)->sole();

        $this->assertNotNull($media->sha256);
        $this->assertSame(64, strlen((string) $media->sha256));
        $this->assertSame(8.5, $media->accuracy_m);
        $this->assertNotNull($media->taken_at);
        $this->assertTrue(Storage::disk('private')->exists($media->path));
    }

    /** L'empreinte n'a d'intérêt que si elle DISTINGUE. */
    public function test_deux_cliches_differents_ont_deux_empreintes(): void
    {
        [$provider, $mission] = $this->scenario();

        // `createWithContent` et non `create` : ce dernier fabrique un fichier VIDE dont il se contente de déclarer la taille.
        foreach (['un.jpg' => 'salon avant', 'deux.jpg' => 'cuisine avant'] as $nom => $contenu) {
            $this->actingAs($provider)
                ->postJson("/api/provider/missions/{$mission->id}/media", [
                    'type' => MissionMedia::TYPE_AFTER_PHOTO,
                    'photo' => UploadedFile::fake()->createWithContent($nom, $contenu),
                ])
                ->assertCreated();
        }

        $empreintes = MissionMedia::query()
            ->where('mission_id', $mission->id)
            ->pluck('sha256')
            ->unique();

        $this->assertCount(2, $empreintes);
    }

    public function test_un_prestataire_non_affecte_ne_depose_rien(): void
    {
        [, $mission] = $this->scenario();
        $intrus = User::factory()->employe()->create(['is_active' => true, 'status' => 'active']);

        $this->actingAs($intrus)
            ->postJson("/api/provider/missions/{$mission->id}/media", [
                'type' => MissionMedia::TYPE_BEFORE_PHOTO,
                'photo' => UploadedFile::fake()->create('avant.jpg', 120, 'image/jpeg'),
            ])
            ->assertStatus(403);

        $this->assertSame(0, MissionMedia::query()->where('mission_id', $mission->id)->count());
    }

    public function test_un_document_nest_pas_une_photo(): void
    {
        [$provider, $mission] = $this->scenario();

        $this->actingAs($provider)
            ->postJson("/api/provider/missions/{$mission->id}/media", [
                'type' => MissionMedia::TYPE_BEFORE_PHOTO,
                'photo' => UploadedFile::fake()->create('facture.pdf', 20, 'application/pdf'),
            ])
            ->assertStatus(422);

        $this->assertCount(0, Storage::disk('private')->allFiles());
    }

    public function test_le_depot_dune_photo_est_diffuse_sur_le_canal_de_la_mission(): void
    {
        Event::fake([MissionMediaAdded::class]);
        [$provider, $mission] = $this->scenario();

        $this->actingAs($provider)
            ->postJson("/api/provider/missions/{$mission->id}/media", [
                'type' => MissionMedia::TYPE_BEFORE_PHOTO,
                'photo' => UploadedFile::fake()->create('avant.jpg', 120, 'image/jpeg'),
            ])
            ->assertCreated();

        Event::assertDispatched(
            MissionMediaAdded::class,
            fn (MissionMediaAdded $e) => $e->mission->id === $mission->id
                && $e->broadcastOn()[0]->name === 'private-mission.'.$mission->id
        );
    }

    public function test_un_imprevu_signale_previent_le_client_et_horodate_lenvoi(): void
    {
        [$provider, $mission, $client] = $this->scenario();

        $this->actingAs($provider)
            ->postJson("/api/provider/missions/{$mission->id}/incidents", [
                'type' => MissionIncident::TYPE_PREEXISTING_DAMAGE,
                'description' => 'Trace d’humidité derrière le meuble, présente à l’arrivée.',
                'photo' => UploadedFile::fake()->create('degat.jpg', 130, 'image/jpeg'),
            ])
            ->assertCreated()
            ->assertJsonPath('data.label', 'Dégât préexistant');

        $incident = MissionIncident::query()->where('mission_id', $mission->id)->sole();

        Notification::assertSentTo($client, MissionIncidentNotification::class);
        $this->assertNotNull($incident->notified_at);
        $this->assertNotNull($incident->mission_media_id);
        $this->assertSame(
            MissionMedia::TYPE_INCIDENT_PHOTO,
            MissionMedia::query()->find($incident->mission_media_id)?->media_type,
        );
    }

    /** La photo d'un imprévu N'ENTRE PAS dans le comparateur avant/après : elle y raconterait le contraire de ce qu'elle documente. */
    public function test_la_photo_dun_imprevu_reste_hors_du_comparateur(): void
    {
        [$provider, $mission, $client] = $this->scenario();

        $this->actingAs($provider)
            ->postJson("/api/provider/missions/{$mission->id}/incidents", [
                'type' => MissionIncident::TYPE_MISSING_ITEM,
                'description' => 'Le produit fourni par le client est vide.',
                'photo' => UploadedFile::fake()->create('bidon.jpg', 140, 'image/jpeg'),
            ])
            ->assertCreated();

        $this->actingAs($client)
            ->getJson("/api/client/bookings/{$mission->booking_id}/onsite/media")
            ->assertOk()
            ->assertJsonCount(0, 'before')
            ->assertJsonCount(0, 'after')
            ->assertJsonCount(1, 'incident');
    }

    public function test_un_imprevu_est_diffuse_sur_le_canal_de_la_mission(): void
    {
        Event::fake([MissionIncidentReported::class]);
        [$provider, $mission] = $this->scenario();

        $this->actingAs($provider)
            ->postJson("/api/provider/missions/{$mission->id}/incidents", [
                'type' => MissionIncident::TYPE_ACCESS_IMPOSSIBLE,
                'description' => 'Portail fermé, personne ne répond.',
            ])
            ->assertCreated();

        Event::assertDispatched(
            MissionIncidentReported::class,
            fn (MissionIncidentReported $e) => $e->broadcastOn()[0]->name === 'private-mission.'.$mission->id
        );
    }

    public function test_le_client_voit_le_fil_de_son_intervention(): void
    {
        [$provider, $mission, $client] = $this->scenario();

        $this->actingAs($provider)
            ->postJson("/api/provider/missions/{$mission->id}/media", [
                'type' => MissionMedia::TYPE_BEFORE_PHOTO,
                'photo' => UploadedFile::fake()->create('avant.jpg', 120, 'image/jpeg'),
            ])
            ->assertCreated();

        $reponse = $this->actingAs($client)
            ->getJson("/api/client/bookings/{$mission->booking_id}/onsite/timeline")
            ->assertOk()
            ->assertJsonPath('mission_id', $mission->id);

        $this->assertNotEmpty($reponse->json('entries'));
        $this->assertContains('media', array_column($reponse->json('entries'), 'kind'));
    }

    /** Le suivi ouvert AVANT l'heure ne doit pas répondre « introuvable » : la réservation existe, l'intervention n'a simplement pas commencé. */
    public function test_une_reservation_sans_mission_rend_un_fil_vide_et_non_une_erreur(): void
    {
        $client = User::factory()->create();
        $booking = Booking::factory()->create(['client_id' => $client->id]);
        Mission::query()->where('booking_id', $booking->id)->delete();

        $this->actingAs($client)
            ->getJson("/api/client/bookings/{$booking->id}/onsite/timeline")
            ->assertOk()
            ->assertJsonPath('mission_id', null)
            ->assertJsonPath('entries', []);
    }

    public function test_un_autre_client_ne_voit_rien(): void
    {
        [, $mission] = $this->scenario();
        $curieux = User::factory()->create();

        $this->actingAs($curieux)
            ->getJson("/api/client/bookings/{$mission->booking_id}/onsite/timeline")
            ->assertStatus(403);
    }

    /** Le prestataire documente aussi pour lui : `client_visible` à faux tient sa promesse. */
    public function test_un_cliche_reserve_a_lequipe_nest_pas_montre_au_client(): void
    {
        [$provider, $mission, $client] = $this->scenario();

        app(MissionMediaService::class)->capture(
            $mission,
            $provider,
            UploadedFile::fake()->create('interne.jpg', 150, 'image/jpeg'),
            MissionMedia::TYPE_AFTER_PHOTO,
            clientVisible: false,
        );

        $this->actingAs($client)
            ->getJson("/api/client/bookings/{$mission->booking_id}/onsite/media")
            ->assertOk()
            ->assertJsonCount(0, 'after');

        $this->actingAs($provider)
            ->getJson("/api/provider/missions/{$mission->id}/media")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    /**
     * @return array{0: User, 1: Mission, 2: User}
     */
    private function scenario(): array
    {
        $client = User::factory()->create();
        $provider = User::factory()->employe()->create(['is_active' => true, 'status' => 'active']);

        $booking = Booking::factory()->create(['client_id' => $client->id]);

        $mission = Mission::query()->where('booking_id', $booking->id)->first()
            ?? Mission::factory()->create(['booking_id' => $booking->id]);

        $mission->forceFill([
            'status' => MissionStatus::STARTED,
            'lead_employee_id' => $provider->id,
            'lead_provider_user_id' => $provider->id,
            'actual_start_at' => now()->subHour(),
            'estimated_duration_minutes' => 120,
        ])->save();

        MissionAssignment::query()->create([
            'mission_id' => $mission->id,
            'user_id' => $provider->id,
            'role_on_mission' => 'lead',
            'assignment_status' => 'accepted',
            'assigned_at' => now()->subHours(2),
            'accepted_at' => now()->subHours(2),
        ]);

        return [$provider, $mission->fresh(), $client];
    }
}
