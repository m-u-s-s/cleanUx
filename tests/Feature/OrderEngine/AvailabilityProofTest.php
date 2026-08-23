<?php

namespace Tests\Feature\OrderEngine;

use App\Livewire\OrderEngine\OrderJourney;
use App\Models\ProviderProfile;
use App\Models\Trade;
use App\Models\User;
use App\Services\GeolocationV2\AddressSuggestion;
use App\Services\GeolocationV2\GeocodingResult;
use App\Services\GeolocationV2\GeocodingService;
use App\Services\OrderEngine\ProviderAvailabilityLookup;
use Database\Seeders\OrderEngineCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

/** La preuve de disponibilité — et son honnêteté. */
class AvailabilityProofTest extends TestCase
{
    use RefreshDatabase;

    /** Bruxelles centre — le lieu de référence du catalogue semé. */
    private const LAT = 50.8467;

    private const LNG = 4.3525;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OrderEngineCatalogSeeder::class);
    }

    /** Le compte est réel : il vient des prestataires du métier dont on connaît la position. */
    public function test_it_counts_the_providers_actually_within_the_radius(): void
    {
        $trade = $this->peinture();

        $this->providerAt($trade, 50.8470, 4.3530);   // ~50 m
        $this->providerAt($trade, 50.8600, 4.3600);   // ~1,6 km
        $this->providerAt($trade, 51.2100, 4.4200);   // Anvers, ~40 km

        $snapshot = app(ProviderAvailabilityLookup::class)->forTrade($trade, self::LAT, self::LNG);

        $this->assertSame(2, $snapshot->providerCount);
        $this->assertTrue($snapshot->hasProviders());
    }

    /** Un prestataire d'un autre métier ne gonfle pas le compte. */
    public function test_providers_of_another_trade_are_not_counted(): void
    {
        $this->providerAt(Trade::where('slug', 'plumbing')->firstOrFail(), 50.8470, 4.3530);

        $this->assertSame(
            0,
            app(ProviderAvailabilityLookup::class)->forTrade($this->peinture(), self::LAT, self::LNG)->providerCount,
        );
    }

    /** Un prestataire inactif non plus : le compte porte sur qui peut réellement venir. */
    public function test_an_inactive_provider_is_not_counted(): void
    {
        $trade = $this->peinture();
        $provider = $this->providerAt($trade, 50.8470, 4.3530);
        $provider->providerProfile->update(['status' => 'suspended']);

        $this->assertSame(
            0,
            app(ProviderAvailabilityLookup::class)->forTrade($trade, self::LAT, self::LNG)->providerCount,
        );
    }

    /** LA garantie d'honnêteté : sans position connue, on n'annonce RIEN. */
    public function test_it_refuses_to_claim_proximity_when_no_position_is_known(): void
    {
        $trade = $this->peinture();
        $this->providerAt($trade, null, null);
        $this->providerAt($trade, null, null);

        $snapshot = app(ProviderAvailabilityLookup::class)->forTrade($trade, self::LAT, self::LNG);

        $this->assertFalse($snapshot->isTrustworthy());
        $this->assertSame(0, $snapshot->providerCount);
    }

    /** L'impasse offre toujours une suite — un écran d'erreur sans action est un bug produit. */
    public function test_an_empty_area_still_offers_a_way_forward(): void
    {
        $peinture = $this->peinture();

        // Personne en peinture à proximité, mais un électricien à 1,6 km.
        $this->providerAt($peinture, 51.2100, 4.4200);
        $this->providerAt(Trade::where('slug', 'electrical')->firstOrFail(), 50.8600, 4.3600);

        $snapshot = app(ProviderAvailabilityLookup::class)->forTrade($peinture, self::LAT, self::LNG);

        $this->assertFalse($snapshot->hasProviders());
        $this->assertTrue($snapshot->hasWayForward(), 'Aucune suite proposée : c’est un cul-de-sac.');
        $this->assertNotEmpty($snapshot->nearbyTrades);
        $this->assertSame('Électricité', $snapshot->nearbyTrades[0]['name']);
    }

    /** Le rayon élargi est une porte de sortie, pas une promesse : il est annoncé comme tel. */
    public function test_a_wider_radius_is_offered_when_the_close_one_is_empty(): void
    {
        $trade = $this->peinture();
        $this->providerAt($trade, 50.9500, 4.3525); // ~11 km : hors des 8 km, dans les 25 km

        $snapshot = app(ProviderAvailabilityLookup::class)->forTrade($trade, self::LAT, self::LNG);

        $this->assertSame(0, $snapshot->providerCount);
        $this->assertSame(1, $snapshot->widerRadiusCount);
        $this->assertTrue($snapshot->hasWayForward());
    }

    /** Les métiers suggérés viennent du MÊME secteur : autrement ce n'est pas une suggestion. */
    public function test_suggested_trades_stay_within_the_same_sector(): void
    {
        $peinture = $this->peinture();
        $this->providerAt(Trade::where('slug', 'jardinage')->firstOrFail(), 50.8470, 4.3530);

        $snapshot = app(ProviderAvailabilityLookup::class)->forTrade($peinture, self::LAT, self::LNG);

        $this->assertSame([], $snapshot->nearbyTrades, 'Un métier d’un autre secteur a été proposé.');
    }

    /** Aucune heure n'est inventée : sans agenda exploitable, on ne promet pas de créneau. */
    public function test_no_time_is_invented_when_no_calendar_says_so(): void
    {
        $trade = $this->peinture();
        $this->providerAt($trade, 50.8470, 4.3530);

        $snapshot = app(ProviderAvailabilityLookup::class)->forTrade($trade, self::LAT, self::LNG);

        $this->assertSame(1, $snapshot->providerCount);
        $this->assertNull($snapshot->earliestAt);
    }

    // ─── Le parcours ─────────────────────────────────────────────────────────────────────────

    /** L'adresse débloque la preuve : le chiffre s'affiche sur l'écran du client. */
    public function test_the_journey_shows_the_proof_once_the_address_is_located(): void
    {
        $trade = $this->peinture();
        $this->providerAt($trade, 50.8470, 4.3530);
        $this->providerAt($trade, 50.8480, 4.3540);
        $this->fakeGeocoder(self::LAT, self::LNG);

        Livewire::test(OrderJourney::class)
            ->call('selectTrade', $trade->id)
            ->set('address', 'Rue de la Loi 1, 1000 Bruxelles')
            ->assertSee('2 professionnels')
            ->assertSee('à moins de 8 km');
    }

    /** Une adresse non située ne bloque pas la commande. */
    public function test_an_unresolved_address_never_blocks_the_order(): void
    {
        $this->fakeGeocoder(null, null);

        Livewire::test(OrderJourney::class)
            ->call('selectTrade', $this->peinture()->id)
            ->set('address', 'Une adresse introuvable')
            ->assertSet('addressUnresolved', true)
            ->assertSee('Vous pouvez continuer');
    }

    /** Le pays qui oriente le géocodage est une DONNÉE, pas une constante dans le code. */
    public function test_the_geocoding_country_comes_from_configuration(): void
    {
        config(['order_engine.geocoding_country' => 'FR']);
        $seen = null;

        $this->mock(GeocodingService::class, function ($mock) use (&$seen) {
            $mock->shouldReceive('geocode')->andReturnUsing(function ($address, $country) use (&$seen) {
                $seen = $country;

                return null;
            });
        });

        Livewire::test(OrderJourney::class)
            ->call('selectTrade', $this->peinture()->id)
            ->set('address', '12 rue de Rivoli, 75001 Paris');

        $this->assertSame('FR', $seen, 'Le pays du géocodage reste figé dans le code.');
    }

    // ─── Saisie de l'adresse ─────────────────────────────────────────────────────────────────

    /** Le champ adresse PROPOSE au lieu de faire deviner. */
    public function test_the_address_field_suggests_completions(): void
    {
        Livewire::test(OrderJourney::class)
            ->call('selectTrade', $this->peinture()->id)
            ->set('address', 'Bruxelles')
            ->assertSee('1000 Bruxelles, Belgique');
    }

    /** Choisir une suggestion situe la commande SANS second géocodage. */
    public function test_choosing_a_suggestion_locates_the_order_straight_away(): void
    {
        $this->mock(GeocodingService::class, function ($mock) {
            $mock->shouldReceive('autocomplete')->andReturn([
                new AddressSuggestion(
                    description: '1000 Bruxelles, Belgique',
                    latitude: self::LAT,
                    longitude: self::LNG,
                ),
            ]);
            // Aucun géocodage ne doit être demandé : la suggestion porte déjà sa position.
            $mock->shouldNotReceive('geocode');
        });

        Livewire::test(OrderJourney::class)
            ->call('selectTrade', $this->peinture()->id)
            ->call('chooseAddressSuggestion', '1000 Bruxelles, Belgique', self::LAT, self::LNG)
            ->assertSet('address', '1000 Bruxelles, Belgique')
            ->assertSet('lat', self::LAT);
    }

    /** « Utiliser ma position » : le client a déjà l'information dans sa poche. */
    public function test_the_client_can_hand_over_their_position(): void
    {
        Livewire::test(OrderJourney::class)
            ->call('selectTrade', $this->peinture()->id)
            ->call('useMyPosition', self::LAT, self::LNG)
            ->assertSet('lat', self::LAT)
            ->assertSet('lng', self::LNG)
            // On vérifie l'ÉTAT, pas le balisage : `wire:model` ne rend aucun attribut `value`,
            // donc chercher l'adresse dans le HTML échouerait alors que tout fonctionne.
            ->assertSet('address', '1000 Bruxelles, Belgique');
    }

    /** Une position que le serveur ne sait pas nommer situe quand même la commande. */
    public function test_an_unnameable_position_still_locates_the_order(): void
    {
        $this->mock(GeocodingService::class, function ($mock) {
            $mock->shouldReceive('reverseGeocode')->andReturn(null);
            $mock->shouldReceive('autocomplete')->andReturn([]);
        });

        Livewire::test(OrderJourney::class)
            ->call('selectTrade', $this->peinture()->id)
            ->call('useMyPosition', self::LAT, self::LNG)
            ->assertOk()
            ->assertSet('lat', self::LAT);
    }

    /** Un géocodage en panne ne fait pas tomber la page : c'est un confort, pas une dépendance. */
    public function test_a_broken_geocoder_degrades_instead_of_crashing(): void
    {
        $this->mock(GeocodingService::class, function ($mock) {
            $mock->shouldReceive('geocode')->andThrow(new \RuntimeException('service indisponible'));
        });

        Livewire::test(OrderJourney::class)
            ->call('selectTrade', $this->peinture()->id)
            ->set('address', 'Rue de la Loi 1, 1000 Bruxelles')
            ->assertOk()
            ->assertSet('addressUnresolved', true);
    }

    /** L'adresse est enregistrée sur la COMMANDE : en multi-services, on ne la redemande pas. */
    public function test_the_address_is_stored_once_on_the_order(): void
    {
        $this->fakeGeocoder(self::LAT, self::LNG);

        $component = Livewire::test(OrderJourney::class)
            ->call('selectTrade', $this->peinture()->id)
            ->set('address', 'Rue de la Loi 1, 1000 Bruxelles');

        $draft = $component->instance()->draft()->fresh();

        $this->assertSame('Rue de la Loi 1, 1000 Bruxelles', $draft->address);
        $this->assertEqualsWithDelta(self::LAT, (float) $draft->lat, 0.0001);
    }

    /** Rien n'est promis tant que l'adresse n'est pas située : pas de décoration. */
    public function test_nothing_is_promised_before_the_address_is_located(): void
    {
        $component = Livewire::test(OrderJourney::class)->call('selectTrade', $this->peinture()->id);

        $this->assertNull($component->instance()->availability());
    }

    // ─── Fabriques ───────────────────────────────────────────────────────────────────────────

    private function peinture(): Trade
    {
        return Trade::where('slug', 'peinture')->firstOrFail();
    }

    private function providerAt(Trade $trade, ?float $lat, ?float $lng): User
    {
        $provider = User::factory()->create(['role' => User::ROLE_PROVIDER]);

        ProviderProfile::create([
            'user_id' => $provider->id,
            'status' => 'active',
            'current_lat' => $lat,
            'current_lng' => $lng,
        ]);

        DB::table('trade_user')->insert([
            'user_id' => $provider->id,
            'trade_id' => $trade->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $provider->fresh('providerProfile');
    }

    /** Le géocodeur est remplacé dans le conteneur. */
    private function fakeGeocoder(?float $lat, ?float $lng): void
    {
        $this->mock(GeocodingService::class, function ($mock) use ($lat, $lng) {
            $mock->shouldReceive('geocode')->andReturn(
                $lat === null ? null : new GeocodingResult($lat, $lng, 'Rue de la Loi 1, 1000 Bruxelles'),
            );
        });
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
