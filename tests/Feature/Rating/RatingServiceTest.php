<?php

namespace Tests\Feature\Rating;

use App\Models\Booking;
use App\Models\Feedback;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Rating\RatingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RatingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected User $client;
    protected User $provider;
    protected Booking $booking;
    protected RatingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::factory()->client()->create();
        $this->provider = User::factory()->create(['role' => 'employe']);

        ProviderProfile::create([
            'user_id' => $this->provider->id,
            'provider_type' => 'independent',
            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        $this->booking = Booking::create([
            'client_id' => $this->client->id,
            'employe_id' => $this->provider->id,
            'date' => now()->subDay(),
            'heure' => '10:00',
            'status' => 'termine',
            'mission_finished_at' => now()->subDay(),
            'devis_estime' => 100,
        ]);

        $this->service = app(RatingService::class);
    }

    public function test_client_can_rate_provider_after_completed_booking(): void
    {
        $feedback = $this->service->submit($this->booking, $this->client, Feedback::DIRECTION_CLIENT_TO_PROVIDER, [
            'rating' => 5,
            'comment' => 'Très bon prestataire',
            'punctuality' => 5,
            'quality' => 4,
        ]);

        $this->assertNotNull($feedback);
        $this->assertSame(5, (int) $feedback->rating);
        $this->assertSame(Feedback::DIRECTION_CLIENT_TO_PROVIDER, $feedback->direction);
        $this->assertSame(Feedback::STATUS_PENDING, $feedback->status);
        $this->assertNull($feedback->published_at);
    }

    public function test_rating_publishes_when_both_parties_rated(): void
    {
        $this->service->submit($this->booking, $this->client, Feedback::DIRECTION_CLIENT_TO_PROVIDER, [
            'rating' => 5,
        ]);

        $providerFeedback = $this->service->submit($this->booking, $this->provider, Feedback::DIRECTION_PROVIDER_TO_CLIENT, [
            'rating' => 4,
        ]);

        $clientFeedback = Feedback::query()
            ->where('booking_id', $this->booking->id)
            ->where('direction', Feedback::DIRECTION_CLIENT_TO_PROVIDER)
            ->first();

        $this->assertSame(Feedback::STATUS_PUBLISHED, $clientFeedback->status);
        $this->assertNotNull($clientFeedback->published_at);
        $this->assertSame(Feedback::STATUS_PUBLISHED, $providerFeedback->fresh()->status);
    }

    public function test_provider_profile_aggregates_updated_on_publish(): void
    {
        $this->service->submit($this->booking, $this->client, Feedback::DIRECTION_CLIENT_TO_PROVIDER, [
            'rating' => 4,
        ]);
        $this->service->submit($this->booking, $this->provider, Feedback::DIRECTION_PROVIDER_TO_CLIENT, [
            'rating' => 5,
        ]);

        $profile = ProviderProfile::query()->where('user_id', $this->provider->id)->first();

        $this->assertEqualsWithDelta(4.0, (float) $profile->rating_avg, 0.01);
        $this->assertSame(1, (int) $profile->rating_count);
        $this->assertSame(['1' => 0, '2' => 0, '3' => 0, '4' => 1, '5' => 0], $profile->rating_distribution);
    }

    public function test_cannot_rate_uncompleted_booking(): void
    {
        $this->booking->update(['status' => 'confirme', 'mission_finished_at' => null]);

        $this->expectException(ValidationException::class);

        $this->service->submit($this->booking, $this->client, Feedback::DIRECTION_CLIENT_TO_PROVIDER, [
            'rating' => 5,
        ]);
    }

    public function test_cannot_rate_outside_window(): void
    {
        $this->booking->update([
            'mission_finished_at' => now()->subDays(RatingService::RATING_WINDOW_DAYS + 1),
        ]);

        $this->expectException(ValidationException::class);

        $this->service->submit($this->booking, $this->client, Feedback::DIRECTION_CLIENT_TO_PROVIDER, [
            'rating' => 5,
        ]);
    }

    public function test_wrong_user_cannot_rate(): void
    {
        $stranger = User::factory()->client()->create();

        $this->expectException(ValidationException::class);

        $this->service->submit($this->booking, $stranger, Feedback::DIRECTION_CLIENT_TO_PROVIDER, [
            'rating' => 5,
        ]);
    }

    public function test_publish_expired_pending_publishes_old_ones(): void
    {
        $feedback = $this->service->submit($this->booking, $this->client, Feedback::DIRECTION_CLIENT_TO_PROVIDER, [
            'rating' => 5,
        ]);

        $feedback->update(['answered_at' => now()->subDays(RatingService::RATING_WINDOW_DAYS + 1)]);

        $count = $this->service->publishExpiredPending();
        $this->assertSame(1, $count);
        $this->assertSame(Feedback::STATUS_PUBLISHED, $feedback->fresh()->status);
    }

    public function test_rating_is_idempotent_when_re_submitted_within_edit_window(): void
    {
        $first = $this->service->submit($this->booking, $this->client, Feedback::DIRECTION_CLIENT_TO_PROVIDER, [
            'rating' => 3,
        ]);

        $second = $this->service->submit($this->booking, $this->client, Feedback::DIRECTION_CLIENT_TO_PROVIDER, [
            'rating' => 5,
        ]);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(5, (int) $second->rating);
        $this->assertSame(1, Feedback::query()
            ->where('booking_id', $this->booking->id)
            ->where('direction', Feedback::DIRECTION_CLIENT_TO_PROVIDER)
            ->count());
    }
}
