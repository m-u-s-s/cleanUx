<?php

namespace App\Support\Presence;

use App\Models\Booking;
use App\Models\ProviderPresence;
use App\Models\User;
use App\Services\Presence\ProviderPresenceService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Helper soft-fail pour auto-transitionner la presence du provider
 * en réaction aux changements de status booking.
 *
 * Workflow :
 *   - Booking → en_cours (provider start mission)   → presence::goBusy
 *   - Booking → termine/cancelled                   → presence::goOnline (si auto_online_on_mission_complete)
 *
 * Appelé depuis BookingObserver::saved. Skip silencieusement si :
 *   - module Presence pas installé (table absente)
 *   - feature désactivée (config)
 *   - provider n'a pas de presence record (pas online actuellement)
 */
class PresenceAutoTransitioner
{
    public static function bookingStarted(Booking $booking): void
    {
        if (! self::isEnabled()) {
            return;
        }
        $provider = self::resolveProvider($booking);
        if (! $provider) {
            return;
        }

        try {
            $presence = ProviderPresence::query()
                ->where('provider_user_id', $provider->id)
                ->first();
            // Ne transition que si actuellement online (sinon ça serait reactivate un provider offline)
            if (! $presence || $presence->status !== ProviderPresence::STATUS_ONLINE) {
                return;
            }
            app(ProviderPresenceService::class)->goBusy($provider);
        } catch (\Throwable $e) {
            Log::warning('[presence_auto] bookingStarted failed', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public static function bookingEnded(Booking $booking): void
    {
        if (! self::isEnabled()) {
            return;
        }
        if (! (bool) Config::get('presence.auto_online_on_mission_complete', true)) {
            return;
        }
        $provider = self::resolveProvider($booking);
        if (! $provider) {
            return;
        }

        try {
            $presence = ProviderPresence::query()
                ->where('provider_user_id', $provider->id)
                ->first();
            // Ne transitionner busy→online (laisser break/offline tels quels)
            if (! $presence || $presence->status !== ProviderPresence::STATUS_BUSY) {
                return;
            }
            app(ProviderPresenceService::class)->goOnline($provider);
        } catch (\Throwable $e) {
            Log::warning('[presence_auto] bookingEnded failed', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected static function isEnabled(): bool
    {
        if (! Schema::hasTable('provider_presence')) {
            return false;
        }
        return (bool) Config::get('presence.enabled', true);
    }

    protected static function resolveProvider(Booking $booking): ?User
    {
        $providerId = $booking->employe_id
            ?? $booking->provider_user_id
            ?? $booking->assigned_employee_id
            ?? null;
        if (! $providerId) {
            return null;
        }
        return User::find($providerId);
    }
}
