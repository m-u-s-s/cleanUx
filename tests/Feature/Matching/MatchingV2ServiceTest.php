<?php

namespace Tests\Feature\Matching;

use App\Models\Booking;
use App\Models\BookingMatchingDecision;
use App\Models\ProviderProfile;
use App\Models\ServiceZone;
use App\Models\User;
use App\Services\Matching\MatchingV2Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class MatchingV2ServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ServiceZone $zone;
    protected Booking $booking;
    protected MatchingV2Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->zone = ServiceZone::factory()->create();

        $client = User::factory()->client()->create();
        $this->booking = Booking::create([
            'client_id' => $client->id,
            'service_zone_id' => $this->zone->id,
            'date' => now()->addDay(),
            'heure' => '10:00',
            'status' => 'en_attente',
            'devis_estime' => 100,
            'booking_mode' => 'scheduled',
        ]);

        $this->service = app(MatchingV2Service::class);
    }

    public function test_rank_candidates_returns_sorted_by_score_desc(): void
    {
        $high = $this->createProvider(rating: 5.0, ratingCount: 30, online: true);
        $low = $this->createProvider(rating: 2.0, ratingCount: 30, online: true);

        $ranked = $this->service->rankCandidates($this->booking);

        $this->assertGreaterThanOrEqual(2, $ranked->count());
        $firstId = $ranked->first()['employee']->id;
        $this->assertSame($high->id, $firstId);
    }

    public function test_best_for_records_decision_with_breakdown(): void
    {
        $this->createProvider(rating: 5.0, ratingCount: 30, online: true);

        $best = $this->service->bestFor($this->booking);
        $this->assertNotNull($best);

        $decision = BookingMatchingDecision::query()
            ->where('booking_id', $this->booking->id)
            ->first();

        $this->assertNotNull($decision);
        $this->assertSame($best->id, (int) $decision->selected_user_id);
        $this->assertNotNull($decision->selected_breakdown);
        $this->assertNotEmpty($decision->weights_snapshot);
        $this->assertSame('v2', $decision->algorithm_version);
    }

    public function test_returns_null_when_no_zone(): void
    {
        $this->booking->update(['service_zone_id' => null]);

        $best = $this->service->bestFor($this->booking->fresh());
        $this->assertNull($best);
    }

    public function test_fallback_returns_below_threshold_when_no_match(): void
    {
        // Force min threshold high
        Config::set('matching.thresholds.min_acceptable_score', 99);
        Config::set('matching.thresholds.fallback_if_no_match', true);

        $this->createProvider(rating: 1.0, ratingCount: 30, online: true);

        $ranked = $this->service->rankCandidates($this->booking);
        $this->assertGreaterThan(0, $ranked->count(), 'Fallback should return candidates even below threshold');
    }

    protected function createProvider(float $rating, int $ratingCount, bool $online): User
    {
        $user = User::factory()->create([
            'role' => 'employe',
            'primary_service_zone_id' => $this->zone->id,
            'is_active' => true,
        ]);

        ProviderProfile::create([
            'user_id' => $user->id,
            'provider_type' => 'independent',
            'status' => 'active',
            'verification_status' => 'verified',
            'rating_avg' => $rating,
            'rating_count' => $ratingCount,
            'is_online' => $online,
        ]);

        $user->serviceZones()->attach($this->zone->id, [
            'assignment_type' => 'primary',
            'is_active' => true,
        ]);

        return $user;
    }
}
