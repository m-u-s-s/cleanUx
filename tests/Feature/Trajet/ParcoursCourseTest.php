<?php

namespace Tests\Feature\Trajet;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\MissionVerificationCode;
use App\Models\ProviderPresence;
use App\Models\ProviderProfile;
use App\Models\TripTrackingSession;
use App\Models\User;
use App\Services\Missions\MissionLifecycleService;
use App\Services\Missions\RideLifecycleService;
use App\Support\Domain\BookingStatus;
use App\Support\Domain\MissionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

/**
 * LE SECOND PARCOURS DE MISSION : la course, sans un seul code.
 *
 * Ce fichier tient DEUX promesses à la fois, et la seconde est la plus importante :
 *
 *  1. Une course se déroule sans code, se termine au point de dépose, et le prestataire redevient
 *     disponible ;
 *  2. Une intervention ORDINAIRE, elle, ne change en RIEN — ses deux codes restent exigés, sa
 *     geofence reste calée sur le lieu d'intervention.
 *
 * Chaque interdiction est doublée de son témoin. Sans cela, un test « aucun code n'est émis »
 * passerait au vert le jour où plus aucun code ne serait jamais émis pour personne.
 */
class ParcoursCourseTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: User, 2: Mission, 3: Booking}
     */
    private function mission(string $statut, bool $course): array
    {
        $client = User::factory()->client()->create(['phone' => '+32470000000']);
        $prestataire = User::factory()->employe()->create();
        ProviderProfile::create(['user_id' => $prestataire->id, 'status' => 'active']);

        $booking = Booking::create([
            'booking_reference' => 'CUX-'.strtoupper(Str::random(6)),
            'customer_user_id' => $client->id,
            'client_id' => $client->id,
            'telephone_client' => '+32470111222',
            'scheduled_date' => now()->addDay()->toDateString(),
            'scheduled_time' => '10:00:00',
            'status' => BookingStatus::CONFIRME,
            'currency' => 'EUR',
            'priority' => 'normal',
            'booking_mode' => 'scheduled',
            'devis_estime' => 120,
            'address' => 'Rue de la Loi 1, 1000 Bruxelles',
            'destination_lat' => 50.8467,
            'destination_lng' => 4.3525,
        ] + ($course ? [
            'dropoff_address' => 'Aéroport de Bruxelles',
            'dropoff_lat' => 50.9010,
            'dropoff_lng' => 4.4844,
            'route_distance_m' => 12_400,
        ] : []));

        $mission = Mission::create([
            'booking_id' => $booking->id,
            'lead_provider_user_id' => $prestataire->id,
            'status' => $statut,
            'planned_start_at' => now()->subHour(),
            'destination_lat' => 50.8467,
            'destination_lng' => 4.3525,
        ]);

        MissionAssignment::factory()->accepted()->create([
            'mission_id' => $mission->id,
            'user_id' => $prestataire->id,
        ]);

        return [$client, $prestataire, $mission, $booking];
    }

    private function codes(Mission $mission): int
    {
        return MissionVerificationCode::where('mission_id', $mission->id)->count();
    }

    public function test_arriver_sur_une_course_n_emet_aucun_code(): void
    {
        Notification::fake();
        [, $prestataire, $mission] = $this->mission(MissionStatus::EN_ROUTE, course: true);

        $resultat = app(MissionLifecycleService::class)->setArrived($mission, $prestataire, 50.8467, 4.3525);

        $this->assertSame(MissionStatus::ARRIVED, $resultat->fresh()->status);
        $this->assertSame(
            0,
            $this->codes($mission),
            'Un code émis pour une course part aussi par SMS — et le module plafonne à cinq messages par heure et par numéro.'
        );
    }

    /** LE TÉMOIN : sur une intervention ordinaire, les deux codes sont toujours émis. */
    public function test_arriver_sur_une_intervention_emet_toujours_ses_deux_codes(): void
    {
        Notification::fake();
        [, $prestataire, $mission] = $this->mission(MissionStatus::EN_ROUTE, course: false);

        app(MissionLifecycleService::class)->setArrived($mission, $prestataire, 50.8467, 4.3525);

        $this->assertSame(2, $this->codes($mission));
    }

    public function test_la_course_demarre_quand_le_client_monte(): void
    {
        Notification::fake();
        [, $prestataire, $mission] = $this->mission(MissionStatus::ARRIVED, course: true);

        $resultat = app(RideLifecycleService::class)->demarrerLaCourse($mission, $prestataire, 50.8467, 4.3525);

        $this->assertSame(MissionStatus::STARTED, $resultat->fresh()->status);
        $this->assertTrue((bool) $resultat->fresh()->client_presence_confirmed);
        $this->assertSame(0, $this->codes($mission));
    }

    public function test_le_suivi_bascule_sur_le_point_d_arrivee(): void
    {
        Notification::fake();
        [, $prestataire, $mission, $booking] = $this->mission(MissionStatus::ARRIVED, course: true);

        app(RideLifecycleService::class)->demarrerLaCourse($mission, $prestataire, 50.8467, 4.3525);

        $session = TripTrackingSession::where('booking_id', $booking->id)->active()->latest('id')->first();

        $this->assertNotNull($session, 'Sans seconde session, le client perd de vue la voiture dans laquelle il est assis.');
        $this->assertEqualsWithDelta(50.9010, (float) $session->destination_lat, 0.0001);
        $this->assertSame('ride', $session->metadata['leg'] ?? null);
    }

    public function test_la_course_se_termine_au_point_de_depose_sans_code(): void
    {
        Notification::fake();
        [, $prestataire, $mission, $booking] = $this->mission(MissionStatus::STARTED, course: true);

        // Position au POINT B : c'est là que la course finit. Comparée au point A, elle serait à
        // plus de dix kilomètres et la clôture serait refusée.
        $resultat = app(RideLifecycleService::class)->terminerLaCourse($mission, $prestataire, 50.9010, 4.4844);

        $this->assertSame(MissionStatus::COMPLETED, $resultat->fresh()->status);
        $this->assertSame(BookingStatus::TERMINE, $booking->fresh()->status);
        $this->assertSame(0, $this->codes($mission));
    }

    /**
     * LE PIÈGE QUE CE TEST FERME.
     *
     * `OnSiteVerifier` compare la position au lieu de l'INTERVENTION. Sur une course, ce lieu est le
     * point de prise en charge : sans le paramètre explicite de lieu attendu, toute fin de course
     * serait refusée avec « vous semblez être à 12 km du lieu de l'intervention » — techniquement
     * vrai, et complètement à côté.
     */
    public function test_terminer_loin_du_point_de_depose_est_refuse(): void
    {
        Notification::fake();
        [, $prestataire, $mission] = $this->mission(MissionStatus::STARTED, course: true);

        $this->expectException(ValidationException::class);

        // Position au point A, à plus de dix kilomètres de la dépose.
        app(RideLifecycleService::class)->terminerLaCourse($mission, $prestataire, 50.8467, 4.3525);
    }

    /** LE TÉMOIN de l'inverse : une intervention ordinaire se clôture bien AU POINT A. */
    public function test_une_intervention_ordinaire_se_cloture_sur_son_lieu(): void
    {
        Notification::fake();
        [, $prestataire, $mission] = $this->mission(MissionStatus::STARTED, course: false);

        $resultat = app(MissionLifecycleService::class)->completeMission($mission, $prestataire, 50.8467, 4.3525);

        $this->assertSame(MissionStatus::COMPLETED, $resultat->fresh()->status);
    }

    /**
     * LE PRESTATAIRE REDEVIENT DISPONIBLE À LA FIN DE LA COURSE.
     *
     * Rien n'a été écrit pour ça : `PresenceAutoTransitioner` fait déjà repasser `busy → online`
     * quand la réservation devient `termine`. Ce test PROUVE que la chaîne tient sur le nouveau
     * parcours — un prestataire resté occupé après sa course cesserait en silence de recevoir la
     * moindre offre, et c'est un défaut que ce dépôt a déjà connu.
     */
    public function test_le_prestataire_repasse_en_ligne_a_la_fin_de_la_course(): void
    {
        Notification::fake();
        [, $prestataire, $mission] = $this->mission(MissionStatus::STARTED, course: true);

        ProviderPresence::create([
            'provider_user_id' => $prestataire->id,
            'status' => ProviderPresence::STATUS_BUSY,
            'heartbeat_at' => now(),
        ]);

        app(RideLifecycleService::class)->terminerLaCourse($mission, $prestataire, 50.9010, 4.4844);

        $this->assertSame(
            ProviderPresence::STATUS_ONLINE,
            ProviderPresence::where('provider_user_id', $prestataire->id)->value('status'),
        );
    }

    public function test_les_deux_parcours_ne_se_croisent_pas(): void
    {
        Notification::fake();
        [, $prestataire, $ordinaire] = $this->mission(MissionStatus::ARRIVED, course: false);

        $this->expectException(RuntimeException::class);
        app(RideLifecycleService::class)->demarrerLaCourse($ordinaire, $prestataire);
    }

    /** LE TÉMOIN du test précédent : la même méthode ABOUTIT sur une vraie course. */
    public function test_le_parcours_course_accepte_une_course(): void
    {
        Notification::fake();
        [, $prestataire, $course] = $this->mission(MissionStatus::ARRIVED, course: true);

        $resultat = app(RideLifecycleService::class)->demarrerLaCourse($course, $prestataire);

        $this->assertSame(MissionStatus::STARTED, $resultat->fresh()->status);
    }

    public function test_l_api_refuse_le_code_de_debut_sur_une_course(): void
    {
        Notification::fake();
        [, $prestataire, $mission] = $this->mission(MissionStatus::ARRIVED, course: true);

        $this->actingAs($prestataire)
            ->postJson("/api/provider/missions/{$mission->id}/begin", ['start_code' => '123456'])
            ->assertStatus(409)
            ->assertJsonPath('ok', false);
    }

    /** LE TÉMOIN : la route des courses refuse, elle aussi, ce qui n'est pas pour elle. */
    public function test_l_api_refuse_le_parcours_course_sur_une_intervention(): void
    {
        Notification::fake();
        [, $prestataire, $mission] = $this->mission(MissionStatus::ARRIVED, course: false);

        $this->actingAs($prestataire)
            ->postJson("/api/provider/missions/{$mission->id}/ride/start")
            ->assertStatus(409)
            ->assertJsonPath('ok', false);
    }

    public function test_l_api_deroule_la_course_de_bout_en_bout(): void
    {
        Notification::fake();
        [, $prestataire, $mission] = $this->mission(MissionStatus::ARRIVED, course: true);

        $this->actingAs($prestataire)
            ->postJson("/api/provider/missions/{$mission->id}/ride/start", ['lat' => 50.8467, 'lng' => 4.3525])
            ->assertOk()
            ->assertJsonPath('status', MissionStatus::STARTED);

        $this->actingAs($prestataire)
            ->postJson("/api/provider/missions/{$mission->id}/ride/complete", ['lat' => 50.9010, 'lng' => 4.4844])
            ->assertOk()
            ->assertJsonPath('status', MissionStatus::COMPLETED);
    }
}
