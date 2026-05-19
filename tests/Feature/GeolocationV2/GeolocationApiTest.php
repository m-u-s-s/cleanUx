<?php

namespace Tests\Feature\GeolocationV2;

use App\Models\User;
use App\Services\GeolocationV2\Contracts\GeocodingProviderContract;
use App\Services\GeolocationV2\Providers\MockGeocodingProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GeolocationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('geolocation_v2.provider', 'mock');
        Config::set('geolocation_v2.autocomplete_min_chars', 3);
        Config::set('geolocation_v2.distance_modes', ['driving', 'walking', 'bicycling', 'transit']);
        Config::set('geolocation_v2.distance_default_mode', 'driving');
        Config::set('geolocation_v2.isochrone_avg_speed_kmh', ['driving' => 35]);
        Config::set('geolocation_v2.earth_radius_meters', 6_371_000);

        $this->app->bind(GeocodingProviderContract::class, MockGeocodingProvider::class);
    }

    public function test_autocomplete_endpoint_requires_auth(): void
    {
        $this->getJson('/api/v2/geo/autocomplete?q=Bruxelles')->assertStatus(401);
    }

    public function test_autocomplete_endpoint_returns_suggestions(): void
    {
        $user = User::factory()->client()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v2/geo/autocomplete?q=Bruxelles');
        $response->assertOk();
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertSame('Bruxelles', $data[0]['main_text']);
        $this->assertSame('mock', $response->json('provider'));
    }

    public function test_geocode_returns_lat_lng(): void
    {
        $user = User::factory()->client()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v2/geo/geocode?address=1050+Ixelles&country=BE');
        $response->assertOk();
        $this->assertSame('BE', $response->json('data.country_code'));
        $this->assertSame('1050', $response->json('data.postal_code'));
    }

    public function test_geocode_returns_404_when_not_found(): void
    {
        $user = User::factory()->client()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v2/geo/geocode?address=Atlantis+Lost+City')
            ->assertStatus(404);
    }

    public function test_reverse_geocode_returns_closest(): void
    {
        $user = User::factory()->client()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v2/geo/reverse?lat=51.22&lng=4.40');
        $response->assertOk();
        $this->assertSame('Anvers', $response->json('data.locality'));
    }

    public function test_distance_endpoint_returns_meters_and_duration(): void
    {
        $user = User::factory()->client()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v2/geo/distance', [
            'origin_lat' => 50.8467, 'origin_lng' => 4.3525,
            'dest_lat' => 51.2194, 'dest_lng' => 4.4025,
            'mode' => 'driving',
        ]);
        $response->assertOk();
        $this->assertGreaterThan(35_000, (int) $response->json('data.distance_meters'));
        $this->assertSame('driving', $response->json('data.mode'));
    }

    public function test_distance_validates_lat_lng_range(): void
    {
        $user = User::factory()->client()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v2/geo/distance', [
            'origin_lat' => 200, 'origin_lng' => 0, 'dest_lat' => 0, 'dest_lng' => 0,
        ])->assertStatus(422);
    }

    public function test_admin_stats_endpoint(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/geolocation-v2/stats');
        $response->assertOk();
        $this->assertSame('mock', $response->json('provider'));
        $this->assertArrayHasKey('cache', $response->json());
    }

    public function test_admin_purge_cache_runs(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/geolocation-v2/cache/purge');
        $response->assertOk();
        $this->assertTrue((bool) $response->json('ok'));
    }
}
