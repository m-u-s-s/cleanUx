<?php

namespace Tests\Feature\Rating;

use App\Livewire\Admin\Ratings\RatingModerationCenter;
use App\Models\Booking;
use App\Models\Feedback;
use App\Models\ProviderProfile;
use App\Models\RatingReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RatingModerationCenterCoverageBatch10Test extends TestCase
{
    use RefreshDatabase;

    protected User $client;

    protected User $provider;

    protected User $admin;

    protected Feedback $publishedFeedback;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::factory()->client()->create(['name' => 'Alice Client']);
        $this->provider = User::factory()->create(['role' => 'employe', 'name' => 'Bob Provider']);
        $this->admin = User::factory()->admin()->create();

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
            'comment' => 'Excellent travail soigné',
            'commentaire' => 'Excellent travail soigné',
            'status' => Feedback::STATUS_PUBLISHED,
            'is_public' => true,
            'is_hidden' => false,
            'published_at' => now(),
        ]);
    }

    public function test_pending_reports_tab_renders_with_kpis(): void
    {
        RatingReport::factory()->create([
            'feedback_id' => $this->publishedFeedback->id,
            'reporter_user_id' => $this->client->id,
            'status' => RatingReport::STATUS_PENDING,
        ]);

        Livewire::actingAs($this->admin)
            ->test(RatingModerationCenter::class)
            ->assertOk()
            ->assertSet('tab', 'pending_reports')
            ->assertViewHas('kpis')
            ->assertViewHas('currentView', 'pending');
    }

    public function test_hidden_tab_renders(): void
    {
        $this->publishedFeedback->update([
            'is_hidden' => true,
            'hidden_reason' => 'auto_hidden_after_3_reports',
            'hidden_at' => now(),
            'status' => Feedback::STATUS_HIDDEN,
        ]);

        Livewire::actingAs($this->admin)
            ->test(RatingModerationCenter::class)
            ->set('tab', 'hidden')
            ->assertOk()
            ->assertViewHas('currentView', 'hidden');
    }

    public function test_all_tab_with_search_renders(): void
    {
        Livewire::actingAs($this->admin)
            ->test(RatingModerationCenter::class)
            ->set('tab', 'all')
            ->set('search', 'Excellent')
            ->assertOk()
            ->assertViewHas('currentView', 'all');
    }

    public function test_all_tab_search_matches_provider_name(): void
    {
        Livewire::actingAs($this->admin)
            ->test(RatingModerationCenter::class)
            ->set('tab', 'all')
            ->set('search', 'Bob')
            ->assertOk()
            ->assertViewHas('currentView', 'all');
    }

    public function test_hide_action_hides_feedback_and_resolves_reports(): void
    {
        RatingReport::factory()->create([
            'feedback_id' => $this->publishedFeedback->id,
            'reporter_user_id' => $this->client->id,
            'status' => RatingReport::STATUS_PENDING,
        ]);

        Livewire::actingAs($this->admin)
            ->test(RatingModerationCenter::class)
            ->call('hide', $this->publishedFeedback->id)
            ->assertDispatched('toast');

        $this->assertTrue((bool) $this->publishedFeedback->fresh()->is_hidden);
        $this->assertSame(1, RatingReport::query()
            ->where('feedback_id', $this->publishedFeedback->id)
            ->where('status', RatingReport::STATUS_REVIEWED_HIDDEN)
            ->count());
    }

    public function test_keep_action_keeps_feedback_and_resolves_reports(): void
    {
        RatingReport::factory()->create([
            'feedback_id' => $this->publishedFeedback->id,
            'reporter_user_id' => $this->client->id,
            'status' => RatingReport::STATUS_PENDING,
        ]);

        Livewire::actingAs($this->admin)
            ->test(RatingModerationCenter::class)
            ->call('keep', $this->publishedFeedback->id)
            ->assertDispatched('toast');

        $this->assertFalse((bool) $this->publishedFeedback->fresh()->is_hidden);
        $this->assertSame(1, RatingReport::query()
            ->where('feedback_id', $this->publishedFeedback->id)
            ->where('status', RatingReport::STATUS_REVIEWED_KEPT)
            ->count());
    }

    public function test_restore_action_unhides_feedback(): void
    {
        $this->publishedFeedback->update([
            'is_hidden' => true,
            'hidden_reason' => 'admin_hidden_after_review',
            'hidden_at' => now(),
            'status' => Feedback::STATUS_HIDDEN,
        ]);

        Livewire::actingAs($this->admin)
            ->test(RatingModerationCenter::class)
            ->call('restore', $this->publishedFeedback->id)
            ->assertDispatched('toast');

        $this->assertFalse((bool) $this->publishedFeedback->fresh()->is_hidden);
    }

    public function test_dismiss_report_marks_it_dismissed(): void
    {
        $report = RatingReport::factory()->create([
            'feedback_id' => $this->publishedFeedback->id,
            'reporter_user_id' => $this->client->id,
            'status' => RatingReport::STATUS_PENDING,
        ]);

        Livewire::actingAs($this->admin)
            ->test(RatingModerationCenter::class)
            ->call('dismissReport', $report->id)
            ->assertDispatched('toast');

        $fresh = $report->fresh();
        $this->assertSame(RatingReport::STATUS_DISMISSED, $fresh->status);
        $this->assertSame($this->admin->id, (int) $fresh->reviewed_by_user_id);
        $this->assertNotNull($fresh->reviewed_at);
    }
}
