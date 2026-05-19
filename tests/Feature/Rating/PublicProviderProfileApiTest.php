<?php

namespace Tests\Feature\Rating;

use App\Models\Booking;
use App\Models\Feedback;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Rating\RatingAggregationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicProviderProfileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_endpoint_returns_provider_profile_with_aggregates(): void
    {
        $provider = User::factory()->create(['role' => 'employe', 'name' => 'Marc Provider']);
        ProviderProfile::create([
            'user_id' => $provider->id,
            'provider_type' => 'independent',
            'status' => 'active',
            'verification_status' => 'verified',
            'bio' => 'Pro de la peinture',
        ]);

        $client = User::factory()->client()->create();
        $this->createPublishedFeedback($client, $provider, 5, 'Top !');
        $this->createPublishedFeedback($client, $provider, 4, 'Très bien');

        app(RatingAggregationService::class)->recalculateForProvider($provider->id);

        $response = $this->getJson('/api/providers/' . $provider->id);

        $response->assertOk();
        $response->assertJsonPath('id', $provider->id);
        $response->assertJsonPath('name', 'Marc Provider');
        $response->assertJsonPath('rating.count', 2);
        $this->assertEqualsWithDelta(4.5, $response->json('rating.avg'), 0.01);
    }

    public function test_public_endpoint_returns_404_for_unverified_provider(): void
    {
        $provider = User::factory()->create(['role' => 'employe']);
        ProviderProfile::create([
            'user_id' => $provider->id,
            'provider_type' => 'independent',
            'status' => 'pending',
            'verification_status' => 'unverified',
        ]);

        $this->getJson('/api/providers/' . $provider->id)->assertStatus(404);
    }

    public function test_public_ratings_endpoint_returns_only_public_visible(): void
    {
        $provider = User::factory()->create(['role' => 'employe']);
        ProviderProfile::create([
            'user_id' => $provider->id,
            'provider_type' => 'independent',
            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        $client = User::factory()->client()->create();
        $this->createPublishedFeedback($client, $provider, 5);
        $this->createPublishedFeedback($client, $provider, 1);

        // Hidden rating
        $this->createPublishedFeedback($client, $provider, 3, null, hidden: true);

        $response = $this->getJson('/api/providers/' . $provider->id . '/ratings?sort=highest');
        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertSame(5, $data[0]['rating']);
        $this->assertSame(1, $data[1]['rating']);
    }

    public function test_min_rating_filter_applies(): void
    {
        $provider = User::factory()->create(['role' => 'employe']);
        ProviderProfile::create([
            'user_id' => $provider->id,
            'provider_type' => 'independent',
            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        $client = User::factory()->client()->create();
        $this->createPublishedFeedback($client, $provider, 5);
        $this->createPublishedFeedback($client, $provider, 2);

        $response = $this->getJson('/api/providers/' . $provider->id . '/ratings?min_rating=4');
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame(5, $data[0]['rating']);
    }

    protected function createPublishedFeedback(
        User $client,
        User $provider,
        int $rating,
        ?string $comment = null,
        bool $hidden = false,
    ): Feedback {
        $booking = Booking::create([
            'client_id' => $client->id,
            'employe_id' => $provider->id,
            'date' => now()->subDay(),
            'heure' => '10:00',
            'status' => 'termine',
            'mission_finished_at' => now()->subDay(),
            'devis_estime' => 100,
        ]);

        return Feedback::create([
            'booking_id' => $booking->id,
            'rendez_vous_id' => $booking->id,
            'client_id' => $client->id,
            'employe_id' => $provider->id,
            'direction' => Feedback::DIRECTION_CLIENT_TO_PROVIDER,
            'rating' => $rating,
            'note' => $rating,
            'comment' => $comment,
            'status' => Feedback::STATUS_PUBLISHED,
            'is_public' => true,
            'is_hidden' => $hidden,
            'published_at' => now(),
        ]);
    }
}
