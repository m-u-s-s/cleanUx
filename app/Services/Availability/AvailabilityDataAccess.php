<?php

namespace App\Services\Availability;

use App\Models\AvailabilityHold;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** AvailabilityDataAccess Cohesive sub-service extracted from AvailabilityService. */
class AvailabilityDataAccess
{
    /**
     * @return array<int, array{start:CarbonImmutable, end:CarbonImmutable}>
     */
    public function getProviderBookings(int $providerId, CarbonImmutable $day): array
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
    public function getProviderHolds(int $providerId, CarbonImmutable $day): array
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

    public function hasOverlappingBooking(int $providerId, CarbonImmutable $startsAt, CarbonImmutable $endsAt): bool
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

    public function resolveBookingStartColumn(): ?string
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

    public function resolveBookingEndColumn(): ?string
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
    public function resolveBookingProviderColumns(): array
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

    public function safeParse(mixed $value): ?CarbonImmutable
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
