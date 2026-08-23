<?php

namespace Tests\Feature\Api\Provider;

use App\Models\Booking;
use App\Models\Feedback;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Coverage batch 13 — drives App\Http\Controllers\Api\Provider\ProviderRatingController through its HTTP routes: - POST /api/provider/bookings/{booking}/rating (submit: 201, validation 422) - POST /api/provider/ratings/{feedback}/respond (respond: 200) - GET /api/provider/ratings/me (mine: 200 with mapping + limit) */
class ProviderRatingControllerCoverageBatch13Test extends TestCase
{
    use RefreshDatabase;

    private User $client;

    private User $provider;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::factory()->client()->create();
        $this->provider = User::factory()->employe()->create();

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
    }

    public function test_submit_records_provider_to_client_rating(): void
    {
        Sanctum::actingAs($this->provider);

        $response = $this->postJson("/api/provider/bookings/{$this->booking->id}/rating", [
            'rating' => 4,
            'comment' => 'Client courtois et ponctuel',
            'is_public' => true,
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['feedback_id', 'status', 'published_at']);

        $this->assertDatabaseHas('feedback', [
            'booking_id' => $this->booking->id,
            'direction' => Feedback::DIRECTION_PROVIDER_TO_CLIENT,
            'rating' => 4,
            'client_id' => $this->client->id,
            'employe_id' => $this->provider->id,
        ]);
    }

    public function test_submit_rejects_invalid_rating(): void
    {
        Sanctum::actingAs($this->provider);

        $this->postJson("/api/provider/bookings/{$this->booking->id}/rating", [
            'rating' => 9,
        ])->assertStatus(422)->assertJsonValidationErrors(['rating']);
    }

    public function test_respond_attaches_provider_reply_to_received_review(): void
    {
        $feedback = Feedback::create([
            'booking_id' => $this->booking->id,
            'rendez_vous_id' => $this->booking->id,
            'client_id' => $this->client->id,
            'employe_id' => $this->provider->id,
            'direction' => Feedback::DIRECTION_CLIENT_TO_PROVIDER,
            'rating' => 5,
            'note' => 5,
            'comment' => 'Travail impeccable',
            'status' => Feedback::STATUS_PUBLISHED,
            'is_public' => true,
            'published_at' => now()->subHour(),
            'answered_at' => now()->subHour(),
        ]);

        Sanctum::actingAs($this->provider);

        $response = $this->postJson("/api/provider/ratings/{$feedback->id}/respond", [
            'response' => 'Merci beaucoup pour votre retour !',
        ]);

        $response->assertOk()
            ->assertJsonPath('feedback_id', $feedback->id)
            ->assertJsonPath('provider_response', 'Merci beaucoup pour votre retour !');
        $response->assertJsonPath('provider_responded_at', fn ($v) => $v !== null);

        $this->assertDatabaseHas('feedback', [
            'id' => $feedback->id,
            'provider_response' => 'Merci beaucoup pour votre retour !',
        ]);
    }

    public function test_mine_lists_reviews_received_by_provider(): void
    {
        Feedback::create([
            'booking_id' => $this->booking->id,
            'rendez_vous_id' => $this->booking->id,
            'client_id' => $this->client->id,
            'employe_id' => $this->provider->id,
            'direction' => Feedback::DIRECTION_CLIENT_TO_PROVIDER,
            'rating' => 5,
            'note' => 5,
            'comment' => 'Excellent',
            'status' => Feedback::STATUS_PUBLISHED,
            'is_public' => true,
            'is_hidden' => false,
            'published_at' => now()->subHour(),
            'answered_at' => now()->subHour(),
            'reports_count' => 0,
        ]);

        // Provider-authored review (provider_to_client) must NOT appear in "mine".
        Feedback::create([
            'booking_id' => $this->booking->id,
            'rendez_vous_id' => $this->booking->id,
            'client_id' => $this->client->id,
            'employe_id' => $this->provider->id,
            'direction' => Feedback::DIRECTION_PROVIDER_TO_CLIENT,
            'rating' => 3,
            'note' => 3,
            'status' => Feedback::STATUS_PUBLISHED,
            'is_public' => true,
            'published_at' => now(),
            'answered_at' => now(),
        ]);

        Sanctum::actingAs($this->provider);

        $response = $this->getJson('/api/provider/ratings/me?limit=10');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure([
                'data' => [
                    ['id', 'rating', 'comment', 'client_name', 'status', 'is_hidden', 'published_at', 'provider_response', 'reports_count'],
                ],
            ])
            ->assertJsonPath('data.0.rating', 5)
            ->assertJsonPath('data.0.comment', 'Excellent')
            ->assertJsonPath('data.0.client_name', $this->client->name);
    }

    public function test_mine_rejects_out_of_range_limit(): void
    {
        Sanctum::actingAs($this->provider);

        $this->getJson('/api/provider/ratings/me?limit=500')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['limit']);
    }
}
