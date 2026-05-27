<?php

namespace App\Services\Calendar;

use App\Models\Booking;
use App\Models\User;

/**
 * 7.7 — Bidirectional Google Calendar sync.
 *
 * Interface defined; full bidirectional webhook loop deferred.
 *
 * Current state (Phase 11):
 *   google-calendar:sync --future-days=30 runs every 15 min (one-way push).
 *   GoogleCalendarSyncService pushes CleanUx events → provider Google Calendar.
 *
 * This service adds the missing direction:
 *   - Google → CleanUx: provider blocks a slot in Google Calendar
 *     → CleanUx marks that slot unavailable (provider_availability exception).
 *   - Conflict detection: if a CleanUx booking is created for a slot that
 *     is already blocked in Google, warn the dispatcher.
 *
 * Architecture plan:
 *   - Register a Google Calendar watch (push channel) on provider's primary calendar.
 *   - Webhook endpoint POST /api/webhooks/google-calendar/{token}
 *   - On notification: fetch changed events via incremental sync token.
 *   - Map external events to AvailabilityException rows (exception_type = 'blocked').
 *   - Channel renewal: Google watch expires after 7 days — cron re-registers.
 *
 * TODO: implement watch registration + renewal cron
 * TODO: implement webhook handler in WebhookController
 * TODO: map Google event → AvailabilityException (create/delete)
 * TODO: implement conflict check on Booking creation (soft-warn, not hard block)
 * TODO: store google_calendar_watch_channels table (user_id, channel_id, resource_id, expiry)
 */
class GoogleCalendarBidirectionalService
{
    /**
     * Register a push notification channel for a provider's Google Calendar.
     *
     * @return array{channel_id: string, resource_id: string, expiry: string}
     *
     * TODO: implement
     */
    public function registerWatch(User $provider): array
    {
        throw new \RuntimeException('[GoogleCalendarBidirectionalService] Not implemented — TODO.');
    }

    /**
     * Process an incoming Google Calendar push notification payload.
     * Called by the webhook controller when Google fires a change notification.
     *
     * TODO: implement
     */
    public function handlePushNotification(string $channelId, string $resourceId): void
    {
        throw new \RuntimeException('[GoogleCalendarBidirectionalService] Not implemented — TODO.');
    }

    /**
     * Check whether a provider has any Google Calendar block for the given slot.
     *
     * @return bool True if conflicting Google event found.
     *
     * TODO: implement
     */
    public function hasConflict(User $provider, Booking $booking): bool
    {
        return false; // safe default until implemented
    }
}
