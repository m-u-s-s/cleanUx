<?php

namespace App\Observers;

use App\Models\Booking;
use App\Models\Feedback;
use App\Models\User;
use App\Notifications\Rating\RatingRequestedNotification;
use App\Services\Analytics\AnalyticsService;
use App\Services\Promotion\ReferralService;
use App\Support\Accounting\BookingAutoPoster;
use App\Support\Chat\BookingChatAutoCreator;
use App\Support\Domain\BookingStatus;
use App\Support\Presence\PresenceAutoTransitioner;
use App\Support\TripTracking\TripTrackingAutoCloser;
use App\Support\Webhooks\BusinessEventEmitter;

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
        } elseif ($booking->wasChanged('status')) {
            $this->trackStatusAnalytics($booking);
            $this->emitBusinessWebhookForStatus($booking);
            $newStatus = $booking->status;
            // Provider démarre mission → busy
            if (in_array($newStatus, ['en_cours', 'started', 'in_progress'], true)) {
                PresenceAutoTransitioner::bookingStarted($booking);
            }
            // Auto-end trip tracking + presence si annulation
            if (in_array($newStatus, ['annule', 'cancelled', 'canceled'], true)) {
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
            in_array($status, [BookingStatus::CONFIRME ?? 'confirme', 'confirmed', 'scheduled'], true) => 'booking.scheduled',
            in_array($status, ['assigned', 'assigne'], true) => 'booking.assigned',
            in_array($status, ['en_cours', 'started', 'in_progress'], true) => 'booking.started',
            in_array($status, ['annule', 'cancelled', 'canceled'], true) => 'booking.cancelled',
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
                'provider_id' => $booking->employe_id ?? $booking->assigned_provider_user_id ?? null,
                'service_zone_id' => $booking->service_zone_id ?? null,
                'service_catalog_id' => $booking->service_catalog_id ?? null,
                'amount_cents' => $booking->total_amount_cents ?? null,
                'currency' => $booking->currency ?? null,
                'occurred_at' => now()->toIso8601String(),
            ],
            idempotencyKey: $eventCode . ':booking:' . $booking->id . ':' . now()->format('YmdHi'),
            sourceType: Booking::class,
            sourceId: (int) $booking->id,
        );
    }

    protected function trackStatusAnalytics(Booking $booking): void
    {
        $status = $booking->status;
        $eventName = match (true) {
            in_array($status, [BookingStatus::CONFIRME ?? 'confirme', 'confirmed'], true) => 'booking.confirmed',
            in_array($status, ['annule', 'cancelled', 'canceled'], true) => 'booking.cancelled',
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
                    'idempotency_key' => $eventName . ':' . $booking->id,
                    'category' => \App\Models\AnalyticsEvent::CATEGORY_LIFECYCLE,
                    'revenue_cents' => $eventName === 'booking.completed' ? ($booking->total_amount_cents ?? null) : null,
                    'currency' => $booking->currency ?? null,
                ],
            );
        } catch (\Throwable $e) {
            // soft-fail, never block booking flow
        }
    }

    protected function justBecameCompleted(Booking $booking): bool
    {
        if (! $booking->wasChanged('status')) {
            return false;
        }

        $completedAliases = [BookingStatus::TERMINE, 'completed', 'done'];

        return in_array($booking->status, $completedAliases, true);
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
            app(\App\Services\Loyalty\LoyaltyService::class)
                ->awardBookingPoints($client, $booking);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    protected function requestRatings(Booking $booking): void
    {
        try {
            $clientId = (int) ($booking->client_id ?? $booking->customer_user_id ?? 0);
            $providerId = (int) ($booking->employe_id ?? $booking->assigned_provider_user_id ?? 0);

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