<?php

namespace App\Observers;

use App\Models\AnalyticsEvent;
use App\Models\Booking;
use App\Models\Feedback;
use App\Models\User;
use App\Notifications\Rating\RatingRequestedNotification;
use App\Services\Analytics\AnalyticsService;
use App\Services\Badges\ProviderBadgeEngine;
use App\Services\Loyalty\LoyaltyService;
use App\Services\Promotion\ReferralService;
use App\Support\Accounting\BookingAutoPoster;
use App\Support\Chat\BookingChatAutoCreator;
use App\Support\Domain\BookingStatus;
use App\Support\Presence\PresenceAutoTransitioner;
use App\Support\TripTracking\TripTrackingAutoCloser;
use App\Support\Webhooks\BusinessEventEmitter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class BookingObserver
{
    public function saved(Booking $booking): void
    {
        if ($booking->customer_organization_id) {
            // Invalider toutes les clés analytics:* pour cette org
            // (avec Redis : SCAN + DEL ; avec file/db : laisser expirer naturellement)
        }

        if ($this->justBecameCompleted($booking)) {
            $this->maybeQualifyReferral($booking);
            $this->requestRatings($booking);
            $this->trackAnalytics($booking, 'booking.completed');
            $this->emitBusinessWebhook($booking, 'booking.completed');
            BookingAutoPoster::postSale($booking);
            BookingChatAutoCreator::archiveThreadIfBookingCompleted($booking);
            TripTrackingAutoCloser::endSessionForBooking($booking, 'booking_completed');
            PresenceAutoTransitioner::bookingEnded($booking);
            $this->maybeEvaluateProviderBadges($booking);
        } elseif ($booking->wasChanged('status')) {
            $this->trackStatusAnalytics($booking);
            $this->emitBusinessWebhookForStatus($booking);
            $newStatus = $booking->status;
            // Provider démarre mission → busy
            if (in_array($newStatus, [BookingStatus::EN_ROUTE, BookingStatus::SUR_PLACE], true)) {
                PresenceAutoTransitioner::bookingStarted($booking);
            }
            // Auto-end trip tracking + presence si annulation
            if (in_array($newStatus, BookingStatus::cancelledAliases(), true)) {
                TripTrackingAutoCloser::endSessionForBooking($booking, 'booking_cancelled');
                PresenceAutoTransitioner::bookingEnded($booking);
            }
        }
    }

    public function created(Booking $booking): void
    {
        $this->trackAnalytics($booking, 'booking.created');
        $this->emitBusinessWebhook($booking, 'booking.created');
        BookingChatAutoCreator::ensureThreadForBooking($booking);
    }

    protected function emitBusinessWebhookForStatus(Booking $booking): void
    {
        $status = $booking->status;
        $eventCode = match (true) {
            $status === BookingStatus::CONFIRME => 'booking.scheduled',
            in_array($status, [BookingStatus::EN_ROUTE, BookingStatus::SUR_PLACE], true) => 'booking.started',
            in_array($status, BookingStatus::cancelledAliases(), true) => 'booking.cancelled',
            default => null,
        };
        if ($eventCode) {
            $this->emitBusinessWebhook($booking, $eventCode);
        }
    }

    protected function emitBusinessWebhook(Booking $booking, string $eventCode): void
    {
        BusinessEventEmitter::emit(
            eventCode: $eventCode,
            payload: [
                'booking_id' => $booking->id,
                'status' => $booking->status,
                'client_id' => $booking->client_id ?? $booking->customer_user_id ?? null,
                'provider_id' => $booking->intervenantId(),
                'service_zone_id' => $booking->service_zone_id ?? null,
                'service_catalog_id' => $booking->service_catalog_id ?? null,
                'amount_cents' => $booking->total_amount_cents ?? null,
                'currency' => $booking->currency ?? null,
                'occurred_at' => now()->toIso8601String(),
            ],
            idempotencyKey: $eventCode.':booking:'.$booking->id.':'.now()->format('YmdHi'),
            sourceType: Booking::class,
            sourceId: (int) $booking->id,
        );
    }

    protected function trackStatusAnalytics(Booking $booking): void
    {
        $status = $booking->status;
        $eventName = match (true) {
            $status === BookingStatus::CONFIRME => 'booking.confirmed',
            in_array($status, BookingStatus::cancelledAliases(), true) => 'booking.cancelled',
            default => null,
        };
        if ($eventName) {
            $this->trackAnalytics($booking, $eventName);
        }
    }

    protected function trackAnalytics(Booking $booking, string $eventName): void
    {
        try {
            app(AnalyticsService::class)->track(
                $eventName,
                [
                    'booking_id' => $booking->id,
                    'service_zone_id' => $booking->service_zone_id ?? null,
                    'service_catalog_id' => $booking->service_catalog_id ?? null,
                    'amount_cents' => $booking->total_amount_cents ?? null,
                ],
                [
                    'idempotency_key' => $eventName.':'.$booking->id,
                    'category' => AnalyticsEvent::CATEGORY_LIFECYCLE,
                    'revenue_cents' => $eventName === 'booking.completed' ? ($booking->total_amount_cents ?? null) : null,
                    'currency' => $booking->currency ?? null,
                ],
            );
        } catch (\Throwable $e) {
            // soft-fail, never block booking flow
        }
    }

    /**
     * Auto-évaluation badges provider après une mission complétée.
     * Soft-fail : si module Badges absent, skip silencieusement.
     */
    protected function maybeEvaluateProviderBadges(Booking $booking): void
    {
        try {
            if (! class_exists(ProviderBadgeEngine::class)) {
                return;
            }
            if (! Schema::hasTable('provider_badges')) {
                return;
            }
            // Les badges récompensent CELUI QUI A FAIT LA MISSION.
            $providerId = $booking->intervenantId();
            if (! $providerId) {
                return;
            }
            $provider = User::find($providerId);
            if (! $provider) {
                return;
            }
            app(ProviderBadgeEngine::class)->evaluate($provider);
        } catch (\Throwable $e) {
            Log::warning('[badges_auto] post-mission evaluate failed', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function justBecameCompleted(Booking $booking): bool
    {
        if (! $booking->wasChanged('status')) {
            return false;
        }

        return in_array($booking->status, BookingStatus::completedAliases(), true);
    }

    protected function maybeQualifyReferral(Booking $booking): void
    {
        try {
            app(ReferralService::class)->markQualifiedByBooking($booking);
        } catch (\Throwable $e) {
            report($e);
        }

        $this->awardLoyaltyForBooking($booking);
    }

    protected function awardLoyaltyForBooking(Booking $booking): void
    {
        try {
            $clientId = (int) ($booking->client_id ?? $booking->customer_user_id ?? 0);
            if (! $clientId) {
                return;
            }
            $client = User::find($clientId);
            if (! $client) {
                return;
            }
            app(LoyaltyService::class)
                ->awardBookingPoints($client, $booking);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    protected function requestRatings(Booking $booking): void
    {
        try {
            $clientId = (int) ($booking->client_id ?? $booking->customer_user_id ?? 0);
            // On demande son avis à l'intervenant réel, et le client note celui qui est venu.
            $providerId = (int) ($booking->intervenantId() ?? 0);

            if ($clientId) {
                $client = User::find($clientId);
                if ($client) {
                    $client->notify(new RatingRequestedNotification(
                        $booking,
                        Feedback::DIRECTION_CLIENT_TO_PROVIDER,
                    ));
                }
            }

            if ($providerId) {
                $provider = User::find($providerId);
                if ($provider) {
                    $provider->notify(new RatingRequestedNotification(
                        $booking,
                        Feedback::DIRECTION_PROVIDER_TO_CLIENT,
                    ));
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
