<?php

namespace App\Observers;

use App\Models\Booking;
use App\Models\Feedback;
use App\Models\User;
use App\Notifications\Rating\RatingRequestedNotification;
use App\Services\Analytics\AnalyticsService;
use App\Services\Promotion\ReferralService;
use App\Support\Domain\BookingStatus;

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
        } elseif ($booking->wasChanged('status')) {
            $this->trackStatusAnalytics($booking);
        }
    }

    public function created(Booking $booking): void
    {
        $this->trackAnalytics($booking, 'booking.created');
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