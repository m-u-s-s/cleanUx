<?php

namespace Tests\Feature\Trajet;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\ProviderProfile;
use App\Models\TripTrackingSession;
use App\Models\User;
use App\Services\Client\SharedTrackingService;
use App\Services\Missions\MissionTrackingService;
use App\Support\Domain\BookingStatus;
use App\Support\Domain\MissionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * LE CLIENT SUIT SA COURSE SUR TOUTES LES SURFACES, PAS SEULEMENT SUR UNE.
 *
 * Trois écrans montrent la même chose au client, et un seul avait été mis à jour : la carte
 * Livewire dédiée. Le suivi « fil complet » continuait de pointer le lieu de PRISE EN CHARGE
 * pendant que le passager s'en éloignait — l'ETA se rapprochait de zéro alors que la voiture
 * partait — et la page publique partagée annonçait « il arrive dans 12 min » sans rien montrer.
 */
class SuiviPartoutTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Mission, 2: Booking}
     */
    private function course(string $statut): array
    {
        $client = User::factory()->client()->create();
        $prestataire = User::factory()->employe()->create(['name' => 'Karim Benali']);
        ProviderProfile::create(['user_id' => $prestataire->id, 'status' => 'active']);

        $booking = Booking::create([
            'booking_reference' => 'CUX-'.strtoupper(Str::random(6)),
            'client_id' => $client->id,
            'customer_user_id' => $client->id,
            'employe_id' => $prestataire->id,
            'status' => BookingStatus::SUR_PLACE,
            'currency' => 'EUR',
            'priority' => 'normal',
            'booking_mode' => 'asap',
            'city' => 'Bruxelles',
            'destination_lat' => 50.8467,
            'destination_lng' => 4.3525,
            'dropoff_lat' => 50.9010,
            'dropoff_lng' => 4.4844,
        ]);

        $mission = Mission::create([
            'booking_id' => $booking->id,
            'lead_provider_user_id' => $prestataire->id,
            'status' => $statut,
            'destination_lat' => 50.8467,
            'destination_lng' => 4.3525,
        ]);

        MissionAssignment::factory()->accepted()->create([
            'mission_id' => $mission->id,
            'user_id' => $prestataire->id,
        ]);

        return [$client, $mission, $booking];
    }

    public function test_le_suivi_pointe_le_point_de_depose_une_fois_la_course_demarree(): void
    {
        [, $mission] = $this->course(MissionStatus::STARTED);

        $charge = app(MissionTrackingService::class)->livePayload($mission);

        $this->assertEqualsWithDelta(
            50.9010,
            (float) $charge['destination']['lat'],
            0.0001,
            'La carte continuait de pointer l’endroit qu’on venait de quitter, et l’ETA se rapprochait de zéro pendant que la voiture s’en éloignait.'
        );
    }

    /** LE TÉMOIN : avant le démarrage, c'est bien le point de prise en charge qu'on vise. */
    public function test_avant_le_demarrage_le_suivi_pointe_la_prise_en_charge(): void
    {
        [, $mission] = $this->course(MissionStatus::EN_ROUTE);

        $charge = app(MissionTrackingService::class)->livePayload($mission);

        $this->assertEqualsWithDelta(50.8467, (float) $charge['destination']['lat'], 0.0001);
    }

    /** LE TÉMOIN inverse : une intervention ordinaire garde son lieu, quel que soit son statut. */
    public function test_une_intervention_ordinaire_garde_son_lieu(): void
    {
        [, $mission, $booking] = $this->course(MissionStatus::STARTED);
        $booking->forceFill(['dropoff_lat' => null, 'dropoff_lng' => null])->save();

        $charge = app(MissionTrackingService::class)->livePayload($mission->fresh());

        $this->assertEqualsWithDelta(50.8467, (float) $charge['destination']['lat'], 0.0001);
    }

    public function test_la_page_publique_partagee_porte_la_destination_et_le_trace(): void
    {
        [, , $booking] = $this->course(MissionStatus::STARTED);

        TripTrackingSession::create([
            'code' => TripTrackingSession::generateCode(),
            'booking_id' => $booking->id,
            'provider_user_id' => $booking->employe_id,
            'status' => TripTrackingSession::STATUS_IN_MISSION,
            'destination_lat' => 50.9010,
            'destination_lng' => 4.4844,
            'last_lat' => 50.8600,
            'last_lng' => 4.3700,
            'started_at' => now(),
            'metadata' => [
                'leg' => 'ride',
                'route_points' => [['lat' => 50.86, 'lng' => 4.37], ['lat' => 50.88, 'lng' => 4.42], ['lat' => 50.90, 'lng' => 4.48]],
                'route_source' => 'mock',
            ],
        ]);

        $apercu = app(SharedTrackingService::class)->apercu($booking->fresh());

        $this->assertEqualsWithDelta(50.9010, (float) $apercu['tracking']['destination']['lat'], 0.0001);
        $this->assertCount(3, $apercu['tracking']['route']['points']);
    }

    /**
     * LA PAGE PUBLIQUE RESTE PAUVRE : des points, jamais une adresse ni un nom complet.
     *
     * Ce lien circule par SMS et peut être transféré. Y ajouter une carte ne doit pas y ajouter des
     * données personnelles au passage.
     */
    public function test_la_page_publique_ne_diffuse_ni_adresse_ni_nom_complet(): void
    {
        [, , $booking] = $this->course(MissionStatus::STARTED);

        $apercu = app(SharedTrackingService::class)->apercu($booking->fresh());

        $this->assertSame('Karim', $apercu['provider_first_name']);
        $this->assertArrayNotHasKey('address', $apercu);
        $this->assertSame('Bruxelles', $apercu['city']);
    }

    public function test_la_page_publique_s_affiche_avec_sa_carte(): void
    {
        [, , $booking] = $this->course(MissionStatus::STARTED);

        TripTrackingSession::create([
            'code' => TripTrackingSession::generateCode(),
            'booking_id' => $booking->id,
            'provider_user_id' => $booking->employe_id,
            'status' => TripTrackingSession::STATUS_IN_MISSION,
            'destination_lat' => 50.9010,
            'destination_lng' => 4.4844,
            'last_lat' => 50.8600,
            'last_lng' => 4.3700,
            'started_at' => now(),
        ]);

        $lien = app(SharedTrackingService::class)->lienPour($booking->fresh());

        $this->get($lien)
            ->assertOk()
            ->assertSee('suivi-carte')
            ->assertSee('leaflet', false);
    }
}
