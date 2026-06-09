<?php

namespace Tests\Feature\Livewire\Client;

use App\Livewire\Client\ClientFeedbackForm;
use App\Models\Booking;
use App\Models\Feedback;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClientFeedbackFormCoverageBatch12Test extends TestCase
{
    use RefreshDatabase;

    protected User $client;

    protected User $provider;

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
    }

    private function makeBooking(string $status = 'termine'): Booking
    {
        return Booking::create([
            'client_id' => $this->client->id,
            'employe_id' => $this->provider->id,
            'date' => now()->subDay(),
            'heure' => '10:00',
            'status' => $status,
            'mission_finished_at' => now()->subDay(),
            'devis_estime' => 100,
        ]);
    }

    public function test_mount_defaults_for_fresh_booking(): void
    {
        $booking = $this->makeBooking();

        Livewire::actingAs($this->client)
            ->test(ClientFeedbackForm::class, ['rendezVous' => $booking])
            ->assertOk()
            ->assertSet('rating', 5)
            ->assertSet('is_public', true)
            ->assertSet('submitted', false)
            ->assertSet('existingFeedback', null);
    }

    public function test_mount_hydrates_existing_feedback(): void
    {
        $booking = $this->makeBooking();

        Feedback::create([
            'booking_id' => $booking->id,
            'rendez_vous_id' => $booking->id,
            'client_id' => $this->client->id,
            'employe_id' => $this->provider->id,
            'direction' => Feedback::DIRECTION_CLIENT_TO_PROVIDER,
            'rating' => 3,
            'note' => 3,
            'punctuality_score' => 4,
            'quality_score' => 2,
            'communication_score' => 5,
            'value_score' => 1,
            'comment' => 'Existant',
            'is_public' => false,
            'status' => Feedback::STATUS_PENDING,
            'answered_at' => now(),
        ]);

        Livewire::actingAs($this->client)
            ->test(ClientFeedbackForm::class, ['rendezVous' => $booking])
            ->assertOk()
            ->assertSet('rating', 3)
            ->assertSet('punctuality', 4)
            ->assertSet('quality', 2)
            ->assertSet('communication', 5)
            ->assertSet('value', 1)
            ->assertSet('comment', 'Existant')
            ->assertSet('is_public', false);
    }

    public function test_mount_aborts_for_non_owner(): void
    {
        $booking = $this->makeBooking();
        $stranger = User::factory()->client()->create();

        Livewire::actingAs($stranger)
            ->test(ClientFeedbackForm::class, ['rendezVous' => $booking])
            ->assertForbidden();
    }

    public function test_set_rating_clamps_to_range(): void
    {
        $booking = $this->makeBooking();

        Livewire::actingAs($this->client)
            ->test(ClientFeedbackForm::class, ['rendezVous' => $booking])
            ->call('setRating', 9)
            ->assertSet('rating', 5)
            ->call('setRating', -3)
            ->assertSet('rating', 1)
            ->call('setRating', 3)
            ->assertSet('rating', 3);
    }

    public function test_set_dimension_maps_each_dimension_and_clamps(): void
    {
        $booking = $this->makeBooking();

        Livewire::actingAs($this->client)
            ->test(ClientFeedbackForm::class, ['rendezVous' => $booking])
            ->call('setDimension', 'punctuality', 8)
            ->assertSet('punctuality', 5)
            ->call('setDimension', 'quality', 0)
            ->assertSet('quality', 1)
            ->call('setDimension', 'communication', 4)
            ->assertSet('communication', 4)
            ->call('setDimension', 'value', 2)
            ->assertSet('value', 2)
            ->call('setDimension', 'unknown', 3)
            ->assertSet('value', 2);
    }

    public function test_submit_persists_feedback_and_marks_submitted(): void
    {
        $booking = $this->makeBooking();

        Livewire::actingAs($this->client)
            ->test(ClientFeedbackForm::class, ['rendezVous' => $booking])
            ->set('rating', 4)
            ->set('punctuality', 5)
            ->set('quality', 4)
            ->set('comment', 'Service impeccable')
            ->call('submit')
            ->assertSet('submitted', true)
            ->assertSet('globalError', null)
            ->assertDispatched('toast');

        $this->assertDatabaseHas('feedback', [
            'booking_id' => $booking->id,
            'direction' => Feedback::DIRECTION_CLIENT_TO_PROVIDER,
            'rating' => 4,
        ]);
    }

    public function test_submit_validation_error_on_invalid_rating(): void
    {
        $booking = $this->makeBooking();

        Livewire::actingAs($this->client)
            ->test(ClientFeedbackForm::class, ['rendezVous' => $booking])
            ->set('rating', 0)
            ->call('submit')
            ->assertHasErrors('rating')
            ->assertSet('submitted', false);
    }

    public function test_submit_catches_service_validation_exception(): void
    {
        // Booking is not completed -> RatingService throws ValidationException,
        // which the component catches and surfaces via globalError.
        $booking = $this->makeBooking('confirme');

        Livewire::actingAs($this->client)
            ->test(ClientFeedbackForm::class, ['rendezVous' => $booking])
            ->set('rating', 5)
            ->call('submit')
            ->assertSet('submitted', false)
            ->assertHasErrors('rating');

        $this->assertDatabaseMissing('feedback', [
            'booking_id' => $booking->id,
            'direction' => Feedback::DIRECTION_CLIENT_TO_PROVIDER,
        ]);
    }
}
