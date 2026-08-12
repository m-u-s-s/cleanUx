<?php

namespace Tests\Feature\Missions;

use App\Actions\Booking\CreateBookingFromApiAction;
use App\Jobs\Missions\GeocodeMissionDestination;
use App\Models\Booking;
use App\Models\Mission;
use App\Models\PostalCode;
use App\Models\ServiceCatalog;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\User;
use App\Services\Geocoding\GeocodingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Feature\Dispatch\Concerns\OuvreLeCatalogue;
use Tests\TestCase;

/**
 * Deux chemins de création (CreateBookingFromApiAction et ProcessRecurringBookings) ne
 * renseignaient aucune destination, contrairement à la synchronisation depuis une réservation, qui
 * géocode l'adresse. Sans elle, la carte du prestataire n'a aucun marqueur à afficher.
 *
 * Le géocodeur est injecté à la main plutôt que stubbé via Http::fake() : la classe de base
 * Tests\TestCase enregistre déjà un stub `nominatim.openstreetmap.org/*` renvoyant Bruxelles, et
 * un Http::fake() posé plus tard ne le supplante PAS (Laravel fusionne les stubs, le premier
 * motif correspondant gagne). Passer le service directement rend ces tests indépendants de ce
 * détail et vérifie le branchement du job, pas le transport HTTP de GeocodingService.
 */
class GeocodeMissionDestinationTest extends TestCase
{
    use OuvreLeCatalogue;
    use RefreshDatabase;

    public function test_it_copies_the_client_supplied_destination_without_calling_the_geocoder(): void
    {
        $booking = $this->makeBooking(['destination_lat' => 50.8466, 'destination_lng' => 4.3528]);
        $mission = Mission::create(['booking_id' => $booking->id, 'status' => 'planned']);

        (new GeocodeMissionDestination($mission->id))->handle($this->geocoderThatMustNotBeCalled());

        $mission->refresh();
        $this->assertSame('50.8466000', $mission->destination_lat);
        $this->assertSame('4.3528000', $mission->destination_lng);
    }

    public function test_it_geocodes_the_address_when_the_booking_has_no_coordinates(): void
    {
        $booking = $this->makeBooking();
        $mission = Mission::create(['booking_id' => $booking->id, 'status' => 'planned']);
        $geocoder = $this->geocoderReturning(['lat' => 50.6402, 'lng' => 5.5713]);

        (new GeocodeMissionDestination($mission->id))->handle($geocoder);

        $mission->refresh();
        $this->assertSame('50.6402000', $mission->destination_lat);
        $this->assertSame('5.5713000', $mission->destination_lng);
        // L'adresse du client est bien celle transmise au géocodeur, pas une autre colonne.
        $this->assertSame(['12 rue du Test', '1000', 'Bruxelles', 'BE'], $geocoder->received);
    }

    public function test_it_resolves_bookings_attached_through_the_legacy_rendez_vous_column(): void
    {
        $booking = $this->makeBooking();
        $mission = Mission::create(['booking_id' => $booking->id, 'status' => 'planned']);

        (new GeocodeMissionDestination($mission->id))
            ->handle($this->geocoderReturning(['lat' => 51.2194, 'lng' => 4.4025]));

        $this->assertSame('51.2194000', $mission->refresh()->destination_lat);
    }

    public function test_it_leaves_an_already_geocoded_mission_untouched(): void
    {
        $booking = $this->makeBooking(['destination_lat' => 50.8466, 'destination_lng' => 4.3528]);
        $mission = Mission::create([
            'booking_id' => $booking->id,
            'status' => 'planned',
            'destination_lat' => 48.8566,
            'destination_lng' => 2.3522,
        ]);

        (new GeocodeMissionDestination($mission->id))->handle($this->geocoderThatMustNotBeCalled());

        $this->assertSame('48.8566000', $mission->refresh()->destination_lat);
    }

    public function test_an_unresolvable_address_leaves_the_mission_without_coordinates_and_does_not_throw(): void
    {
        $booking = $this->makeBooking();
        $mission = Mission::create(['booking_id' => $booking->id, 'status' => 'planned']);

        (new GeocodeMissionDestination($mission->id))->handle($this->geocoderReturning(null));

        $this->assertNull($mission->refresh()->destination_lat);
    }

    /**
     * Comportement observé sur le vrai Nominatim : « 10 Rue des Arts, 1000, Bruxelles, BE » ne
     * rend AUCUN résultat, alors que « 1000, Bruxelles, BE » rend le centroïde. La saisie libre
     * produit couramment le format numéro-en-tête, donc sans repli une part réelle des missions
     * resterait sans marqueur.
     */
    public function test_it_falls_back_to_the_postal_code_centroid_when_the_full_address_is_unresolvable(): void
    {
        $booking = $this->makeBooking();
        $mission = Mission::create(['booking_id' => $booking->id, 'status' => 'planned']);

        $geocoder = new class extends GeocodingService
        {
            public int $calls = 0;

            public function resolve(?string $addressLine, ?string $postalCode, ?string $city, ?string $countryCode = 'BE'): ?array
            {
                $this->calls++;

                // Rejette la ligne d'adresse, accepte le code postal seul.
                return $addressLine === null ? ['lat' => 50.8469, 'lng' => 4.3667] : null;
            }
        };

        (new GeocodeMissionDestination($mission->id))->handle($geocoder);

        $this->assertSame('50.8469000', $mission->refresh()->destination_lat);
        $this->assertSame(2, $geocoder->calls, 'Le repli doit être une seconde tentative, pas un remplacement.');
    }

    /**
     * Câblage : sans cette mise en file, une mission créée par le chemin API n'obtient jamais de
     * destination, et l'offre arrive sur la carte du prestataire sans marqueur.
     */
    public function test_creating_an_asap_booking_queues_the_geocoding_of_its_mission(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        /*
         * LE CATALOGUE DOIT ÊTRE OUVERT POUR L'IMMÉDIAT, sinon la création refuse avant même
         * d'arriver au géocodage. `trades.allows_asap` vaut faux par défaut en base, et une zone
         * sans ligne de `trade_zone_pricing` vaut « fermé » — cette fixture décrivait un service
         * que la plateforme ne vend nulle part.
         */
        $zone = ServiceZone::factory()->create(['status' => 'active']);
        $trade = Trade::factory()->create(['allows_asap' => true]);
        $this->ouvrirAuCatalogue($trade, $zone);

        $codePostal = PostalCode::factory()->create(['code' => '1000']);
        $zone->postalCodes()->attach($codePostal->id, ['is_primary' => true]);

        $catalog = ServiceCatalog::factory()->create(['trade_id' => $trade->id]);

        app(CreateBookingFromApiAction::class)->execute($user, [
            'service_catalog_id' => $catalog->id,
            'address' => '12 rue du Test',
            'city' => 'Bruxelles',
            'postal_code' => '1000',
            'scheduled_date' => now()->addDay()->toDateString(),
            'scheduled_time' => '10:00',
            'booking_mode' => 'asap',
        ]);

        Queue::assertPushed(GeocodeMissionDestination::class);
    }

    /**
     * Géocodeur de test enregistrant ses arguments et renvoyant un résultat fixe.
     */
    protected function geocoderReturning(?array $result): GeocodingService
    {
        return new class($result) extends GeocodingService
        {
            /** @var array<int, mixed> */
            public array $received = [];

            public function __construct(private ?array $result) {}

            public function resolve(?string $addressLine, ?string $postalCode, ?string $city, ?string $countryCode = 'BE'): ?array
            {
                $this->received = [$addressLine, $postalCode, $city, $countryCode];

                return $this->result;
            }
        };
    }

    protected function geocoderThatMustNotBeCalled(): GeocodingService
    {
        return new class extends GeocodingService
        {
            public function resolve(?string $addressLine, ?string $postalCode, ?string $city, ?string $countryCode = 'BE'): ?array
            {
                throw new \RuntimeException('Le géocodeur ne doit pas être appelé ici.');
            }
        };
    }

    /**
     * withoutEvents : un booking `confirme` réveille RendezVousObserver, qui crée la mission de
     * la réservation et géocode son adresse. Elle brouillerait les assertions d'un test qui porte
     * sur une seule mission. Même précaution que DevProviderMissionSeeder.
     */
    protected function makeBooking(array $overrides = []): Booking
    {
        $client = User::factory()->create();

        return Booking::withoutEvents(fn () => Booking::create(array_merge([
            'booking_reference' => 'CUX-'.strtoupper(Str::random(6)),
            'customer_user_id' => $client->id,
            'client_id' => $client->id,
            'address' => '12 rue du Test',
            'city' => 'Bruxelles',
            'postal_code' => '1000',
            'scheduled_date' => now()->addDay()->toDateString(),
            'scheduled_time' => '10:00:00',
            'status' => 'confirme',
            'currency' => 'EUR',
            'priority' => 'normal',
            'booking_mode' => 'scheduled',
        ], $overrides)));
    }
}
