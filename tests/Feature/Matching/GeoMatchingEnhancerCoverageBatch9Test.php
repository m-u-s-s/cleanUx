<?php

namespace Tests\Feature\Matching;

use App\Models\Booking;
use App\Models\ServiceZone;
use App\Models\User;
use App\Services\Matching\GeoMatchingEnhancer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeoMatchingEnhancerCoverageBatch9Test extends TestCase
{
    use RefreshDatabase;

    protected GeoMatchingEnhancer $enhancer;

    protected ServiceZone $zone;

    protected User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enhancer = app(GeoMatchingEnhancer::class);
        $this->zone = ServiceZone::factory()->create();
        $this->client = User::factory()->client()->create();
    }

    protected function makeBooking(): Booking
    {
        return Booking::create([
            'client_id' => $this->client->id,
            'service_zone_id' => $this->zone->id,
            'date' => now()->addDay(),
            'heure' => '10:00',
            'status' => 'en_attente',
            'devis_estime' => 100,
        ]);
    }

    protected function providerAt(float $lat, float $lng): User
    {
        $u = User::factory()->employe()->create();
        $u->latitude = $lat;
        $u->longitude = $lng;

        return $u;
    }

    public function test_proximity_score_is_max_for_same_location(): void
    {
        $booking = $this->makeBooking();
        $booking->latitude = 50.0;
        $booking->longitude = 4.0;

        $provider = $this->providerAt(50.0, 4.0);

        $this->assertSame(100.0, $this->enhancer->proximityScore($provider, $booking));
    }

    public function test_proximity_score_decreases_monotonically_with_distance(): void
    {
        $booking = $this->makeBooking();
        $booking->latitude = 50.0;
        $booking->longitude = 4.0;

        // ~5.5 km -> bucket 5..15
        $near = $this->enhancer->proximityScore($this->providerAt(50.05, 4.0), $booking);
        // ~22 km -> bucket 15..30
        $mid = $this->enhancer->proximityScore($this->providerAt(50.2, 4.0), $booking);
        // ~44.5 km -> bucket 30..50
        $far = $this->enhancer->proximityScore($this->providerAt(50.4, 4.0), $booking);
        // ~111 km -> beyond 50
        $veryFar = $this->enhancer->proximityScore($this->providerAt(51.0, 4.0), $booking);

        $this->assertGreaterThan(60.0, $near);
        $this->assertLessThanOrEqual(90.0, $near);

        $this->assertGreaterThan(30.0, $mid);
        $this->assertLessThanOrEqual(60.0, $mid);

        $this->assertGreaterThan(10.0, $far);
        $this->assertLessThanOrEqual(30.0, $far);

        $this->assertSame(0.0, $veryFar);

        $this->assertGreaterThan($mid, $near);
        $this->assertGreaterThan($far, $mid);
        $this->assertGreaterThan($veryFar, $far);
    }

    public function test_proximity_score_is_null_without_any_coordinates(): void
    {
        $booking = $this->makeBooking();
        $provider = User::factory()->employe()->create();

        $this->assertNull($this->enhancer->proximityScore($provider, $booking));
    }

    public function test_proximity_score_is_null_when_provider_has_coords_but_booking_does_not(): void
    {
        $booking = $this->makeBooking();
        $provider = $this->providerAt(50.0, 4.0);

        $this->assertNull($this->enhancer->proximityScore($provider, $booking));
    }

    public function test_proximity_score_uses_geocoded_addresses_when_no_direct_coords(): void
    {
        $booking = $this->makeBooking();
        $booking->address = '1050 Ixelles';

        $provider = User::factory()->employe()->create();
        $provider->base_address = '1000 Bruxelles';

        $score = $this->enhancer->proximityScore($provider, $booking);

        // Bruxelles <-> Ixelles is only a couple km -> top bucket.
        $this->assertNotNull($score);
        $this->assertSame(100.0, $score);
    }

    public function test_filter_within_radius_filters_and_sorts_by_distance(): void
    {
        $booking = $this->makeBooking();
        $booking->latitude = 50.0;
        $booking->longitude = 4.0;

        $closest = $this->providerAt(50.02, 4.0);   // ~2 km
        $middle = $this->providerAt(50.1, 4.0);     // ~11 km
        $outside = $this->providerAt(51.0, 4.0);    // ~111 km

        $result = $this->enhancer->filterWithinRadius([$middle, $outside, $closest], $booking, 30.0);

        $this->assertCount(2, $result);
        $this->assertSame($closest->id, $result[0]['provider']->id);
        $this->assertSame($middle->id, $result[1]['provider']->id);
        $this->assertLessThan($result[1]['distance_km'], $result[0]['distance_km']);
        $this->assertIsFloat($result[0]['distance_km']);
    }

    public function test_filter_within_radius_skips_providers_without_coordinates(): void
    {
        $booking = $this->makeBooking();
        $booking->latitude = 50.0;
        $booking->longitude = 4.0;

        $withCoords = $this->providerAt(50.01, 4.0);
        $withoutCoords = User::factory()->employe()->create();

        $result = $this->enhancer->filterWithinRadius([$withoutCoords, $withCoords], $booking, 50.0);

        $this->assertCount(1, $result);
        $this->assertSame($withCoords->id, $result[0]['provider']->id);
    }

    public function test_filter_within_radius_returns_empty_without_booking_coords(): void
    {
        $booking = $this->makeBooking();
        $provider = $this->providerAt(50.0, 4.0);

        $this->assertSame([], $this->enhancer->filterWithinRadius([$provider], $booking, 50.0));
    }
}
