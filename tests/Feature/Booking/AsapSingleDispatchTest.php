<?php

namespace Tests\Feature\Booking;

use App\Actions\Booking\CreateBookingFromApiAction;
use App\Models\Mission;
use App\Models\ServiceCatalog;
use App\Models\User;
use App\Services\Booking\CreateBookingAction;
use App\Services\Dispatch\MissionDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\Support\CreatesZoneAwareFixtures;
use Tests\TestCase;

/**
 * Une réservation ASAP ne doit produire QU'UNE mission, et cette mission doit partir en offre.
 *
 * Deux colonnes de `missions` portent un bookings.id — `booking_id` et `rendez_vous_id` — et deux
 * chemins écrivaient chacun la sienne sans voir l'autre :
 *
 *  - RendezVousObserver, sur toute réservation enregistrée en `confirme`, crée une mission via
 *    rendez_vous_id (MissionFromRendezVousSyncService) ;
 *  - CreateBookingFromApiAction::maybeDispatchAsap créait la SIENNE via booking_id, puis
 *    dispatchait celle-là.
 *
 * L'ASAP passant par `confirme`, les deux se déclenchaient : deux missions pour une réservation,
 * dont une seule dispatchée. Risque de double assignation et comptage faussé.
 */
class AsapSingleDispatchTest extends TestCase
{
    use CreatesZoneAwareFixtures;
    use RefreshDatabase;

    public function test_an_asap_api_booking_creates_exactly_one_mission(): void
    {
        $this->mockDispatch();

        $booking = $this->createAsapBooking();

        $this->assertSame(
            1,
            Mission::where('booking_id', $booking->id)->orWhere('rendez_vous_id', $booking->id)->count(),
            'Une réservation ASAP ne doit produire qu’une seule mission.'
        );
    }

    public function test_the_single_mission_is_the_one_dispatched(): void
    {
        $dispatched = [];
        $this->mockDispatch($dispatched);

        $booking = $this->createAsapBooking();

        $mission = Mission::where('booking_id', $booking->id)
            ->orWhere('rendez_vous_id', $booking->id)
            ->firstOrFail();

        $this->assertCount(1, $dispatched, 'Le dispatch doit partir exactement une fois.');
        $this->assertSame($mission->id, $dispatched[0], 'La mission dispatchée doit être celle de la réservation.');
    }

    /**
     * Une réservation planifiée ne passe pas par l'offre ASAP : elle ne doit ni créer de seconde
     * mission, ni déclencher de dispatch immédiat.
     */
    public function test_a_scheduled_booking_creates_one_mission_and_is_not_dispatched(): void
    {
        $dispatched = [];
        $this->mockDispatch($dispatched);

        $booking = $this->createBooking('scheduled');

        $this->assertLessThanOrEqual(
            1,
            Mission::where('booking_id', $booking->id)->orWhere('rendez_vous_id', $booking->id)->count()
        );
        $this->assertCount(0, $dispatched);
    }

    /**
     * LE CHEMIN WEB ENTRE PAR LA PORTE UNIQUE : `DispatchEngine::dispatchBooking()`.
     *
     * Il testait auparavant `isset($mission)` sur une variable jamais assignée : la garde valait
     * toujours faux et l'offre ASAP ne partait jamais. Il portait ensuite DEUX chemins — l'offre
     * immédiate d'un côté, la confirmation directe du planifié de l'autre, chacun avec sa propre
     * liste de candidats. Le moteur tient les deux modes ; c'est lui qu'on observe ici.
     */
    public function test_an_asap_web_booking_is_dispatched(): void
    {
        $dispatched = [];
        $this->mockDispatch($dispatched);

        $recues = [];
        $this->mock(\App\Services\Dispatch\DispatchEngine::class, function (MockInterface $mock) use (&$recues) {
            $mock->shouldReceive('dispatchBooking')
                ->andReturnUsing(function ($booking) use (&$recues) {
                    $recues[] = $booking->id;

                    return null;
                });
            $mock->shouldIgnoreMissing();
        });

        $context = $this->createCoverageContext();
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $employee = User::factory()->create([
            'role' => User::ROLE_EMPLOYE,
            'is_active' => true,
            'primary_service_zone_id' => $context['zone']->id,
        ]);

        app(CreateBookingAction::class)->execute(
            client: $client,
            postal: $context['postalCode'],
            zone: $context['zone'],
            catalog: $context['service'],
            rule: $context['rule'],
            assignedEmployee: $employee,
            data: [
                'date' => now()->addDay()->toDateString(),
                'heure' => '10:00',
                'service_zone_id' => $context['zone']->id,
                'postal_code_id' => $context['postalCode']->id,
                'service_identifier' => $context['service']->code ?: $context['service']->slug,
                'type_lieu' => 'maison',
                'frequence' => 'ponctuel',
                'surface' => '50_100',
                'adresse' => '1 rue Test',
                'ville' => $context['postalCode']->city_name,
                'code_postal' => $context['postalCode']->code,
                'telephone_client' => '+32490000000',
                'priorite' => 'normale',
                'options_prestation' => [],
                'zones_specifiques' => [],
                'materiel_specifique' => [],
                'is_recurrent' => false,
                'status' => 'confirme',
                'booking_mode' => 'asap',
                'duree_estimee' => 90,
                'devis_estime' => 100.0,
                'employe_id' => $employee->id,
            ],
        );

        $this->assertCount(1, $recues, 'Une réservation ASAP web doit entrer dans le moteur, une fois.');
    }

    /**
     * @param  array<int, int>  $dispatched  reçoit l'id de chaque mission passée au dispatch
     */
    private function mockDispatch(array &$dispatched = []): void
    {
        $this->mock(MissionDispatchService::class, function (MockInterface $mock) use (&$dispatched) {
            $mock->shouldReceive('dispatchToNextProvider')
                ->andReturnUsing(function ($mission) use (&$dispatched) {
                    $dispatched[] = $mission->id;

                    return null;
                });
            $mock->shouldIgnoreMissing();
        });
    }

    private function createAsapBooking()
    {
        return $this->createBooking('asap');
    }

    private function createBooking(string $mode)
    {
        $client = User::factory()->create();
        $catalog = ServiceCatalog::factory()->create();

        return app(CreateBookingFromApiAction::class)->execute($client, [
            'service_catalog_id' => $catalog->id,
            'address' => 'Rue de la Loi 1',
            'city' => 'Bruxelles',
            'postal_code' => '1000',
            'scheduled_date' => now()->addDay()->toDateString(),
            'scheduled_time' => '10:00',
            'booking_mode' => $mode,
        ]);
    }
}
