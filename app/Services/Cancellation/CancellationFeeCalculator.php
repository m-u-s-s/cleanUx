<?php

namespace App\Services\Cancellation;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionAssignment;
use Carbon\Carbon;

/** Penalite de desistement prestataire et detection de non-presentation. Sans acces DB. */
class CancellationFeeCalculator
{
    /** Calcul de pénalité pour annulation par prestataire. */
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
                'penalty_eur' => 0.0,
                'reliability_penalty' => 0,
                'is_free' => true,
                'minutes_before_start' => $minutesBeforeStart,
                'reason_code' => 'free_window',
            ];
        }

        return [
            'penalty_eur' => (float) ($config['penalty_eur'] ?? 0),
            'reliability_penalty' => (int) ($config['reliability_penalty'] ?? 0),
            'is_free' => false,
            'minutes_before_start' => $minutesBeforeStart,
            'reason_code' => 'late_cancellation',
        ];
    }

    /** Détection no-show (passé X min après planned_start_at sans arrivée). */
    public function isNoShow(Booking $booking, ?Carbon $now = null): bool
    {
        $now = $now ?? now();

        // SUR UNE COURSE, LE DÉCOMPTE PART DE L'ARRIVÉE, PAS DE L'HORAIRE.
        if ($booking->estUneCourse()) {
            $arrivee = $this->arriveeAuPointDePriseEnCharge($booking);

            if (! $arrivee) {
                return false;
            }

            $attente = (int) config(
                'cancellation.no_show.ride_grace_minutes',
                config('cancellation.no_show.grace_minutes', 15),
            );

            return $now->greaterThanOrEqualTo($arrivee->copy()->addMinutes($attente));
        }

        $start = $this->bookingStartDateTime($booking);
        if (! $start) {
            return false;
        }

        $graceMinutes = (int) config('cancellation.no_show.grace_minutes', 15);

        return $now->greaterThanOrEqualTo($start->copy()->addMinutes($graceMinutes));
    }

    /** Quand le prestataire a signalé son arrivée au point de prise en charge. */
    protected function arriveeAuPointDePriseEnCharge(Booking $booking): ?Carbon
    {
        $arrivee = MissionAssignment::query()
            ->whereIn('mission_id', Mission::query()->where('booking_id', $booking->id)->select('id'))
            ->whereNotNull('arrived_at')
            ->orderByDesc('arrived_at')
            ->value('arrived_at');

        return $arrivee ? Carbon::parse($arrivee) : null;
    }

    /** Combine scheduled_date + scheduled_time → Carbon. */
    protected function bookingStartDateTime(Booking $booking): ?Carbon
    {
        if (! $booking->scheduled_date) {
            return null;
        }

        try {
            $date = $booking->scheduled_date instanceof Carbon
                ? $booking->scheduled_date->copy()
                : Carbon::parse($booking->scheduled_date);

            if ($booking->scheduled_time) {
                if ($booking->scheduled_time instanceof \DateTimeInterface) {
                    $h = (int) $booking->scheduled_time->format('H');
                    $m = (int) $booking->scheduled_time->format('i');
                } else {
                    $time = (string) $booking->scheduled_time;
                    // Si la chaîne contient une date complète (cas SQLite "Y-m-d H:i:s"),
                    // on ne garde que la partie heure.
                    if (preg_match('/(\d{1,2}):(\d{2})(?::\d{2})?\s*$/', $time, $m1)) {
                        $h = (int) $m1[1];
                        $m = (int) $m1[2];
                    } else {
                        $parts = explode(':', $time);
                        $h = (int) ($parts[0] ?? 0);
                        $m = (int) ($parts[1] ?? 0);
                    }
                }

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
        if ($min > 0 && $fee < $min) {
            return $min;
        }

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
            'fee_amount' => round($amount, 2),
            'fee_percent' => $percent,
            'tier_matched' => $tier,
            'is_free' => $isFree,
            'minutes_before_start' => $minutesBefore,
            'reason_code' => $reasonCode,
        ];
    }
}
