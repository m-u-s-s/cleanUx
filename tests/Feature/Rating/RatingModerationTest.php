<?php

namespace Tests\Feature\Rating;

use App\Models\Booking;
use App\Models\Feedback;
use App\Models\ProviderProfile;
use App\Models\RatingReport;
use App\Models\User;
use App\Services\Rating\RatingModerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RatingModerationTest extends TestCase
{
    use RefreshDatabase;

    protected User $client;
    protected User $provider;
    protected User $reporter;
    protected Feedback $publishedFeedback;
    protected RatingModerationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::factory()->client()->create();
        $this->provider = User::factory()->create(['role' => 'employe']);
        $this->reporter = User::factory()->client()->create();

        ProviderProfile::create([
            'user_id' => $this->provider->id,
            'provider_type' => 'independent',
            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        $booking = Booking::create([
            'client_id' => $this->client->id,
            'employe_id' => $this->provider->id,
            'date' => now()->subDay(),
            'heure' => '10:00',
            'status' => 'termine',
            'mission_finished_at' => now()->subDay(),
            'devis_estime' => 100,
        ]);

        $this->publishedFeedback = Feedback::create([
            'booking_id' => $booking->id,
            'rendez_vous_id' => $booking->id,
            'client_id' => $this->client->id,
            'employe_id' => $this->provider->id,
            'direction' => Feedback::DIRECTION_CLIENT_TO_PROVIDER,
            'rating' => 5,
            'note' => 5,
            'comment' => 'Excellent',
            'status' => Feedback::STATUS_PUBLISHED,
            'is_public' => true,
            'is_hidden' => false,
            'published_at' => now(),
        ]);

        $this->service = app(RatingModerationService::class);
    }

    public function test_report_increments_reports_count(): void
    {
        $report = $this->service->report($this->publishedFeedback, $this->reporter, RatingReport::REASON_SPAM, 'Test spam');

        $this->assertNotNull($report);
        $this->assertSame(RatingReport::STATUS_PENDING, $report->status);
        $this->assertSame(1, (int) $this->publishedFeedback->fresh()->reports_count);
    }

    public function test_same_user_cannot_report_twice(): void
    {
        $a = $this->service->report($this->publishedFeedback, $this->reporter, RatingReport::REASON_SPAM);
        $b = $this->service->report($this->publishedFeedback, $this->reporter, RatingReport::REASON_FAKE);

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, RatingReport::query()->count());
    }

    public function test_auto_hide_after_threshold_reports(): void
    {
        $reporters = User::factory()->client()->count(RatingModerationService::AUTO_HIDE_REPORTS_THRESHOLD)->create();

        foreach ($reporters as $r) {
            $this->service->report($this->publishedFeedback, $r, RatingReport::REASON_OFFENSIVE);
        }

        $fresh = $this->publishedFeedback->fresh();
        $this->assertTrue((bool) $fresh->is_hidden);
        $this->assertStringStartsWith('auto_hidden_', (string) $fresh->hidden_reason);
    }

    public function test_admin_can_hide_and_restore(): void
    {
        $admin = User::factory()->admin()->create();

        $this->service->hide($this->publishedFeedback, $admin, 'admin_decision');
        $this->assertTrue($this->publishedFeedback->fresh()->is_hidden);

        $this->service->restore($this->publishedFeedback, $admin);
        $this->assertFalse($this->publishedFeedback->fresh()->is_hidden);
    }

    public function test_hidden_rating_does_not_count_in_aggregates(): void
    {
        // First aggregate it
        app(\App\Services\Rating\RatingAggregationService::class)
            ->recalculateForProvider($this->provider->id);

        $profile = ProviderProfile::query()->where('user_id', $this->provider->id)->first();
        $this->assertSame(1, (int) $profile->rating_count);

        // Now hide it
        $this->service->hide($this->publishedFeedback, null, 'test');

        $profile->refresh();
        $this->assertSame(0, (int) $profile->rating_count);
        $this->assertNull($profile->rating_avg);
    }

    public function test_resolve_reports_updates_all_pending(): void
    {
        $admin = User::factory()->admin()->create();
        $reporters = User::factory()->client()->count(2)->create();

        foreach ($reporters as $r) {
            $this->service->report($this->publishedFeedback, $r, RatingReport::REASON_SPAM);
        }

        $affected = $this->service->resolveReports($this->publishedFeedback, $admin, keep: true);
        $this->assertSame(2, $affected);

        $this->assertSame(2, RatingReport::query()
            ->where('feedback_id', $this->publishedFeedback->id)
            ->where('status', RatingReport::STATUS_REVIEWED_KEPT)
            ->count());
    }
}
