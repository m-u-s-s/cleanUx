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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
 */
class AvailabilityService
{
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
        if ($this->hasOverlappingBooking($provider->id, $startsAt, $endsAt)) {
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
                $blockStart = CarbonImmutable::parse($day->format('Y-m-d') . ' ' . $partial->start_time);
                $blockEnd = CarbonImmutable::parse($day->format('Y-m-d') . ' ' . $partial->end_time);
                $dayWindows = $this->subtractRange($dayWindows, $blockStart, $blockEnd);
            }

            // Also subtract existing confirmed bookings (no double-booking)
            foreach ($this->getProviderBookings($provider->id, $day) as $busy) {
                $dayWindows = $this->subtractRange($dayWindows, $busy['start'], $busy['end']);
            }

            // And subtract active holds
            foreach ($this->getProviderHolds($provider->id, $day) as $hold) {
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
            'start' => CarbonImmutable::parse($base . ' ' . $startTime),
            'end' => CarbonImmutable::parse($base . ' ' . $endTime),
        ];
    }

    /**
     * Subtracts the [blockStart, blockEnd] range from a set of windows.
     * Returns the remaining windows.
     *
     * @param array<int, array{start:CarbonImmutable, end:CarbonImmutable}> $windows
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

    /**
     * @return array<int, array{start:CarbonImmutable, end:CarbonImmutable}>
     */
    protected function getProviderBookings(int $providerId, CarbonImmutable $day): array
    {
        $startCol = $this->resolveBookingStartColumn();
        if (! $startCol) {
            return [];
        }

        $dayStart = $day->copy()->startOfDay();
        $dayEnd = $day->copy()->endOfDay();

        $providerCols = $this->resolveBookingProviderColumns();
        if (empty($providerCols)) {
            return [];
        }

        $rows = DB::table('bookings')
            ->where(function ($q) use ($providerCols, $providerId) {
                foreach ($providerCols as $col) {
                    $q->orWhere($col, $providerId);
                }
            })
            ->whereNotIn('status', ['annule', 'cancelled', 'canceled', 'rejected'])
            ->whereBetween($startCol, [$dayStart, $dayEnd])
            ->get();

        $endCol = $this->resolveBookingEndColumn();

        $out = [];
        foreach ($rows as $row) {
            $start = $this->safeParse($row->{$startCol} ?? null);
            $end = $endCol ? $this->safeParse($row->{$endCol} ?? null) : null;
            if ($start && ! $end) {
                $end = $start->copy()->addHours(2);  // fallback duration
            }
            if ($start && $end) {
                $out[] = ['start' => $start, 'end' => $end];
            }
        }
        return $out;
    }

    /**
     * @return array<int, array{start:CarbonImmutable, end:CarbonImmutable}>
     */
    protected function getProviderHolds(int $providerId, CarbonImmutable $day): array
    {
        $dayStart = $day->copy()->startOfDay();
        $dayEnd = $day->copy()->endOfDay();

        $rows = AvailabilityHold::query()
            ->forProvider($providerId)
            ->active()
            ->where('starts_at', '<', $dayEnd)
            ->where('ends_at', '>', $dayStart)
            ->get();

        $out = [];
        foreach ($rows as $h) {
            $out[] = [
                'start' => CarbonImmutable::instance($h->starts_at),
                'end' => CarbonImmutable::instance($h->ends_at),
            ];
        }
        return $out;
    }

    protected function hasOverlappingBooking(int $providerId, CarbonImmutable $startsAt, CarbonImmutable $endsAt): bool
    {
        $startCol = $this->resolveBookingStartColumn();
        if (! $startCol) {
            return false;
        }

        $endCol = $this->resolveBookingEndColumn();
        $providerCols = $this->resolveBookingProviderColumns();
        if (empty($providerCols)) {
            return false;
        }

        $q = DB::table('bookings')
            ->where(function ($inner) use ($providerCols, $providerId) {
                foreach ($providerCols as $col) {
                    $inner->orWhere($col, $providerId);
                }
            })
            ->whereNotIn('status', ['annule', 'cancelled', 'canceled', 'rejected'])
            ->where($startCol, '<', $endsAt);

        if ($endCol) {
            $q->where($endCol, '>', $startsAt);
        } else {
            $q->where($startCol, '>=', $startsAt->copy()->subHours(4));
        }

        return $q->exists();
    }

    protected function resolveBookingStartColumn(): ?string
    {
        if (! Schema::hasTable('bookings')) {
            return null;
        }
        foreach (['start_at', 'starts_at', 'planned_start_at', 'scheduled_at'] as $col) {
            if (Schema::hasColumn('bookings', $col)) {
                return $col;
            }
        }
        return null;
    }

    protected function resolveBookingEndColumn(): ?string
    {
        if (! Schema::hasTable('bookings')) {
            return null;
        }
        foreach (['end_at', 'ends_at', 'planned_end_at', 'mission_finished_at'] as $col) {
            if (Schema::hasColumn('bookings', $col)) {
                return $col;
            }
        }
        return null;
    }

    /**
     * @return array<int,string>
     */
    protected function resolveBookingProviderColumns(): array
    {
        if (! Schema::hasTable('bookings')) {
            return [];
        }
        $candidates = ['employe_id', 'provider_user_id', 'assigned_provider_user_id', 'assigned_employee_id'];
        return array_values(array_filter(
            $candidates,
            fn ($c) => Schema::hasColumn('bookings', $c),
        ));
    }

    protected function safeParse(mixed $value): ?CarbonImmutable
    {
        if (! $value) {
            return null;
        }
        try {
            return CarbonImmutable::parse((string) $value);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
