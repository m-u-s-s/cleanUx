<?php

namespace App\Observers;

use App\Models\PushNotification;
use App\Models\TripTrackingSession;
use App\Services\Push\PushService;
use App\Support\Webhooks\BusinessEventEmitter;
use Illuminate\Support\Facades\Log;

/**
 * Réagit aux transitions de status TripTrackingSession :
 *   - enroute → arrived : push au client "votre prestataire est arrivé"
 *   - arrived → in_mission : push au client "mission démarrée"
 *   - * → ended : push au client "mission terminée" + webhook B2B
 *
 * Soft-fail partout — ne bloque jamais le flow business.
 */
class TripTrackingSessionObserver
{
    public function updated(TripTrackingSession $session): void
    {
        if (! $session->wasChanged('status')) {
            return;
        }

        $newStatus = $session->status;
        $oldStatus = $session->getOriginal('status');

        if ($newStatus === TripTrackingSession::STATUS_ARRIVED && $oldStatus === TripTrackingSession::STATUS_ENROUTE) {
            $this->notifyClientArrived($session);
            $this->emitWebhook($session, 'tracking.arrived');
        }

        if ($newStatus === TripTrackingSession::STATUS_IN_MISSION) {
            $this->notifyClientInMission($session);
            $this->emitWebhook($session, 'tracking.in_mission');
        }

        if ($newStatus === TripTrackingSession::STATUS_ENDED) {
            $this->emitWebhook($session, 'tracking.ended');
        }
    }

    public function created(TripTrackingSession $session): void
    {
        $this->notifyClientEnroute($session);
        $this->emitWebhook($session, 'tracking.started');
    }

    protected function notifyClientEnroute(TripTrackingSession $session): void
    {
        try {
            $booking = $session->booking;
            if (! $booking || ! $booking->client_id) {
                return;
            }
            $client = $booking->client;
            if (! $client) {
                return;
            }

            app(PushService::class)->dispatchToUser(
                user: $client,
                title: '🚗 Votre prestataire arrive',
                body: 'Votre prestataire est en route vers votre adresse.',
                data: [
                    'type' => 'tracking.enroute',
                    'session_code' => $session->code,
                    'booking_id' => $session->booking_id,
                ],
                category: PushNotification::CATEGORY_TRANSACTIONAL,
                idempotencyKey: 'tracking_enroute_' . $session->id,
                source: $session,
            );
        } catch (\Throwable $e) {
            Log::warning('[trip_tracking_push] enroute failed', ['error' => $e->getMessage()]);
        }
    }

    protected function notifyClientArrived(TripTrackingSession $session): void
    {
        try {
            $booking = $session->booking;
            $client = $booking?->client;
            if (! $client) {
                return;
            }
            app(PushService::class)->dispatchToUser(
                user: $client,
                title: '📍 Prestataire arrivé',
                body: 'Votre prestataire vient d\'arriver à votre adresse.',
                data: [
                    'type' => 'tracking.arrived',
                    'session_code' => $session->code,
                    'booking_id' => $session->booking_id,
                ],
                category: PushNotification::CATEGORY_TRANSACTIONAL,
                idempotencyKey: 'tracking_arrived_' . $session->id,
                source: $session,
            );
        } catch (\Throwable $e) {
            Log::warning('[trip_tracking_push] arrived failed', ['error' => $e->getMessage()]);
        }
    }

    protected function notifyClientInMission(TripTrackingSession $session): void
    {
        try {
            $booking = $session->booking;
            $client = $booking?->client;
            if (! $client) {
                return;
            }
            app(PushService::class)->dispatchToUser(
                user: $client,
                title: '✨ Mission démarrée',
                body: 'Votre prestataire a commencé la mission.',
                data: [
                    'type' => 'tracking.in_mission',
                    'session_code' => $session->code,
                    'booking_id' => $session->booking_id,
                ],
                category: PushNotification::CATEGORY_TRANSACTIONAL,
                idempotencyKey: 'tracking_in_mission_' . $session->id,
                source: $session,
            );
        } catch (\Throwable $e) {
            Log::warning('[trip_tracking_push] in_mission failed', ['error' => $e->getMessage()]);
        }
    }

    protected function emitWebhook(TripTrackingSession $session, string $eventCode): void
    {
        if (! class_exists(BusinessEventEmitter::class)) {
            return;
        }
        BusinessEventEmitter::emit(
            eventCode: $eventCode,
            payload: [
                'session_code' => $session->code,
                'session_id' => $session->id,
                'booking_id' => $session->booking_id,
                'provider_user_id' => $session->provider_user_id,
                'status' => $session->status,
                'total_distance_m' => (int) $session->total_distance_m,
                'points_count' => (int) $session->points_count,
                'current_eta_seconds' => $session->current_eta_seconds,
            ],
            idempotencyKey: $eventCode . '_' . $session->id,
            sourceType: TripTrackingSession::class,
            sourceId: $session->id,
        );
    }
}
