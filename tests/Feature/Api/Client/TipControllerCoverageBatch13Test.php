<?php

namespace Tests\Feature\Api\Client;

use App\Models\Booking;
use App\Models\BookingTip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TipControllerCoverageBatch13Test extends TestCase
{
    use RefreshDatabase;

    private function completedBooking(User $client, User $provider): Booking
    {
        return Booking::factory()->create([
            'client_id' => $client->id,
            'employe_id' => $provider->id,
            'status' => 'termine',
            'devis_estime' => 100.00,
        ]);
    }

    private function makeTip(User $client, User $provider, string $status): BookingTip
    {
        $booking = $this->completedBooking($client, $provider);

        return BookingTip::query()->create([
            'code' => BookingTip::generateCode(),
            'booking_id' => $booking->id,
            'client_user_id' => $client->id,
            'provider_user_id' => $provider->id,
            'amount_cents' => 1500,
            'currency' => 'EUR',
            'status' => $status,
            'client_bonus_points' => 15,
        ]);
    }

    public function test_endpoints_require_auth(): void
    {
        $booking = Booking::factory()->create();

        $this->getJson("/api/client/bookings/{$booking->id}/tip/suggestions")->assertUnauthorized();
        $this->postJson("/api/client/bookings/{$booking->id}/tip", ['amount_cents' => 500])->assertUnauthorized();
        $this->getJson('/api/client/tips/mine')->assertUnauthorized();
    }

    public function test_suggestions_returns_three_presets_for_owner(): void
    {
        $client = User::factory()->client()->create();
        $provider = User::factory()->employe()->create();
        $booking = $this->completedBooking($client, $provider);

        Sanctum::actingAs($client);

        $data = $this->getJson("/api/client/bookings/{$booking->id}/tip/suggestions")
            ->assertOk()
            ->assertJsonStructure(['data' => [['label', 'percent', 'amount_cents', 'amount_formatted']]])
            ->json('data');

        $this->assertCount(3, $data);
        $this->assertSame(1000, $data[0]['amount_cents']);
        $this->assertSame(1500, $data[1]['amount_cents']);
        $this->assertSame(2000, $data[2]['amount_cents']);
    }

    public function test_suggestions_forbidden_for_other_client(): void
    {
        $owner = User::factory()->client()->create();
        $intruder = User::factory()->client()->create();
        $provider = User::factory()->employe()->create();
        $booking = $this->completedBooking($owner, $provider);

        Sanctum::actingAs($intruder);

        $this->getJson("/api/client/bookings/{$booking->id}/tip/suggestions")
            ->assertStatus(403)
            ->assertJson(['error' => 'forbidden']);
    }

    public function test_create_persists_tip_and_returns_201(): void
    {
        $client = User::factory()->client()->create();
        $provider = User::factory()->employe()->create(['name' => 'Jean Martin']);
        $booking = $this->completedBooking($client, $provider);

        Sanctum::actingAs($client);

        $payload = [
            'amount_cents' => 1500,
            'preset_label' => '15%',
            'preset_percent' => 15,
            'message' => 'Super travail, merci!',
        ];

        $data = $this->postJson("/api/client/bookings/{$booking->id}/tip", $payload)
            ->assertStatus(201)
            ->assertJsonStructure(['data' => [
                'id', 'code', 'booking_id', 'provider' => ['id', 'name'],
                'amount_cents', 'currency', 'status', 'message',
                'preset_label', 'client_bonus_points', 'charged_at',
                'paid_out_at', 'created_at',
            ]])
            ->json('data');

        $this->assertSame(1500, $data['amount_cents']);
        $this->assertSame('EUR', $data['currency']);
        $this->assertSame(BookingTip::STATUS_PENDING, $data['status']);
        $this->assertSame('Jean Martin', $data['provider']['name']);
        $this->assertSame(15, $data['client_bonus_points']);
        $this->assertSame((int) $booking->id, $data['booking_id']);

        $this->assertDatabaseHas('booking_tips', [
            'booking_id' => $booking->id,
            'client_user_id' => $client->id,
            'amount_cents' => 1500,
            'status' => BookingTip::STATUS_PENDING,
        ]);
    }

    public function test_create_rejects_request_with_invalid_amount(): void
    {
        $client = User::factory()->client()->create();
        $provider = User::factory()->employe()->create();
        $booking = $this->completedBooking($client, $provider);

        Sanctum::actingAs($client);

        $this->postJson("/api/client/bookings/{$booking->id}/tip", ['amount_cents' => 10])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount_cents']);
    }

    public function test_create_returns_422_when_booking_not_eligible(): void
    {
        $client = User::factory()->client()->create();
        $provider = User::factory()->employe()->create();
        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'employe_id' => $provider->id,
            'status' => 'en_cours',
            'devis_estime' => 100.00,
        ]);

        Sanctum::actingAs($client);

        $this->postJson("/api/client/bookings/{$booking->id}/tip", ['amount_cents' => 1500])
            ->assertStatus(422)
            ->assertJson(['error' => 'validation_failed'])
            ->assertJsonStructure(['error', 'errors']);
    }

    public function test_mine_lists_only_own_tips_with_status_filter_and_limit(): void
    {
        $client = User::factory()->client()->create();
        $other = User::factory()->client()->create();
        $provider = User::factory()->employe()->create();

        $charged = $this->makeTip($client, $provider, BookingTip::STATUS_CHARGED);
        $pending = $this->makeTip($client, $provider, BookingTip::STATUS_PENDING);
        $foreign = $this->makeTip($other, $provider, BookingTip::STATUS_CHARGED);

        Sanctum::actingAs($client);

        // No filter: only own tips.
        $all = $this->getJson('/api/client/tips/mine')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'code', 'provider', 'status']]])
            ->json('data');

        $ids = collect($all)->pluck('id');
        $this->assertTrue($ids->contains($charged->id));
        $this->assertTrue($ids->contains($pending->id));
        $this->assertFalse($ids->contains($foreign->id), 'cross-client leak');

        // Status filter narrows results.
        $chargedOnly = $this->getJson('/api/client/tips/mine?status=charged')
            ->assertOk()
            ->json('data');
        $this->assertCount(1, $chargedOnly);
        $this->assertSame($charged->id, $chargedOnly[0]['id']);

        // Limit caps result count.
        $limited = $this->getJson('/api/client/tips/mine?limit=1')
            ->assertOk()
            ->json('data');
        $this->assertCount(1, $limited);
    }

    public function test_mine_rejects_invalid_limit(): void
    {
        $client = User::factory()->client()->create();
        Sanctum::actingAs($client);

        $this->getJson('/api/client/tips/mine?limit=500')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['limit']);
    }
}
