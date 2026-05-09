<?php

namespace App\Services\Cancellation;

use App\Models\Booking;
use Carbon\Carbon;

/**
 * Phase 14 — Calcul des frais d'annulation selon délai.
 *
 * Pure function : ne touche PAS la DB. Renvoie un détail structuré pour
 * traçabilité.
 *
 * Rules dans config/cancellation.php — ajustables sans toucher au code.
 */
class CancellationFeeCalculator
{
    /**
     * Calcule le fee à appliquer pour une annulation par le client.
     *
     * Renvoie :
     *   - fee_amount: float (en euros)
     *   - fee_percent: int (% appliqué)
     *   - tier_matched: array (le tier qui a matché)
     *   - is_free: bool
     *   - minutes_before_start: int|null
     *   - reason_code: 'free_within_grace'|'tier_<n>'|'no_show'
     */
    public function forClientCancellation(Booking $booking, ?Carbon $cancelledAt = null): array
    {
        $cancelledAt = $cancelledAt ?? now();
        $config = config('cancellation.client');
        $bookingPrice = (float) ($booking->estimated_price ?? $booking->payment_amount_cents / 100 ?? 0);

        $start = $this->bookingStartDateTime($booking);

        // 1. Fenêtre de grâce après création (pour les ASAP surtout)
        $createdAt = $booking->created_at;
        if ($createdAt) {
            $minutesSinceCreation = abs($cancelledAt->diffInMinutes($createdAt, true));
            $graceMinutes = (int) ($config['free_cancellation_minutes'] ?? 0);
            if ($graceMinutes > 0 && $minutesSinceCreation <= $graceMinutes) {
                return $this->result(0.0, 0, null, true, null, 'free_within_grace');
            }
        }

        // 2. Si pas de date de start, on traite comme annulation tardive
        if (! $start) {
            $tier = $config['fee_tiers'][count($config['fee_tiers']) - 1] ?? ['fee_percent' => 100];
            return $this->result(
                $this->applyMinimumFee($bookingPrice * ($tier['fee_percent'] / 100), $config),
                (int) $tier['fee_percent'],
                $tier,
                false,
                null,
                'tier_default'
            );
        }

        $minutesBeforeStart = (int) $cancelledAt->diffInMinutes($start, false);

        // 3. Match les tiers (1er match wins, évalués par ordre décroissant)
        foreach ($config['fee_tiers'] as $i => $tier) {
            $matches = false;

            if (isset($tier['min_hours_before'])) {
                $minutesNeeded = $tier['min_hours_before'] * 60;
                if ($minutesBeforeStart >= $minutesNeeded) {
                    $matches = true;
                }
            } elseif (isset($tier['min_minutes_before'])) {
                if ($minutesBeforeStart >= $tier['min_minutes_before']) {
                    $matches = true;
                }
            }

            if ($matches) {
                $feePercent = (int) $tier['fee_percent'];
                $fee = $bookingPrice * ($feePercent / 100);
                $fee = $this->applyMinimumFee($fee, $config);

                return $this->result(
                    round($fee, 2),
                    $feePercent,
                    $tier,
                    $feePercent === 0 && $fee === 0.0,
                    $minutesBeforeStart,
                    "tier_{$i}",
                );
            }
        }

        // 4. Aucun tier matché : fallback 100%
        $fee = $this->applyMinimumFee($bookingPrice, $config);
        return $this->result(round($fee, 2), 100, null, false, $minutesBeforeStart, 'tier_fallback');
    }

    /**
     * Calcul de pénalité pour annulation par prestataire.
     */
    public function forProviderCancellation(Booking $booking, ?Carbon $cancelledAt = null): array
    {
        $cancelledAt = $cancelledAt ?? now();
        $config = config('cancellation.provider');

        $start = $this->bookingStartDateTime($booking);
        $minutesBeforeStart = $start ? (int) $cancelledAt->diffInMinutes($start, false) : 0;

        // Plus de X min avant → pas de pénalité
        $freeMinutes = (int) ($config['free_cancellation_minutes'] ?? 30);
        if ($start && $minutesBeforeStart >= $freeMinutes) {
            return [
                'penalty_eur'           => 0.0,
                'reliability_penalty'   => 0,
                'is_free'               => true,
                'minutes_before_start'  => $minutesBeforeStart,
                'reason_code'           => 'free_window',
            ];
        }

        return [
            'penalty_eur'           => (float) ($config['penalty_eur'] ?? 0),
            'reliability_penalty'   => (int) ($config['reliability_penalty'] ?? 0),
            'is_free'               => false,
            'minutes_before_start'  => $minutesBeforeStart,
            'reason_code'           => 'late_cancellation',
        ];
    }

    /**
     * Détection no-show (passé X min après planned_start_at sans arrivée).
     */
    public function isNoShow(Booking $booking, ?Carbon $now = null): bool
    {
        $now = $now ?? now();
        $start = $this->bookingStartDateTime($booking);
        if (! $start) return false;

        $graceMinutes = (int) config('cancellation.no_show.grace_minutes', 15);
        return $now->greaterThanOrEqualTo($start->copy()->addMinutes($graceMinutes));
    }

    /**
     * Combine scheduled_date + scheduled_time → Carbon.
     */
    protected function bookingStartDateTime(Booking $booking): ?Carbon
    {
        if (! $booking->scheduled_date) return null;

        try {
            $date = $booking->scheduled_date instanceof Carbon
                ? $booking->scheduled_date->copy()
                : Carbon::parse($booking->scheduled_date);

            if ($booking->scheduled_time) {
                $time = (string) $booking->scheduled_time;
                $parts = explode(':', $time);
                $h = (int) ($parts[0] ?? 0);
                $m = (int) ($parts[1] ?? 0);
                $date->setTime($h, $m);
            }

            return $date;
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function applyMinimumFee(float $fee, array $config): float
    {
        $min = (float) ($config['minimum_fee_eur'] ?? 0);
        if ($min > 0 && $fee < $min) return $min;
        return $fee;
    }

    protected function result(
        float $amount,
        int $percent,
        ?array $tier,
        bool $isFree,
        ?int $minutesBefore,
        string $reasonCode,
    ): array {
        return [
            'fee_amount'           => round($amount, 2),
            'fee_percent'          => $percent,
            'tier_matched'         => $tier,
            'is_free'              => $isFree,
            'minutes_before_start' => $minutesBefore,
            'reason_code'          => $reasonCode,
        ];
    }
}
