<?php

namespace Tests\Feature\Safety;

use App\Models\Booking;
use App\Models\MaskedCallSession;
use App\Models\User;
use App\Services\Safety\MaskedCallService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MaskedCallServiceCoverageBatch17Test extends TestCase
{
    use RefreshDatabase;

    public function test_open_session_creates_new_when_existing_booking_session_has_expired(): void
    {
        $client = User::factory()->create(['phone' => '+32470123456']);
        $provider = User::factory()->create(['phone' => '+32488998877']);
        $booking = Booking::factory()->create();
        $service = app(MaskedCallService::class);

        $first = $service->openSession($client, $provider, $booking);
        // Keep ACTIVE status but expire it so isActive() returns false.
        $first->update(['expires_at' => now()->subHour()]);

        $second = $service->openSession($client, $provider, $booking);

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(MaskedCallSession::STATUS_ACTIVE, $second->status);
        $this->assertSame(2, MaskedCallSession::query()->where('booking_id', $booking->id)->count());
    }

    public function test_open_session_without_booking_persists_unlinked_session(): void
    {
        $client = User::factory()->create(['phone' => '+32470123456']);
        $provider = User::factory()->create(['phone' => '+32488998877']);

        $session = app(MaskedCallService::class)->openSession($client, $provider, null);

        $this->assertNull($session->booking_id);
        $this->assertTrue($session->isActive());
    }

    public function test_open_session_rejects_provider_without_phone(): void
    {
        $client = User::factory()->create(['phone' => '+32470123456']);
        $provider = User::factory()->create(['phone' => null]);

        $this->expectException(ValidationException::class);
        app(MaskedCallService::class)->openSession($client, $provider);
    }

    public function test_close_session_invokes_twilio_close_branch_when_sid_present_but_unconfigured(): void
    {
        config(['masked_calls.twilio_service_sid' => null]);
        $client = User::factory()->create(['phone' => '+32470123456']);
        $provider = User::factory()->create(['phone' => '+32488998877']);
        $service = app(MaskedCallService::class);

        $session = $service->openSession($client, $provider);
        // Simulate a previously assigned Twilio session id to enter the close branch.
        $session->update(['twilio_session_sid' => 'KCxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx']);

        $closed = $service->closeSession($session->fresh(), 'mission_completed');

        $this->assertSame(MaskedCallSession::STATUS_CLOSED, $closed->status);
        $this->assertSame('mission_completed', $closed->metadata['close_reason'] ?? null);
        $this->assertNotNull($closed->closed_at);
    }

    public function test_scan_expired_returns_zero_when_nothing_to_expire(): void
    {
        $client = User::factory()->create(['phone' => '+32470123456']);
        $provider = User::factory()->create(['phone' => '+32488998877']);
        $service = app(MaskedCallService::class);

        $service->openSession($client, $provider);

        $this->assertSame(0, $service->scanExpired());
    }
}
