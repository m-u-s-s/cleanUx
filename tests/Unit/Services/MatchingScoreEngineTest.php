<?php

namespace Tests\Unit\Services;

use App\Models\Booking;
use App\Models\ProviderPerformanceMetric;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Matching\MatchingScoreBreakdown;
use App\Services\Matching\MatchingScoreEngine;
use App\Services\Matching\ProviderPerformanceCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class MatchingScoreEngineTest extends TestCase
{
    use RefreshDatabase;

    private MatchingScoreEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'matching.weights' => [
                'rating'           => 25,
                'acceptance_rate'  => 15,
                'completion_rate'  => 15,
                'response_time'    => 10,
                'zone_proximity'   => 15,
                'workload'         => 10,
                'client_affinity'  => 5,
                'trade_specialty'  => 3,
                'recency_balance'  => 2,
            ],
            'matching.response_time.excellent_seconds' => 60,
            'matching.response_time.poor_seconds' => 600,
            'matching.diversification.enabled' => true,
            'matching.diversification.penalty_per_recent_mission' => 2.0,
            'matching.diversification.recent_window_hours' => 24,
        ]);

        $perfCalc = Mockery::mock(ProviderPerformanceCalculator::class);
        $perfCalc->shouldReceive('latestFor')->andReturn(null);

        $this->engine = new MatchingScoreEngine($perfCalc);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeProvider(array $profileAttrs = []): User
    {
        $provider = User::factory()->employe()->create();
        ProviderProfile::factory()->create(array_merge(
            ['user_id' => $provider->id],
            $profileAttrs
        ));
        return $provider->load('providerProfile');
    }

    private function makeBooking(array $attrs = []): Booking
    {
        return Booking::factory()->create($attrs);
    }

    // ────────────────────────────────────────────────
    // Return type
    // ────────────────────────────────────────────────

    public function test_score_returns_matching_score_breakdown(): void
    {
        $provider = $this->makeProvider();
        $booking  = $this->makeBooking();

        $result = $this->engine->score($provider, $booking);

        $this->assertInstanceOf(MatchingScoreBreakdown::class, $result);
        $this->assertSame((int) $provider->id, $result->userId);
    }

    public function test_total_score_is_between_0_and_100(): void
    {
        $provider = $this->makeProvider(['rating_avg' => 4.5, 'rating_count' => 20]);
        $booking  = $this->makeBooking();

        $result = $this->engine->score($provider, $booking, [
            'acceptance_rate' => 0.9,
            'completion_rate' => 0.95,
        ]);

        $this->assertGreaterThanOrEqual(0, $result->totalScore);
        $this->assertLessThanOrEqual(100, $result->totalScore);
    }

    // ────────────────────────────────────────────────
    // Rating component
    // ────────────────────────────────────────────────

    public function test_high_rating_with_many_reviews_scores_near_100(): void
    {
        $provider = $this->makeProvider(['rating_avg' => 5.0, 'rating_count' => 20]);
        $booking  = $this->makeBooking();

        $result = $this->engine->score($provider, $booking, [
            'rating_avg'   => 5.0,
            'rating_count' => 20,
        ]);

        // Rating component: base=100, confidence=1 => weighted = 100 * 25/100 = 25
        $this->assertSame(25.0, $result->components['rating']['weighted']);
    }

    public function test_new_provider_with_no_rating_gets_default_60_raw_score(): void
    {
        $provider = $this->makeProvider(['rating_avg' => null, 'rating_count' => 0]);
        $booking  = $this->makeBooking();

        $result = $this->engine->score($provider, $booking, [
            'rating_avg'   => null,
            'rating_count' => 0,
        ]);

        // default = 60.0 => weighted = 60 * 25 / 100 = 15
        $this->assertSame(15.0, $result->components['rating']['weighted']);
    }

    public function test_low_rating_reduces_score(): void
    {
        $provider = $this->makeProvider(['rating_avg' => 1.0, 'rating_count' => 20]);
        $booking  = $this->makeBooking();

        $resultLow = $this->engine->score($provider, $booking, [
            'rating_avg'   => 1.0,
            'rating_count' => 20,
        ]);
        $resultHigh = $this->engine->score($provider, $booking, [
            'rating_avg'   => 5.0,
            'rating_count' => 20,
        ]);

        $this->assertLessThan(
            $resultHigh->components['rating']['weighted'],
            $resultLow->components['rating']['weighted']
        );
    }

    // ────────────────────────────────────────────────
    // Acceptance rate component
    // ────────────────────────────────────────────────

    public function test_perfect_acceptance_rate_scores_100_raw(): void
    {
        $provider = $this->makeProvider();
        $booking  = $this->makeBooking();

        $result = $this->engine->score($provider, $booking, [
            'acceptance_rate' => 1.0,
        ]);

        $this->assertSame(100.0, $result->components['acceptance_rate']['raw']);
    }

    public function test_missing_acceptance_rate_defaults_to_70_raw(): void
    {
        $provider = $this->makeProvider();
        $booking  = $this->makeBooking();

        $result = $this->engine->score($provider, $booking, [
            'acceptance_rate' => null,
        ]);

        $this->assertSame(70.0, $result->components['acceptance_rate']['raw']);
    }

    // ────────────────────────────────────────────────
    // Completion rate component
    // ────────────────────────────────────────────────

    public function test_perfect_completion_rate_scores_100_raw(): void
    {
        $provider = $this->makeProvider();
        $booking  = $this->makeBooking();

        $result = $this->engine->score($provider, $booking, [
            'completion_rate' => 1.0,
        ]);

        $this->assertSame(100.0, $result->components['completion_rate']['raw']);
    }

    public function test_missing_completion_rate_defaults_to_75_raw(): void
    {
        $provider = $this->makeProvider();
        $booking  = $this->makeBooking();

        $result = $this->engine->score($provider, $booking, [
            'completion_rate' => null,
        ]);

        $this->assertSame(75.0, $result->components['completion_rate']['raw']);
    }

    // ────────────────────────────────────────────────
    // Response time component
    // ────────────────────────────────────────────────

    public function test_excellent_response_time_scores_100(): void
    {
        $provider = $this->makeProvider();
        $booking  = $this->makeBooking();

        $result = $this->engine->score($provider, $booking, [
            'avg_response_seconds' => 30,  // below 60s excellent threshold
        ]);

        $this->assertSame(100.0, $result->components['response_time']['raw']);
    }

    public function test_poor_response_time_scores_0(): void
    {
        $provider = $this->makeProvider();
        $booking  = $this->makeBooking();

        $result = $this->engine->score($provider, $booking, [
            'avg_response_seconds' => 700,  // above 600s poor threshold
        ]);

        $this->assertSame(0.0, $result->components['response_time']['raw']);
    }

    public function test_null_response_time_returns_default_60(): void
    {
        $provider = $this->makeProvider();
        $booking  = $this->makeBooking();

        $result = $this->engine->score($provider, $booking, [
            'avg_response_seconds' => null,
        ]);

        $this->assertSame(60.0, $result->components['response_time']['raw']);
    }

    // ────────────────────────────────────────────────
    // Recency balance (diversification)
    // ────────────────────────────────────────────────

    public function test_zero_recent_missions_scores_100_recency(): void
    {
        $provider = $this->makeProvider();
        $booking  = $this->makeBooking();

        $result = $this->engine->score($provider, $booking, [
            'recent_missions_24h' => 0,
        ]);

        $this->assertSame(100.0, $result->components['recency_balance']['raw']);
    }

    public function test_many_recent_missions_reduces_recency_score(): void
    {
        $provider = $this->makeProvider();
        $booking  = $this->makeBooking();

        $resultFew = $this->engine->score($provider, $booking, [
            'recent_missions_24h' => 0,
        ]);
        $resultMany = $this->engine->score($provider, $booking, [
            'recent_missions_24h' => 5,
        ]);

        $this->assertLessThan(
            $resultFew->components['recency_balance']['weighted'],
            $resultMany->components['recency_balance']['weighted']
        );
    }

    // ────────────────────────────────────────────────
    // Component structure
    // ────────────────────────────────────────────────

    public function test_breakdown_contains_all_expected_components(): void
    {
        $provider = $this->makeProvider();
        $booking  = $this->makeBooking();

        $result = $this->engine->score($provider, $booking);

        $expectedKeys = [
            'rating', 'acceptance_rate', 'completion_rate', 'response_time',
            'zone_proximity', 'workload', 'client_affinity', 'trade_specialty',
            'recency_balance',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $result->components, "Missing component: {$key}");
            $this->assertArrayHasKey('raw', $result->components[$key]);
            $this->assertArrayHasKey('weighted', $result->components[$key]);
            $this->assertArrayHasKey('weight', $result->components[$key]);
        }
    }

    public function test_to_array_includes_user_id_and_total_score(): void
    {
        $provider = $this->makeProvider();
        $booking  = $this->makeBooking();

        $result = $this->engine->score($provider, $booking)->toArray();

        $this->assertArrayHasKey('user_id', $result);
        $this->assertArrayHasKey('total_score', $result);
        $this->assertArrayHasKey('components', $result);
        $this->assertSame((int) $provider->id, $result['user_id']);
    }

    // ────────────────────────────────────────────────
    // Weights config
    // ────────────────────────────────────────────────

    public function test_weights_sum_is_100(): void
    {
        $weights = $this->engine->weights();

        $this->assertSame(100, array_sum($weights));
    }
}
