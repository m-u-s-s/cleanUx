<?php

namespace Tests\Feature\Rating;

use App\Livewire\Provider\ProviderRatingsPage;
use App\Models\Booking;
use App\Models\Feedback;
use App\Models\ProviderProfile;
use App\Models\RatingReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProviderRatingsPageCoverageBatch14Test extends TestCase
{
    use RefreshDatabase;

    protected User $client;

    protected User $provider;

    protected Feedback $feedback;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::factory()->client()->create(['name' => 'Alice Client']);
        $this->provider = User::factory()->create(['role' => 'employe', 'name' => 'Bob Provider']);

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

        $this->feedback = Feedback::create([
            'booking_id' => $booking->id,
            'rendez_vous_id' => $booking->id,
            'client_id' => $this->client->id,
            'employe_id' => $this->provider->id,
            'direction' => Feedback::DIRECTION_CLIENT_TO_PROVIDER,
            'rating' => 2,
            'note' => 2,
            'comment' => 'Peut mieux faire',
            'commentaire' => 'Peut mieux faire',
            'status' => Feedback::STATUS_PUBLISHED,
            'is_public' => true,
            'is_hidden' => false,
            'published_at' => now(),
        ]);
    }

    public function test_default_render_lists_provider_ratings(): void
    {
        Livewire::actingAs($this->provider)
            ->test(ProviderRatingsPage::class)
            ->assertOk()
            ->assertSet('filter', 'all')
            ->assertViewHas('ratings')
            ->assertViewHas('profile');
    }

    public function test_pending_response_filter_renders(): void
    {
        Livewire::actingAs($this->provider)
            ->test(ProviderRatingsPage::class)
            ->set('filter', 'pending_response')
            ->assertOk()
            ->assertViewHas('ratings');
    }

    public function test_hidden_filter_renders(): void
    {
        $this->feedback->update([
            'is_hidden' => true,
            'hidden_reason' => 'auto_hidden_after_3_reports',
            'hidden_at' => now(),
            'status' => Feedback::STATUS_HIDDEN,
        ]);

        Livewire::actingAs($this->provider)
            ->test(ProviderRatingsPage::class)
            ->set('filter', 'hidden')
            ->assertOk()
            ->assertViewHas('ratings');
    }

    public function test_low_filter_renders(): void
    {
        Livewire::actingAs($this->provider)
            ->test(ProviderRatingsPage::class)
            ->set('filter', 'low')
            ->assertOk()
            ->assertViewHas('ratings');
    }

    public function test_start_reply_loads_existing_response(): void
    {
        $this->feedback->update(['provider_response' => 'Merci beaucoup']);

        Livewire::actingAs($this->provider)
            ->test(ProviderRatingsPage::class)
            ->call('startReply', $this->feedback->id)
            ->assertSet('replyingTo', $this->feedback->id)
            ->assertSet('responseText', 'Merci beaucoup');
    }

    public function test_cancel_reply_resets_state(): void
    {
        Livewire::actingAs($this->provider)
            ->test(ProviderRatingsPage::class)
            ->set('replyingTo', $this->feedback->id)
            ->set('responseText', 'draft')
            ->call('cancelReply')
            ->assertSet('replyingTo', null)
            ->assertSet('responseText', '');
    }

    public function test_submit_reply_publishes_response(): void
    {
        Livewire::actingAs($this->provider)
            ->test(ProviderRatingsPage::class)
            ->set('replyingTo', $this->feedback->id)
            ->set('responseText', 'Nous prenons note de votre retour.')
            ->call('submitReply')
            ->assertDispatched('toast')
            ->assertSet('replyingTo', null)
            ->assertSet('responseText', '');

        $fresh = $this->feedback->fresh();
        $this->assertSame('Nous prenons note de votre retour.', $fresh->provider_response);
        $this->assertNotNull($fresh->provider_responded_at);
    }

    public function test_submit_reply_validation_fails_on_empty_text(): void
    {
        Livewire::actingAs($this->provider)
            ->test(ProviderRatingsPage::class)
            ->set('replyingTo', $this->feedback->id)
            ->set('responseText', '')
            ->call('submitReply')
            ->assertHasErrors('responseText');
    }

    public function test_submit_reply_surfaces_service_validation_error(): void
    {
        $providerToClient = Feedback::create([
            'booking_id' => $this->feedback->booking_id,
            'rendez_vous_id' => $this->feedback->rendez_vous_id,
            'client_id' => $this->client->id,
            'employe_id' => $this->provider->id,
            'direction' => Feedback::DIRECTION_PROVIDER_TO_CLIENT,
            'rating' => 5,
            'note' => 5,
            'comment' => 'Client agréable',
            'status' => Feedback::STATUS_PUBLISHED,
            'is_public' => true,
            'is_hidden' => false,
            'published_at' => now(),
        ]);

        Livewire::actingAs($this->provider)
            ->test(ProviderRatingsPage::class)
            ->set('replyingTo', $providerToClient->id)
            ->set('responseText', 'Bonjour')
            ->call('submitReply')
            ->assertHasErrors('response');
    }

    public function test_start_report_initialises_form(): void
    {
        Livewire::actingAs($this->provider)
            ->test(ProviderRatingsPage::class)
            ->set('reportReason', RatingReport::REASON_SPAM)
            ->set('reportDetails', 'old')
            ->call('startReport', $this->feedback->id)
            ->assertSet('reportingId', $this->feedback->id)
            ->assertSet('reportReason', RatingReport::REASON_OTHER)
            ->assertSet('reportDetails', '');
    }

    public function test_cancel_report_resets_state(): void
    {
        Livewire::actingAs($this->provider)
            ->test(ProviderRatingsPage::class)
            ->set('reportingId', $this->feedback->id)
            ->set('reportDetails', 'draft')
            ->call('cancelReport')
            ->assertSet('reportingId', null)
            ->assertSet('reportDetails', '');
    }

    public function test_submit_report_creates_report(): void
    {
        Livewire::actingAs($this->provider)
            ->test(ProviderRatingsPage::class)
            ->set('reportingId', $this->feedback->id)
            ->set('reportReason', RatingReport::REASON_FAKE)
            ->set('reportDetails', 'Avis manifestement faux')
            ->call('submitReport')
            ->assertDispatched('toast')
            ->assertSet('reportingId', null);

        $this->assertSame(1, RatingReport::query()
            ->where('feedback_id', $this->feedback->id)
            ->where('reporter_user_id', $this->provider->id)
            ->where('reason', RatingReport::REASON_FAKE)
            ->count());
    }

    public function test_submit_report_surfaces_service_validation_error(): void
    {
        Livewire::actingAs($this->provider)
            ->test(ProviderRatingsPage::class)
            ->set('reportingId', $this->feedback->id)
            ->set('reportReason', 'not_a_real_reason')
            ->set('reportDetails', '')
            ->call('submitReport')
            ->assertHasErrors('reason');

        $this->assertSame(0, RatingReport::query()->count());
    }
}
