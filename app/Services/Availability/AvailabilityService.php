<?php

namespace App\Services\Availability;

use App\Models\AvailabilityException;
use App\Models\AvailabilityHold;
use App\Models\AvailabilitySlot;
use App\Models\User;
use App\Support\ActivityLogger;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Config;

/**
 * AvailabilityService (Phase Availability v2).
 *
 * Calcule les fenêtres disponibles d'un provider à partir de :
 *   - templates récurrents (AvailabilitySlot par weekday)
 *   - overrides par date (AvailabilityException : closed / open_override / partial)
 *   - holds actifs (AvailabilityHold : soft-lock pendant booking flow)
 *   - bookings existants (table bookings : statut confirmé/encours/etc.)
 *
 * Toutes les comparaisons sont faites en UTC interne ; les inputs/outputs
 * gardent le tz du slot (ou default si absent).
 *
 * Data-access + schema-defensive logic is delegated to AvailabilityDataAccess.
 */
class AvailabilityService
{
    public function __construct(protected AvailabilityDataAccess $dataAccess) {}

    public function isAvailable(User $provider, \DateTimeInterface $startsAt, \DateTimeInterface $endsAt): bool
    {
        if (! Config::get('availability.enabled', true)) {
            return true;  // module désactivé → toujours dispo (fallback historique)
        }

        $startsAt = CarbonImmutable::instance($startsAt);
        $endsAt = CarbonImmutable::instance($endsAt);

        if ($endsAt <= $startsAt) {
            return false;
        }

        // 1) Au moins un slot/exception couvre la fenêtre demandée
        $windows = $this->getAvailableWindows($provider, $startsAt, $endsAt);

        $covered = false;
        foreach ($windows as $w) {
            if ($w['start'] <= $startsAt && $w['end'] >= $endsAt) {
                $covered = true;
                break;
            }
        }
        if (! $covered) {
            return false;
        }

        // 2) Aucun hold actif overlap
        $hold = AvailabilityHold::query()
            ->forProvider($provider->id)
            ->active()
            ->overlapping($startsAt, $endsAt)
            ->exists();
        if ($hold) {
            return false;
        }

        // 3) Aucun booking confirmé/en cours overlap
        if ($this->dataAccess->hasOverlappingBooking($provider->id, $startsAt, $endsAt)) {
            return false;
        }

        return true;
    }

    /**
     * Computes available windows between `$from` and `$to` for a provider.
     *
     * @return array<int, array{start: CarbonImmutable, end: CarbonImmutable}>
     */
    public function getAvailableWindows(User $provider, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $maxLookahead = (int) Config::get('availability.max_lookahead_days', 90);
        $from = CarbonImmutable::instance($from);
        $to = CarbonImmutable::instance($to);

        $hardCap = $from->copy()->addDays($maxLookahead);
        if ($to > $hardCap) {
            $to = $hardCap;
        }

        $slots = AvailabilitySlot::query()
            ->forProvider($provider->id)
            ->active()
            ->get();

        $exceptions = AvailabilityException::query()
            ->forProvider($provider->id)
            ->between($from, $to)
            ->get()
            ->groupBy(fn ($e) => $e->date->format('Y-m-d'));

        $windows = [];

        $period = CarbonPeriod::create($from->copy()->startOfDay(), '1 day', $to->copy()->endOfDay());

        foreach ($period as $day) {
            $day = CarbonImmutable::instance($day);
            $dayKey = $day->format('Y-m-d');
            $dayExceptions = $exceptions->get($dayKey, collect());

            // If any "closed" exception today → fully closed
            $closed = $dayExceptions->firstWhere('exception_type', AvailabilityException::TYPE_CLOSED);
            if ($closed) {
                continue;
            }

            // "open_override" exception replaces slots entirely for that day
            $openOverride = $dayExceptions->firstWhere('exception_type', AvailabilityException::TYPE_OPEN_OVERRIDE);
            if ($openOverride && $openOverride->start_time && $openOverride->end_time) {
                $windows[] = $this->buildWindow($day, $openOverride->start_time, $openOverride->end_time);

                continue;
            }

            // Normal: apply weekly slots matching this day
            $dayWindows = [];
            foreach ($slots as $slot) {
                if (! $slot->appliesOn($day)) {
                    continue;
                }
                $dayWindows[] = $this->buildWindow($day, $slot->start_time, $slot->end_time);
            }

            // "partial" exception subtracts a time range from the day's windows
            foreach ($dayExceptions->where('exception_type', AvailabilityException::TYPE_PARTIAL) as $partial) {
                if (! $partial->start_time || ! $partial->end_time) {
                    continue;
                }
                $blockStart = CarbonImmutable::parse($day->format('Y-m-d').' '.$partial->start_time);
                $blockEnd = CarbonImmutable::parse($day->format('Y-m-d').' '.$partial->end_time);
                $dayWindows = $this->subtractRange($dayWindows, $blockStart, $blockEnd);
            }

            // Also subtract existing confirmed bookings (no double-booking)
            foreach ($this->dataAccess->getProviderBookings($provider->id, $day) as $busy) {
                $dayWindows = $this->subtractRange($dayWindows, $busy['start'], $busy['end']);
            }

            // And subtract active holds
            foreach ($this->dataAccess->getProviderHolds($provider->id, $day) as $hold) {
                $dayWindows = $this->subtractRange($dayWindows, $hold['start'], $hold['end']);
            }

            // Clip windows to the [from, to] requested boundary
            foreach ($dayWindows as $w) {
                $start = max($w['start'], $from);
                $end = min($w['end'], $to);
                if ($end > $start) {
                    $windows[] = ['start' => $start, 'end' => $end];
                }
            }
        }

        return $windows;
    }

    public function createHold(
        User $provider,
        \DateTimeInterface $startsAt,
        \DateTimeInterface $endsAt,
        ?int $bookingId = null,
        ?string $reason = 'booking_flow',
        ?string $idempotencyKey = null,
    ): AvailabilityHold {
        $startsAt = CarbonImmutable::instance($startsAt);
        $endsAt = CarbonImmutable::instance($endsAt);

        if ($idempotencyKey) {
            $existing = AvailabilityHold::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        $hold = AvailabilityHold::create([
            'provider_user_id' => $provider->id,
            'booking_id' => $bookingId,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'reason' => $reason ?? 'booking_flow',
            'expires_at' => now()->addMinutes((int) Config::get('availability.hold_duration_minutes', 10)),
            'idempotency_key' => $idempotencyKey,
        ]);

        ActivityLogger::log('availability.hold_created', $hold, [
            'provider_id' => $provider->id,
            'booking_id' => $bookingId,
        ]);

        return $hold;
    }

    public function releaseHold(AvailabilityHold $hold): void
    {
        if ($hold->released_at) {
            return;
        }
        $hold->forceFill(['released_at' => now()])->save();

        ActivityLogger::log('availability.hold_released', $hold, [
            'provider_id' => $hold->provider_user_id,
        ]);
    }

    /**
     * Cleanup expired holds (called from a scheduled command).
     */
    public function cleanupExpiredHolds(): int
    {
        return AvailabilityHold::query()
            ->whereNull('released_at')
            ->where('expires_at', '<=', now())
            ->update(['released_at' => now()]);
    }

    protected function buildWindow(\DateTimeInterface $day, string $startTime, string $endTime): array
    {
        $base = $day->format('Y-m-d');

        return [
            'start' => CarbonImmutable::parse($base.' '.$startTime),
            'end' => CarbonImmutable::parse($base.' '.$endTime),
        ];
    }

    /**
     * Subtracts the [blockStart, blockEnd] range from a set of windows.
     * Returns the remaining windows.
     *
     * @param  array<int, array{start:CarbonImmutable, end:CarbonImmutable}>  $windows
     * @return array<int, array{start:CarbonImmutable, end:CarbonImmutable}>
     */
    protected function subtractRange(array $windows, CarbonImmutable $blockStart, CarbonImmutable $blockEnd): array
    {
        $result = [];
        foreach ($windows as $w) {
            if ($blockEnd <= $w['start'] || $blockStart >= $w['end']) {
                $result[] = $w;

                continue;
            }
            if ($blockStart > $w['start']) {
                $result[] = ['start' => $w['start'], 'end' => $blockStart];
            }
            if ($blockEnd < $w['end']) {
                $result[] = ['start' => $blockEnd, 'end' => $w['end']];
            }
        }

        return $result;
    }
}
