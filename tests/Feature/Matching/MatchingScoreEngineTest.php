<?php

namespace Tests\Feature\Matching;

use App\Models\Booking;
use App\Models\ProviderPerformanceMetric;
use App\Models\ProviderProfile;
use App\Models\ServiceZone;
use App\Models\User;
use App\Services\Matching\MatchingScoreEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchingScoreEngineTest extends TestCase
{
    use RefreshDatabase;

    protected User $provider;
    protected Booking $booking;
    protected ServiceZone $zone;
    protected MatchingScoreEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->zone = ServiceZone::factory()->create();

        $this->provider = User::factory()->create([
            'role' => 'employe',
            'primary_service_zone_id' => $this->zone->id,
        ]);

        ProviderProfile::create([
            'user_id' => $this->provider->id,
            'provider_type' => 'independent',
            'status' => 'active',
            'verification_status' => 'verified',
            'rating_avg' => 4.5,
            'rating_count' => 20,
        ]);

        $client = User::factory()->client()->create();

        $this->booking = Booking::create([
            'client_id' => $client->id,
            'service_zone_id' => $this->zone->id,
            'date' => now()->addDay(),
            'heure' => '10:00',
            'status' => 'en_attente',
            'devis_estime' => 100,
        ]);

        $this->engine = app(MatchingScoreEngine::class);
    }

    public function test_score_returns_breakdown_with_all_dimensions(): void
    {
        $breakdown = $this->engine->score($this->provider, $this->booking);

        $expectedKeys = [
            'rating', 'acceptance_rate', 'completion_rate', 'response_time',
            'zone_proximity', 'workload', 'client_affinity',
            'trade_specialty', 'recency_balance',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $breakdown->components, "Missing dimension: {$key}");
            $this->assertArrayHasKey('raw', $breakdown->components[$key]);
            $this->assertArrayHasKey('weighted', $breakdown->components[$key]);
            $this->assertArrayHasKey('weight', $breakdown->components[$key]);
        }
    }

    public function test_higher_rating_yields_higher_score(): void
    {
        $highRatedProvider = User::factory()->create([
            'role' => 'employe',
            'primary_service_zone_id' => $this->zone->id,
        ]);
        ProviderProfile::create([
            'user_id' => $highRatedProvider->id,
            'provider_type' => 'independent',
            'status' => 'active',
            'verification_status' => 'verified',
            'rating_avg' => 5.0,
            'rating_count' => 50,
        ]);

        $lowRatedProvider = User::factory()->create([
            'role' => 'employe',
            'primary_service_zone_id' => $this->zone->id,
        ]);
        ProviderProfile::create([
            'user_id' => $lowRatedProvider->id,
            'provider_type' => 'independent',
            'status' => 'active',
            'verification_status' => 'verified',
            'rating_avg' => 2.0,
            'rating_count' => 50,
        ]);

        $high = $this->engine->score($highRatedProvider, $this->booking);
        $low = $this->engine->score($lowRatedProvider, $this->booking);

        $this->assertGreaterThan(
            $low->components['rating']['weighted'],
            $high->components['rating']['weighted']
        );
    }

    public function test_primary_zone_gives_max_zone_score(): void
    {
        $breakdown = $this->engine->score($this->provider, $this->booking);
        $this->assertEqualsWithDelta(100.0, $breakdown->components['zone_proximity']['raw'], 0.01);
    }

    public function test_acceptance_metric_influences_score(): void
    {
        ProviderPerformanceMetric::create([
            'user_id' => $this->provider->id,
            'period_start' => now()->subDays(30)->toDateString(),
            'period_end' => now()->toDateString(),
            'window_days' => 30,
            'offers_received' => 10,
            'offers_accepted' => 9,
            'offers_declined' => 1,
            'offers_expired' => 0,
            'missions_completed' => 9,
            'missions_cancelled_by_provider' => 0,
            'acceptance_rate' => 0.9,
            'completion_rate' => 1.0,
            'cancellation_rate' => 0.0,
            'avg_response_seconds' => 30,
            'computed_at' => now(),
        ]);

        $breakdown = $this->engine->score($this->provider, $this->booking);

        $this->assertEqualsWithDelta(90.0, $breakdown->components['acceptance_rate']['raw'], 0.01);
        $this->assertEqualsWithDelta(100.0, $breakdown->components['response_time']['raw'], 0.01);
    }

    public function test_total_score_bounded_0_100(): void
    {
        $breakdown = $this->engine->score($this->provider, $this->booking);

        $this->assertGreaterThanOrEqual(0, $breakdown->totalScore);
        $this->assertLessThanOrEqual(100, $breakdown->totalScore);
    }
}
