<?php

namespace Tests\Feature\Analytics;

use App\Models\Booking;
use App\Models\CustomerClaim;
use App\Models\Feedback;
use App\Models\Mission;
use App\Models\User;
use App\Services\Analytics\OperationalQualityService;
use App\Support\Domain\BookingStatus;
use App\Support\Domain\MissionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalQualityServiceCoverageBatch10Test extends TestCase
{
    use RefreshDatabase;

    public function test_metrics_returns_zeroed_structure_when_no_data(): void
    {
        $metrics = app(OperationalQualityService::class)->metrics();

        $this->assertSame(0, $metrics['missions_total']);
        $this->assertSame(0, $metrics['missions_completed']);
        $this->assertSame(0.0, $metrics['punctuality_rate']);
        $this->assertSame(0, $metrics['bookings_total']);
        $this->assertSame(0, $metrics['bookings_cancelled']);
        $this->assertSame(0.0, $metrics['no_show_rate']);
        $this->assertSame(0, $metrics['replanning_count']);
        $this->assertSame(0.0, $metrics['replanning_rate']);
        $this->assertSame(0, $metrics['claims_total']);
        $this->assertSame(0, $metrics['claims_open']);
        $this->assertSame(0, $metrics['claims_resolved']);
        $this->assertSame(0.0, $metrics['avg_claim_resolution_hours']);
        $this->assertSame(0, $metrics['feedback_total']);
        $this->assertSame(0.0, $metrics['avg_rating']);
        $this->assertSame(0.0, $metrics['csat_rate']);
        $this->assertSame(0.0, $metrics['quality_score_avg']);
    }

    public function test_metrics_aggregates_missions_punctuality_and_quality(): void
    {
        // On-time completed mission (actual within 10 min of planned).
        Mission::factory()->create([
            'status' => MissionStatus::COMPLETED,
            'planned_start_at' => now(),
            'actual_start_at' => now()->addMinutes(5),
            'quality_score' => 8,
        ]);

        // Late completed mission (actual beyond 10 min window).
        Mission::factory()->create([
            'status' => MissionStatus::COMPLETED,
            'planned_start_at' => now(),
            'actual_start_at' => now()->addMinutes(45),
            'quality_score' => 6,
        ]);

        // Completed mission with no actual_start_at -> not on time.
        Mission::factory()->create([
            'status' => MissionStatus::COMPLETED,
            'planned_start_at' => now(),
            'actual_start_at' => null,
            'quality_score' => 10,
        ]);

        // Cancelled mission contributes to no-show rate.
        Mission::factory()->create([
            'status' => MissionStatus::CANCELLED,
        ]);

        $metrics = app(OperationalQualityService::class)->metrics();

        $this->assertSame(4, $metrics['missions_total']);
        $this->assertSame(3, $metrics['missions_completed']);
        // 1 of 3 completed missions on time.
        $this->assertSame(33.3, $metrics['punctuality_rate']);
        // 1 cancelled of 4 missions.
        $this->assertSame(25.0, $metrics['no_show_rate']);
        // avg of 8, 6, 10 = 8.0.
        $this->assertSame(8.0, $metrics['quality_score_avg']);
    }

    public function test_metrics_counts_cancelled_and_replanned_bookings(): void
    {
        // A refused booking (counts as cancelled).
        Booking::factory()->create([
            'status' => BookingStatus::REFUSE,
        ]);

        // A replanned booking: no 24h reminder, updated long after creation.
        $replanned = Booking::factory()->create([
            'status' => BookingStatus::CONFIRME,
            'rappel_24h_envoye_at' => null,
        ]);
        Booking::query()->whereKey($replanned->id)->update([
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay()->addMinutes(30),
        ]);

        // A non-replanned booking (updated immediately after creation).
        $fresh = Booking::factory()->create([
            'status' => BookingStatus::CONFIRME,
            'rappel_24h_envoye_at' => null,
        ]);
        Booking::query()->whereKey($fresh->id)->update([
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay()->addMinutes(1),
        ]);

        $metrics = app(OperationalQualityService::class)->metrics();

        $this->assertSame(3, $metrics['bookings_total']);
        $this->assertSame(1, $metrics['bookings_cancelled']);
        $this->assertSame(1, $metrics['replanning_count']);
        $this->assertGreaterThan(0.0, $metrics['replanning_rate']);
    }

    public function test_metrics_aggregates_claims_resolution_and_feedback(): void
    {
        $client = User::factory()->client()->create();

        // Open claim.
        CustomerClaim::query()->create([
            'client_id' => $client->id,
            'category' => 'quality',
            'priority' => 'normal',
            'status' => 'open',
            'title' => 'Open claim',
            'description' => 'Still open',
            'resolved_at' => null,
        ]);

        // Resolved claim 5 hours after creation.
        $resolved = CustomerClaim::query()->create([
            'client_id' => $client->id,
            'category' => 'delay',
            'priority' => 'high',
            'status' => 'resolved',
            'title' => 'Resolved claim',
            'description' => 'Done',
            'resolved_at' => now(),
        ]);
        CustomerClaim::query()->whereKey($resolved->id)->update([
            'created_at' => now()->subHours(5),
        ]);

        // Feedback: high + low ratings to drive csat + avg.
        Feedback::factory()->highRating()->create(['note' => 5]);
        Feedback::factory()->lowRating()->create(['note' => 2]);

        $metrics = app(OperationalQualityService::class)->metrics();

        $this->assertSame(2, $metrics['claims_total']);
        $this->assertSame(1, $metrics['claims_open']);
        $this->assertSame(1, $metrics['claims_resolved']);
        $this->assertSame(5.0, $metrics['avg_claim_resolution_hours']);

        $this->assertSame(2, $metrics['feedback_total']);
        $this->assertSame(3.5, $metrics['avg_rating']);
        $this->assertSame(50.0, $metrics['csat_rate']);
    }

    public function test_metrics_applies_date_range_filters(): void
    {
        Mission::factory()->create([
            'status' => MissionStatus::COMPLETED,
            'planned_start_at' => '2030-01-15 09:00:00',
            'actual_start_at' => '2030-01-15 09:05:00',
        ]);

        $inRange = app(OperationalQualityService::class)->metrics('2030-01-01', '2030-01-31');
        $this->assertGreaterThanOrEqual(1, $inRange['missions_total']);

        $outOfRange = app(OperationalQualityService::class)->metrics('2020-01-01', '2020-12-31');
        $this->assertSame(0, $outOfRange['missions_total']);
    }
}
