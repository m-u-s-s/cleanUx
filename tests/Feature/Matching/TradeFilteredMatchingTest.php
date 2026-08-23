<?php

namespace Tests\Feature\Matching;

use App\Models\Booking;
use App\Models\ProviderProfile;
use App\Models\ServiceCatalog;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\User;
use App\Services\Matching\MatchingScoreEngine;
use App\Services\Matching\MatchingV2Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/** Feature tests for trade-aware matching in MatchingV2Service. Verifies that: 1. */
class TradeFilteredMatchingTest extends TestCase
{
    use RefreshDatabase;

    private ServiceZone $zone;

    private Trade $trade;

    private MatchingV2Service $service;

    private MatchingScoreEngine $scoreEngine;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('matching.thresholds.min_acceptable_score', 0);
        Config::set('matching.thresholds.fallback_if_no_match', true);
        Config::set('matching.weights', [
            'rating' => 25,
            'acceptance_rate' => 10,
            'completion_rate' => 10,
            'response_time' => 10,
            'zone_proximity' => 15,
            'workload' => 10,
            'client_affinity' => 5,
            'trade_specialty' => 10,
            'recency_balance' => 5,
        ]);

        $this->zone = ServiceZone::factory()->create();
        $this->trade = Trade::factory()->create(['billing_unit' => 'hourly', 'is_active' => true]);
        $this->service = app(MatchingV2Service::class);
        $this->scoreEngine = app(MatchingScoreEngine::class);
    }

    // ──────────────────────────────────────────────────────────────────
    // Test 1 — providers WITHOUT the required trade are excluded
    // ──────────────────────────────────────────────────────────────────

    public function test_provider_without_trade_is_excluded_from_candidates(): void
    {
        $providerWithTrade = $this->createProvider(rating: 4.0, ratingCount: 10);
        $providerWithoutTrade = $this->createProvider(rating: 4.9, ratingCount: 30);

        // Only providerWithTrade gets the required trade
        $providerWithTrade->trades()->attach($this->trade->id, [
            'is_primary' => true,
            'proficiency' => 'expert',
        ]);

        $booking = $this->createBookingForTrade($this->trade);

        $ranked = $this->service->rankCandidates($booking);

        $ids = $ranked->pluck('employee.id');

        $this->assertTrue($ids->contains($providerWithTrade->id), 'Provider with trade should be in candidates');
        $this->assertFalse($ids->contains($providerWithoutTrade->id), 'Provider without trade should be excluded');
    }

    // ──────────────────────────────────────────────────────────────────
    // Test 2 — trade_specialty score is higher for primary-trade provider
    // ──────────────────────────────────────────────────────────────────

    public function test_primary_trade_provider_scores_higher_in_trade_specialty(): void
    {
        $primaryProvider = $this->createProvider(rating: 4.0, ratingCount: 10);
        $nonPrimaryProvider = $this->createProvider(rating: 4.0, ratingCount: 10);

        $primaryProvider->trades()->attach($this->trade->id, [
            'is_primary' => true,
            'proficiency' => 'advanced',
        ]);

        $nonPrimaryProvider->trades()->attach($this->trade->id, [
            'is_primary' => false,
            'proficiency' => 'advanced',
        ]);

        $booking = $this->createBookingForTrade($this->trade);

        $primaryBreakdown = $this->scoreEngine->score($primaryProvider, $booking);
        $nonPrimaryBreakdown = $this->scoreEngine->score($nonPrimaryProvider, $booking);

        $primaryTradeScore = $primaryBreakdown->components['trade_specialty']['raw'] ?? 0;
        $nonPrimaryTradeScore = $nonPrimaryBreakdown->components['trade_specialty']['raw'] ?? 0;

        $this->assertGreaterThan(
            $nonPrimaryTradeScore,
            $primaryTradeScore,
            'Primary-trade provider should have a higher trade_specialty raw score'
        );
    }

    // ──────────────────────────────────────────────────────────────────
    // Test 3 — le repli ouvert est supprimé : personne, plutôt que n'importe qui
    // ──────────────────────────────────────────────────────────────────

    /** LE FILTRE MÉTIER N'A PLUS DE REPLI. */
    public function test_aucun_candidat_quand_personne_n_exerce_le_metier_requis(): void
    {
        $otherTrade = Trade::factory()->create(['billing_unit' => 'fixed', 'is_active' => true]);
        $provider = $this->createProvider(rating: 4.0, ratingCount: 5);
        $provider->trades()->attach($otherTrade->id, ['is_primary' => true, 'proficiency' => 'advanced']);

        $booking = $this->createBookingForTrade($this->trade);

        $ranked = $this->service->rankCandidates($booking);

        $this->assertTrue($ranked->isEmpty(), 'Le métier vit dans la requête : aucun repli ne le rouvre.');
        $this->assertFalse($ranked->pluck('employee.id')->contains($provider->id));
    }

    // ──────────────────────────────────────────────────────────────────
    // Test 4 — une réservation sans métier ne rend personne
    // ──────────────────────────────────────────────────────────────────

    /** SANS MÉTIER RÉSOLVABLE, ON NE REND PERSONNE. */
    public function test_une_reservation_sans_metier_ne_rend_personne(): void
    {
        $provider = $this->createProvider(rating: 4.5, ratingCount: 20);

        $booking = Booking::create([
            'client_id' => User::factory()->client()->create()->id,
            'service_zone_id' => $this->zone->id,
            'date' => now()->addDay(),
            'heure' => '10:00',
            'status' => 'en_attente',
            'devis_estime' => 80,
            'booking_mode' => 'scheduled',
            // Ni service au catalogue, ni `trade_id` : le métier est introuvable.
        ]);

        $ranked = $this->service->rankCandidates($booking);

        $this->assertTrue($ranked->isEmpty());
        $this->assertFalse($ranked->pluck('employee.id')->contains($provider->id));
    }

    // ──────────────────────────────────────────────────────────────────
    // Test 5 — trade_specialty score is 0 when provider lacks required trade
    // ──────────────────────────────────────────────────────────────────

    public function test_trade_specialty_score_is_zero_when_provider_lacks_required_trade(): void
    {
        $provider = $this->createProvider(rating: 3.5, ratingCount: 5);
        // No trade attached

        $booking = $this->createBookingForTrade($this->trade);

        $breakdown = $this->scoreEngine->score($provider, $booking);

        $this->assertSame(0.0, $breakdown->components['trade_specialty']['raw'] ?? -1.0);
    }

    // ──────────────────────────────────────────────────────────────────
    // Test 6 — expert proficiency outscores beginner in trade_specialty
    // ──────────────────────────────────────────────────────────────────

    public function test_expert_proficiency_scores_higher_than_beginner(): void
    {
        $expertProvider = $this->createProvider(rating: 4.0, ratingCount: 10);
        $beginnerProvider = $this->createProvider(rating: 4.0, ratingCount: 10);

        $expertProvider->trades()->attach($this->trade->id, [
            'is_primary' => false,
            'proficiency' => 'expert',
        ]);

        $beginnerProvider->trades()->attach($this->trade->id, [
            'is_primary' => false,
            'proficiency' => 'beginner',
        ]);

        $booking = $this->createBookingForTrade($this->trade);

        $expertScore = $this->scoreEngine->score($expertProvider, $booking)->components['trade_specialty']['raw'];
        $beginnerScore = $this->scoreEngine->score($beginnerProvider, $booking)->components['trade_specialty']['raw'];

        $this->assertGreaterThan($beginnerScore, $expertScore);
    }

    // ──────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────

    private function createProvider(float $rating, int $ratingCount): User
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
            'is_online' => true,
        ]);

        $user->serviceZones()->attach($this->zone->id, [
            'assignment_type' => 'primary',
            'is_active' => true,
        ]);

        return $user;
    }

    private function createBookingForTrade(Trade $trade): Booking
    {
        $service = ServiceCatalog::factory()->create([
            'trade_id' => $trade->id,
            'is_active' => true,
        ]);

        return Booking::create([
            'client_id' => User::factory()->client()->create()->id,
            'service_zone_id' => $this->zone->id,
            'service_catalog_id' => $service->id,
            'date' => now()->addDay(),
            'heure' => '10:00',
            'status' => 'en_attente',
            'devis_estime' => 100,
            'booking_mode' => 'scheduled',
        ]);
    }
}
