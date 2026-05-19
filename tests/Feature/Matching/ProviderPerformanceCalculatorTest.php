<?php

namespace Tests\Feature\Matching;

use App\Models\Booking;
use App\Models\Feedback;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Matching\ProviderPerformanceCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderPerformanceCalculatorTest extends TestCase
{
    use RefreshDatabase;

    protected User $provider;
    protected ProviderPerformanceCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = User::factory()->create(['role' => 'employe']);
        ProviderProfile::create([
            'user_id' => $this->provider->id,
            'provider_type' => 'independent',
            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        $this->calc = app(ProviderPerformanceCalculator::class);
    }

    public function test_calculates_acceptance_rate_from_assignments(): void
    {
        $mission = $this->createMission();

        MissionAssignment::create([
            'mission_id' => $mission->id,
            'user_id' => $this->provider->id,
            'assigned_at' => now()->subDays(5),
            'accepted_at' => now()->subDays(5),
            'response_seconds' => 45,
            'status' => 'accepted',
        ]);

        MissionAssignment::create([
            'mission_id' => $this->createMission()->id,
            'user_id' => $this->provider->id,
            'assigned_at' => now()->subDays(3),
            'declined_at' => now()->subDays(3),
            'response_seconds' => 120,
            'status' => 'declined',
        ]);

        $metric = $this->calc->calculate($this->provider, 30);

        $this->assertSame(2, $metric->offers_received);
        $this->assertSame(1, $metric->offers_accepted);
        $this->assertSame(1, $metric->offers_declined);
        $this->assertEqualsWithDelta(0.5, (float) $metric->acceptance_rate, 0.01);
        $this->assertEqualsWithDelta(82.5, (float) $metric->avg_response_seconds, 0.5);
    }

    public function test_rating_window_only_counts_published_feedbacks(): void
    {
        $client = User::factory()->client()->create();

        Feedback::create([
            'client_id' => $client->id,
            'employe_id' => $this->provider->id,
            'direction' => Feedback::DIRECTION_CLIENT_TO_PROVIDER,
            'rating' => 5,
            'note' => 5,
            'status' => Feedback::STATUS_PUBLISHED,
            'is_public' => true,
            'is_hidden' => false,
            'published_at' => now()->subDays(2),
        ]);

        Feedback::create([
            'client_id' => $client->id,
            'employe_id' => $this->provider->id,
            'direction' => Feedback::DIRECTION_CLIENT_TO_PROVIDER,
            'rating' => 3,
            'note' => 3,
            'status' => Feedback::STATUS_PUBLISHED,
            'is_public' => true,
            'is_hidden' => true,
            'published_at' => now()->subDays(1),
        ]);

        $metric = $this->calc->calculate($this->provider, 30);

        $this->assertSame(1, $metric->rating_count_window);
        $this->assertEqualsWithDelta(5.0, (float) $metric->rating_avg_window, 0.01);
    }

    public function test_upsert_replaces_existing_period_end_row(): void
    {
        $first = $this->calc->calculate($this->provider, 30);
        $second = $this->calc->calculate($this->provider, 30);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, \App\Models\ProviderPerformanceMetric::query()
            ->where('user_id', $this->provider->id)
            ->count());
    }

    protected function createMission(): Mission
    {
        // Booking assigné à un autre employé pour éviter la création
        // automatique de MissionAssignment ciblant $this->provider via
        // le pipeline RendezVousObserver → MissionLifecycleService.
        $otherProvider = User::factory()->create(['role' => 'employe']);

        $booking = Booking::create([
            'client_id' => User::factory()->client()->create()->id,
            'employe_id' => $otherProvider->id,
            'date' => now()->addDay(),
            'heure' => '10:00',
            'status' => 'en_attente',
            'devis_estime' => 100,
        ]);

        return Mission::create([
            'booking_id' => $booking->id,
            'rendez_vous_id' => $booking->id,
            'status' => 'assigned',
        ]);
    }
}
