<?php

namespace Tests\Feature\Safety;

use App\Models\Booking;
use App\Models\MaskedCallSession;
use App\Models\User;
use App\Services\Safety\MaskedCallService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MaskedCallServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_session_creates_active_session_with_masked_phones(): void
    {
        $client = User::factory()->create(['phone' => '+32470123456']);
        $provider = User::factory()->create(['phone' => '+32488998877']);

        $session = app(MaskedCallService::class)->openSession($client, $provider);

        $this->assertInstanceOf(MaskedCallSession::class, $session);
        $this->assertSame(MaskedCallSession::STATUS_ACTIVE, $session->status);
        $this->assertSame($client->id, (int) $session->client_user_id);
        $this->assertSame($provider->id, (int) $session->provider_user_id);
        $this->assertStringStartsWith('mask_', $session->code);
        // Twilio not configured in test env -> persisted DB-only, no sid
        $this->assertNull($session->twilio_session_sid);
        $this->assertNotNull($session->expires_at);
        // Masked: only last 4 digits preserved
        $this->assertStringEndsWith('3456', $session->client_phone_masked);
        $this->assertStringEndsWith('8877', $session->provider_phone_masked);
        $this->assertStringContainsString('*', $session->client_phone_masked);
    }

    public function test_open_session_masks_short_phone_with_stars(): void
    {
        $client = User::factory()->create(['phone' => '12']);
        $provider = User::factory()->create(['phone' => '+32488998877']);

        $session = app(MaskedCallService::class)->openSession($client, $provider);

        $this->assertSame('****', $session->client_phone_masked);
    }

    public function test_open_session_requires_both_phones(): void
    {
        $client = User::factory()->create(['phone' => null]);
        $provider = User::factory()->create(['phone' => '+32488998877']);

        $this->expectException(ValidationException::class);
        app(MaskedCallService::class)->openSession($client, $provider);
    }

    public function test_open_session_is_idempotent_per_booking(): void
    {
        $client = User::factory()->create(['phone' => '+32470123456']);
        $provider = User::factory()->create(['phone' => '+32488998877']);
        $booking = Booking::factory()->create();

        $service = app(MaskedCallService::class);
        $first = $service->openSession($client, $provider, $booking);
        $second = $service->openSession($client, $provider, $booking);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, MaskedCallSession::query()->where('booking_id', $booking->id)->count());
    }

    public function test_close_session_marks_closed_with_reason(): void
    {
        $client = User::factory()->create(['phone' => '+32470123456']);
        $provider = User::factory()->create(['phone' => '+32488998877']);
        $service = app(MaskedCallService::class);
        $session = $service->openSession($client, $provider);

        $closed = $service->closeSession($session, 'mission_completed');

        $this->assertSame(MaskedCallSession::STATUS_CLOSED, $closed->status);
        $this->assertNotNull($closed->closed_at);
        $this->assertSame('mission_completed', $closed->metadata['close_reason'] ?? null);
    }

    public function test_close_session_noop_when_not_active(): void
    {
        $client = User::factory()->create(['phone' => '+32470123456']);
        $provider = User::factory()->create(['phone' => '+32488998877']);
        $service = app(MaskedCallService::class);
        $session = $service->openSession($client, $provider);
        $service->closeSession($session);

        // Second close on an already-closed session is a no-op
        $again = $service->closeSession($session->fresh(), 'should_not_apply');

        $this->assertSame(MaskedCallSession::STATUS_CLOSED, $again->status);
        $this->assertArrayNotHasKey('close_reason', $again->metadata ?? []);
    }

    public function test_scan_expired_transitions_only_past_active_sessions(): void
    {
        $client = User::factory()->create(['phone' => '+32470123456']);
        $provider = User::factory()->create(['phone' => '+32488998877']);
        $service = app(MaskedCallService::class);

        $expired = $service->openSession($client, $provider);
        $expired->update(['expires_at' => now()->subHour()]);

        $live = $service->openSession(
            User::factory()->create(['phone' => '+32470000001']),
            User::factory()->create(['phone' => '+32470000002']),
        );

        $count = $service->scanExpired();

        $this->assertSame(1, $count);
        $this->assertSame(MaskedCallSession::STATUS_EXPIRED, $expired->fresh()->status);
        $this->assertNotNull($expired->fresh()->closed_at);
        $this->assertSame(MaskedCallSession::STATUS_ACTIVE, $live->fresh()->status);
    }
}
