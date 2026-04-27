<?php

namespace App\Services\Booking;

use App\Models\RendezVous;
use App\Models\ServiceZone;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class EmployeeAvailabilityService
{
    public function eligibleEmployeesQuery(?int $zoneId = null): Builder
    {
        $query = User::query()
            ->where('role', 'employe')
            ->where('is_active', true);

        if (! $zoneId) {
            return $query->orderBy('name');
        }

        $now = now();

        return $query
            ->where(function ($employeeQuery) use ($zoneId, $now) {
                $employeeQuery
                    ->where('primary_service_zone_id', $zoneId)
                    ->orWhereHas('zoneAssignments', function ($assignmentQuery) use ($zoneId, $now) {
                        $assignmentQuery
                            ->where('service_zone_id', $zoneId)
                            ->where('is_active', true)
                            ->where(function ($q) use ($now) {
                                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
                            })
                            ->where(function ($q) use ($now) {
                                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
                            });
                    });
            })
            ->with(['zoneAssignments' => function ($query) use ($zoneId) {
                $query->where('service_zone_id', $zoneId)->orderByDesc('coverage_priority');
            }])
            ->orderByRaw('CASE WHEN primary_service_zone_id = ? THEN 0 ELSE 1 END', [$zoneId])
            ->orderBy('name');
    }

    public function employeeCoverageScore(User $employee, int $zoneId): int
    {
        $score = $employee->primary_service_zone_id === $zoneId ? 1000 : 0;
        $assignment = $employee->zoneAssignments->firstWhere('service_zone_id', $zoneId);

        if ($assignment) {
            $typeBonus = match ($assignment->assignment_type) {
                'primary' => 300,
                'secondary' => 200,
                'backup' => 100,
                default => 50,
            };

            $score += $typeBonus + (int) ($assignment->coverage_priority ?? 0);
        }

        return $score;
    }

    public function sortedEligibleEmployeesForZone(int $zoneId): Collection
    {
        return $this->eligibleEmployeesQuery($zoneId)
            ->get()
            ->sortByDesc(fn(User $employee) => $this->employeeCoverageScore($employee, $zoneId))
            ->values();
    }

    public function employeeCanCoverZone(int $employeeId, int $zoneId): bool
    {
        $now = now();

        return User::query()
            ->whereKey($employeeId)
            ->where('role', 'employe')
            ->where('is_active', true)
            ->where(function ($employeeQuery) use ($zoneId, $now) {
                $employeeQuery
                    ->where('primary_service_zone_id', $zoneId)
                    ->orWhereHas('zoneAssignments', function ($assignmentQuery) use ($zoneId, $now) {
                        $assignmentQuery
                            ->where('service_zone_id', $zoneId)
                            ->where('is_active', true)
                            ->where(function ($q) use ($now) {
                                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
                            })
                            ->where(function ($q) use ($now) {
                                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
                            });
                    });
            })
            ->exists();
    }

    public function employeeIsAvailableForSlot(
        int $employeeId,
        string $date,
        string $heure,
        ?ServiceZone $zone = null,
        int $estimatedDuration = 90,
        ?int $ignoreRendezVousId = null,
    ): bool {
        $timezone = config('app.timezone', 'Europe/Brussels');
        $bufferMinutes = (int) ($zone?->time_buffer_minutes ?? 0);
        $estimatedDuration = max(30, $estimatedDuration);

        $slotStart = Carbon::createFromFormat('Y-m-d H:i', $date . ' ' . $heure, $timezone);
        $slotEnd = $slotStart->copy()->addMinutes($estimatedDuration);

        $employee = User::query()->whereKey($employeeId)->first();

        if (! $employee) {
            return false;
        }

        $availabilities = $employee->disponibilites()
            ->whereDate('date', $date)
            ->get();

        if ($availabilities->isNotEmpty()) {
            $withinAvailability = $availabilities->contains(function ($availability) use ($slotStart, $slotEnd, $date, $timezone) {
                $availableStart = Carbon::createFromFormat('Y-m-d H:i:s', $date . ' ' . $availability->heure_debut, $timezone);
                $availableEnd = Carbon::createFromFormat('Y-m-d H:i:s', $date . ' ' . $availability->heure_fin, $timezone);

                return $slotStart->greaterThanOrEqualTo($availableStart)
                    && $slotEnd->lessThanOrEqualTo($availableEnd);
            });

            if (! $withinAvailability) {
                return false;
            }
        }

        $activeStatuses = ['en_attente', 'confirme', 'en_route', 'sur_place'];

        return ! RendezVous::query()
            ->where('employe_id', $employeeId)
            ->whereDate('date', $date)
            ->whereIn('status', $activeStatuses)
            ->get()
            ->contains(function ($existing) use ($slotStart, $slotEnd, $bufferMinutes, $timezone) {
                $existingStart = Carbon::createFromFormat('Y-m-d H:i', $existing->date->format('Y-m-d') . ' ' . substr((string) $existing->heure, 0, 5), $timezone);
                $existingDuration = max(30, (int) ($existing->duree_estimee ?: $existing->duree ?: 90));
                $existingEnd = $existingStart->copy()->addMinutes($existingDuration);

                return $slotStart->lt($existingEnd->copy()->addMinutes($bufferMinutes))
                    && $slotEnd->gt($existingStart->copy()->subMinutes($bufferMinutes));
            });
    }

    public function resolveBestAvailableEmployeeForSlot(
        string $date,
        string $heure,
        ServiceZone $zone,
        int $estimatedDuration = 90,
        ?int $ignoreRendezVousId = null,
    ): ?User {
        return $this->sortedEligibleEmployeesForZone((int) $zone->id)
            ->first(fn(User $employee) => $this->employeeIsAvailableForSlot(
                $employee->id,
                $date,
                $heure,
                $zone,
                $estimatedDuration,
                $ignoreRendezVousId,
            ));
    }
}
