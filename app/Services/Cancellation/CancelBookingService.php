<?php

namespace App\Services\Cancellation;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Phase 14 — Service d'annulation booking avec calcul + application du fee.
 *
 * Distingue annulation client / annulation prestataire / no-show.
 *
 * Pour le fee client : le service essaie d'utiliser
 * StripeConnectPaymentService::refundMissionPayment (Phase 13) pour ne refund
 * que la partie non-fee, sinon il marque just le booking et laisse l'admin
 * gérer.
 */
class CancelBookingService
{
    public function __construct(
        protected CancellationFeeCalculator $calculator,
    ) {}

    /**
     * Annule un booking par le client. Calcule + applique le fee.
     *
     * Renvoie un array détaillé pour audit + UI.
     */
    public function cancelByClient(Booking $booking, User $client, ?string $reason = null): array
    {
        $this->guardCanCancel($booking);

        $feeDetails = $this->calculator->forClientCancellation($booking);

        return DB::transaction(function () use ($booking, $client, $reason, $feeDetails) {
            $booking->update([
                'status'              => 'annule',
                'cancelled_at'        => now(),
                'cancelled_by'        => $client->id,
                'cancellation_reason' => $reason,
                'metadata'            => array_merge($booking->metadata ?? [], [
                    'cancellation_fee'        => $feeDetails['fee_amount'],
                    'cancellation_fee_percent'=> $feeDetails['fee_percent'],
                    'cancellation_reason_code'=> $feeDetails['reason_code'],
                ]),
            ]);

            // Si payment authorized/captured, déclencher refund partiel
            $this->tryRefundPartial($booking, $feeDetails);

            Log::info('CancelBookingService: client cancellation', [
                'booking_id' => $booking->id,
                'fee_amount' => $feeDetails['fee_amount'],
                'reason'     => $feeDetails['reason_code'],
            ]);

            return [
                'ok'           => true,
                'booking_id'   => $booking->id,
                'fee_details'  => $feeDetails,
                'is_free'      => $feeDetails['is_free'],
            ];
        });
    }

    /**
     * Annule par le prestataire. Pénalité + redispatch (via Phase 11 si dispo).
     */
    public function cancelByProvider(Booking $booking, User $provider, ?string $reason = null): array
    {
        $this->guardCanCancel($booking);

        $penalty = $this->calculator->forProviderCancellation($booking);

        return DB::transaction(function () use ($booking, $provider, $reason, $penalty) {
            // Le booking redevient "en_attente" pour redispatch
            $booking->update([
                'status'              => 'en_attente',
                'cancellation_reason' => "Annulé par prestataire: " . ($reason ?? 'non précisé'),
                'metadata'            => array_merge($booking->metadata ?? [], [
                    'provider_cancellation_at'    => now()->toIso8601String(),
                    'provider_cancellation_user'  => $provider->id,
                    'provider_penalty_eur'        => $penalty['penalty_eur'],
                    'provider_reliability_penalty'=> $penalty['reliability_penalty'],
                ]),
            ]);

            // Redispatch via Phase 11 si dispo
            $this->tryRedispatch($booking);

            // Application de la pénalité reliability sur ProviderProfile
            if (! $penalty['is_free']) {
                $this->applyProviderPenalty($provider, $penalty);
            }

            Log::info('CancelBookingService: provider cancellation', [
                'booking_id'  => $booking->id,
                'provider_id' => $provider->id,
                'penalty'     => $penalty,
            ]);

            return [
                'ok'           => true,
                'booking_id'   => $booking->id,
                'penalty'      => $penalty,
            ];
        });
    }

    /**
     * Marque un no-show client (le client n'est pas venu).
     * Utilisé par le prestataire après X min d'attente.
     */
    public function markClientNoShow(Booking $booking, User $reportedBy): array
    {
        if (! $this->calculator->isNoShow($booking)) {
            throw new \DomainException('Trop tôt pour déclarer un no-show.');
        }

        $bookingPrice = (float) ($booking->estimated_price ?? 0);
        $feePercent = (int) config('cancellation.no_show.client_fee_percent', 100);
        $feeAmount = round($bookingPrice * ($feePercent / 100), 2);

        return DB::transaction(function () use ($booking, $reportedBy, $feeAmount, $feePercent) {
            $booking->update([
                'status'              => 'annule',
                'cancelled_at'        => now(),
                'cancelled_by'        => $reportedBy->id,
                'cancellation_reason' => 'Client no-show',
                'metadata'            => array_merge($booking->metadata ?? [], [
                    'no_show_type'           => 'client',
                    'no_show_reported_by'    => $reportedBy->id,
                    'cancellation_fee'       => $feeAmount,
                    'cancellation_fee_percent' => $feePercent,
                ]),
            ]);

            // Capture totale du paiement (le client paie 100%)
            $this->tryCaptureFull($booking);

            return [
                'ok'         => true,
                'fee_amount' => $feeAmount,
                'type'       => 'client_no_show',
            ];
        });
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    protected function guardCanCancel(Booking $booking): void
    {
        $finalStatuses = ['termine', 'completed', 'annule', 'cancelled', 'refuse'];
        if (in_array((string) $booking->status, $finalStatuses, true)) {
            throw new \DomainException(
                "Booking dans un statut final non annulable: {$booking->status}"
            );
        }
    }

    /**
     * Tente un refund partiel via Phase 13 si dispo.
     * Le client est remboursé (prix - fee).
     */
    protected function tryRefundPartial(Booking $booking, array $feeDetails): void
    {
        if (! $booking->stripe_payment_intent_id) return;
        if (! in_array($booking->payment_status, ['authorized', 'captured'], true)) return;

        $service = '\App\Services\Payments\StripeConnectPaymentService';
        if (! class_exists($service)) {
            Log::info('CancelBookingService: pas de Phase 13 — refund manuel requis', [
                'booking_id' => $booking->id,
            ]);
            return;
        }

        $bookingPrice = (float) ($booking->estimated_price ?? 0);
        $feeAmount = (float) $feeDetails['fee_amount'];
        $refundAmount = max(0, $bookingPrice - $feeAmount);

        if ($refundAmount <= 0) return;

        $refundCents = (int) round($refundAmount * 100);

        try {
            app($service)->refundMissionPayment($booking, $refundCents, 'requested_by_customer');
        } catch (\Throwable $e) {
            Log::error('CancelBookingService: refund échoué', [
                'booking_id' => $booking->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    protected function tryCaptureFull(Booking $booking): void
    {
        if ($booking->payment_status !== 'authorized') return;

        $missionService = '\App\Services\Payments\MissionPaymentService';
        if (! class_exists($missionService)) return;

        try {
            app($missionService)->capture($booking);
        } catch (\Throwable $e) {
            Log::error('CancelBookingService: capture no-show échouée', [
                'booking_id' => $booking->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Tente de redispatcher via Phase 11 si dispo.
     */
    protected function tryRedispatch(Booking $booking): void
    {
        $mission = $booking->missions()->latest()->first();
        if (! $mission) return;

        $dispatchService = '\App\Services\Dispatch\MissionDispatchService';
        if (! class_exists($dispatchService)) return;

        try {
            app($dispatchService)->dispatchToNextProvider($mission);
        } catch (\Throwable $e) {
            Log::warning('CancelBookingService: redispatch échoué', [
                'booking_id' => $booking->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Applique la pénalité reliability sur ProviderProfile.metadata.
     */
    protected function applyProviderPenalty(User $provider, array $penalty): void
    {
        $profile = $provider->providerProfile;
        if (! $profile) return;

        $metadata = $profile->metadata ?? [];
        $currentPenalty = (int) ($metadata['reliability_penalty_total'] ?? 0);
        $cancelCount30d = (int) ($metadata['cancellations_30d_count'] ?? 0);

        $metadata['reliability_penalty_total'] = $currentPenalty + (int) $penalty['reliability_penalty'];
        $metadata['cancellations_30d_count'] = $cancelCount30d + 1;
        $metadata['last_cancellation_at'] = now()->toIso8601String();

        $profile->update(['metadata' => $metadata]);

        // Si dépasse le seuil, log warning admin
        $maxAllowed = (int) config('cancellation.provider.max_cancellations_per_30d', 5);
        if ($metadata['cancellations_30d_count'] >= $maxAllowed) {
            Log::warning('CancelBookingService: provider above cancellation threshold', [
                'provider_id'              => $provider->id,
                'cancellations_30d_count'  => $metadata['cancellations_30d_count'],
                'threshold'                => $maxAllowed,
            ]);
        }
    }
}
