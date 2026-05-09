<?php

namespace App\Actions\Booking;

use App\Models\Booking;
use App\Models\User;
use App\Services\Booking\EmployeeAvailabilityService;
use App\Support\ActivityLogger;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class UpdateRecurringSeriesAction
{
    public function __construct(
        protected EmployeeAvailabilityService $employeeAvailabilityService,
    ) {
    }

    public function execute(Booking $anchor, array $data, string $scope = 'occurrence'): Collection
    {
        if (! $anchor->recurring_series_id) {
            throw ValidationException::withMessages([
                'series' => 'Ce rendez-vous n’appartient à aucune série récurrente.',
            ]);
        }

        $targets = $this->resolveTargets($anchor, $scope);
        $anchorDate = optional($anchor->date)?->copy() ?? Carbon::parse($anchor->date);
        $newDate = ! empty($data['date']) ? Carbon::parse($data['date']) : null;
        $dayShift = $newDate ? $anchorDate->diffInDays($newDate, false) : 0;
        $newHour = $data['heure'] ?? null;
        $newEmployeeId = array_key_exists('employe_id', $data) ? $data['employe_id'] : null;
        $estimatedDuration = max(30, (int) ($anchor->duree_estimee ?: $anchor->duree ?: 90));

        foreach ($targets as $target) {
            $targetDate = optional($target->date)?->copy() ?? Carbon::parse($target->date);
            if ($newDate && $scope !== 'occurrence') {
                $targetDate = $targetDate->addDays($dayShift);
            } elseif ($newDate) {
                $targetDate = $newDate->copy();
            }

            $targetHour = $newHour ?: substr((string) $target->heure, 0, 5);
            $employeeId = $newEmployeeId ?: $target->employe_id;

            if ($employeeId) {
                if (! $this->employeeAvailabilityService->employeeCanCoverZone((int) $employeeId, (int) $target->service_zone_id)) {
                    throw ValidationException::withMessages([
                        'employe_id' => 'Un employé sélectionné ne couvre pas la zone pour la portée choisie.',
                    ]);
                }

                if (! $this->employeeAvailableIgnoringTargets((int) $employeeId, $targetDate->toDateString(), $targetHour, $target, $estimatedDuration, $targets->pluck('id')->all())) {
                    throw ValidationException::withMessages([
                        'heure' => 'Au moins une occurrence n’est pas disponible pour cet employé.',
                    ]);
                }
            }
        }

        $updated = collect();

        foreach ($targets as $target) {
            $original = [
                'date' => $target->date,
                'heure' => $target->heure,
                'status' => $target->status,
                'priorite' => $target->priorite,
            ];

            if ($newDate && $scope !== 'occurrence') {
                $target->date = (optional($target->date)?->copy() ?? Carbon::parse($target->date))->addDays($dayShift)->toDateString();
            } elseif ($newDate) {
                $target->date = $newDate->toDateString();
            }

            if ($newHour) {
                $target->heure = $newHour;
            }

            if ($newEmployeeId) {
                $target->employe_id = (int) $newEmployeeId;
            }

            if (! empty($data['priorite'])) {
                $target->priorite = $data['priorite'];
            }

            if (! empty($data['status'])) {
                $target->status = $data['status'];
            } elseif (! in_array($target->status, ['refuse', 'termine'], true)) {
                $target->status = 'en_attente';
            }

            $target->resetNotificationTrackingIfNeeded($original);
            $target->save();
            $updated->push($target->fresh());
        }

        ActivityLogger::log('booking.recurring.updated', $anchor, [
            'recurring_series_id' => $anchor->recurring_series_id,
            'scope' => $scope,
            'targets_count' => $updated->count(),
            'target_ids' => $updated->pluck('id')->all(),
        ]);

        return $updated;
    }

    protected function resolveTargets(Booking $anchor, string $scope): Collection
    {
        $query = Booking::query()
            ->where('recurring_series_id', $anchor->recurring_series_id)
            ->orderBy('series_position')
            ->orderBy('date')
            ->orderBy('heure');

        return match ($scope) {
            'series' => $query->get(),
            'future' => $query->where('series_position', '>=', (int) $anchor->series_position)->get(),
            default => collect([$anchor]),
        };
    }

    protected function employeeAvailableIgnoringTargets(int $employeeId, string $date, string $heure, Booking $target, int $estimatedDuration, array $ignoredIds = []): bool
    {
        $timezone = config('app.timezone', 'Europe/Brussels');
        $bufferMinutes = (int) ($target->serviceZone?->time_buffer_minutes ?? 0);
        $slotStart = Carbon::createFromFormat('Y-m-d H:i', $date.' '.$heure, $timezone);
        $slotEnd = $slotStart->copy()->addMinutes($estimatedDuration);

        $employee = User::query()->find($employeeId);
        if (! $employee) {
            return false;
        }

        $availabilities = $employee->disponibilites()->whereDate('date', $date)->get();
        if ($availabilities->isNotEmpty()) {
            $within = $availabilities->contains(function ($availability) use ($slotStart, $slotEnd, $date, $timezone) {
                $availableStart = Carbon::createFromFormat('Y-m-d H:i:s', $date.' '.$availability->heure_debut, $timezone);
                $availableEnd = Carbon::createFromFormat('Y-m-d H:i:s', $date.' '.$availability->heure_fin, $timezone);
                return $slotStart->greaterThanOrEqualTo($availableStart) && $slotEnd->lessThanOrEqualTo($availableEnd);
            });
            if (! $within) {
                return false;
            }
        }

        $activeStatuses = ['en_attente', 'confirme', 'en_route', 'sur_place'];

        return ! Booking::query()
            ->where('employe_id', $employeeId)
            ->whereDate('date', $date)
            ->whereIn('status', $activeStatuses)
            ->whereNotIn('id', $ignoredIds)
            ->get()
            ->contains(function ($existing) use ($slotStart, $slotEnd, $bufferMinutes, $timezone) {
                $existingStart = Carbon::createFromFormat('Y-m-d H:i', $existing->date->format('Y-m-d').' '.substr((string) $existing->heure, 0, 5), $timezone);
                $existingDuration = max(30, (int) ($existing->duree_estimee ?: $existing->duree ?: 90));
                $existingEnd = $existingStart->copy()->addMinutes($existingDuration);

                return $slotStart->lt($existingEnd->copy()->addMinutes($bufferMinutes))
                    && $slotEnd->gt($existingStart->copy()->subMinutes($bufferMinutes));
            });
    }
}
