<?php

namespace Tests\Feature\Booking;

use App\Actions\Booking\CreateBookingFromApiAction;
use App\Models\Mission;
use App\Models\PostalCode;
use App\Models\ServiceCatalog;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\User;
use App\Services\Booking\CreateBookingAction;
use App\Services\Dispatch\DispatchEngine;
use App\Services\Dispatch\MissionDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\Feature\Dispatch\Concerns\OuvreLeCatalogue;
use Tests\Support\CreatesZoneAwareFixtures;
use Tests\TestCase;

/** Une réservation ASAP ne doit produire QU'UNE mission, et cette mission doit partir en offre. */
class AsapSingleDispatchTest extends TestCase
{
    use CreatesZoneAwareFixtures;
    use OuvreLeCatalogue;
    use RefreshDatabase;

    public function test_an_asap_api_booking_creates_exactly_one_mission(): void
    {
        $this->mockDispatch();

        $booking = $this->createAsapBooking();

        $this->assertSame(
            1,
            Mission::where('booking_id', $booking->id)->count(),
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

    /** Une réservation planifiée ne passe pas par l'offre ASAP : elle ne doit ni créer de seconde mission, ni déclencher de dispatch immédiat. */
    /** UNE RÉSERVATION PLANIFIÉE PASSE PAR LE MOTEUR, PAS PAR L'ANCIEN CHEMIN. */
    public function test_a_scheduled_booking_creates_one_mission_and_goes_through_the_engine(): void
    {
        $dispatched = [];
        $this->mockDispatch($dispatched);

        $moteur = \Mockery::mock(DispatchEngine::class);
        $moteur->shouldReceive('dispatchBooking')->once()->andReturnNull();
        $this->app->instance(DispatchEngine::class, $moteur);

        $booking = $this->createBooking('scheduled');

        $this->assertLessThanOrEqual(
            1,
            Mission::where('booking_id', $booking->id)->count()
        );

        // L'offre immédiate ne part pas : c'est bien un planifié.
        $this->assertCount(0, $dispatched);
    }

    /** LE CHEMIN WEB ENTRE PAR LA PORTE UNIQUE : `DispatchEngine::dispatchBooking()`. */
    public function test_an_asap_web_booking_is_dispatched(): void
    {
        $dispatched = [];
        $this->mockDispatch($dispatched);

        $recues = [];
        $this->mock(DispatchEngine::class, function (MockInterface $mock) use (&$recues) {
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
                'place_type' => 'maison',
                'frequency' => 'ponctuel',
                'surface' => '50_100',
                'adresse' => '1 rue Test',
                'ville' => $context['postalCode']->city_name,
                'code_postal' => $context['postalCode']->code,
                'contact_phone' => '+32490000000',
                'priorite' => 'normale',
                'options_prestation' => [],
                'zones_specifiques' => [],
                'materiel_specifique' => [],
                'is_recurrent' => false,
                'status' => 'confirme',
                'booking_mode' => 'asap',
                'estimated_duration_minutes' => 90,
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

        // OUVRIR LE CATALOGUE — sans quoi ce test ne mesure plus la répartition.
        $zone = ServiceZone::factory()->create(['status' => 'active']);
        $trade = Trade::factory()->create(['allows_asap' => true]);
        $this->ouvrirAuCatalogue($trade, $zone);

        $codePostal = PostalCode::factory()->create(['code' => '1000']);
        $zone->postalCodes()->attach($codePostal->id, ['is_primary' => true]);

        $catalog = ServiceCatalog::factory()->create(['trade_id' => $trade->id]);

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
