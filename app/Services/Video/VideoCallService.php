<?php

namespace App\Services\Video;

use App\Models\Booking;
use App\Models\User;

/**
 * 7.3 — Video call integration for remote inspection / pre-mission consultation.
 *
 * Interface defined; implementation deferred.
 *
 * Planned providers: Twilio Video, Daily.co, or Jitsi self-hosted.
 * Use-case: client shows site condition before booking (supports AI photo
 * quote pipeline), or remote quality inspection by supervisor.
 *
 * TODO: implement VideoCallProvider contract + TwilioVideoProvider
 * TODO: persist video_call_sessions table (session_id, booking_id, participants, status, recording_url)
 * TODO: add webhook handler for session-end events (auto-close, recording archive)
 * TODO: integrate with quality/inspection module — attach recording to Inspection
 */
class VideoCallService
{
    /**
     * Create a video room for two participants tied to a booking.
     *
     * @param  User  $host  Initiator (client or supervisor)
     * @param  User  $guest  Recipient (provider or client)
     * @param  Booking|null  $booking  Optional booking context
     * @return array{room_url: string, room_sid: string, token_host: string, token_guest: string}
     *
     * @throws \RuntimeException when no video provider is configured
     *
     * TODO: implement
     */
    public function createRoom(User $host, User $guest, ?Booking $booking = null): array
    {
        throw new \RuntimeException('[VideoCallService] Not implemented — TODO: implement provider.');
    }

    /**
     * Close/archive a video room by its external SID.
     *
     * TODO: implement
     */
    public function closeRoom(string $roomSid): void
    {
        throw new \RuntimeException('[VideoCallService] Not implemented — TODO: implement provider.');
    }
}
