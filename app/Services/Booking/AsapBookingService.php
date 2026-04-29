<?php

namespace App\Services\Booking;

use App\Models\ServiceZone;
use App\Models\User;
use Carbon\Carbon;

class AsapBookingService
{
    public function __construct(
        protected EmployeeAvailabilityService $availabilityService
    ) {}

    public function findBestAsapSlot(
        ServiceZone $zone,
        int $estimatedDuration = 90,
        ?int $preferredEmployeeId = null,
        int $maxDelayHours = 2,
    ): ?array {
        $timezone = config('app.timezone', 'Europe/Brussels');

        $start = now($timezone)->copy()->addMinutes(30);
        $deadline = now($timezone)->copy()->addHours($maxDelayHours);

        $cursor = $start->copy()->ceilMinutes(15);

        while ($cursor->lessThanOrEqualTo($deadline)) {
            $date = $cursor->format('Y-m-d');
            $heure = $cursor->format('H:i');

            if ($preferredEmployeeId) {
                $employee = User::query()->find($preferredEmployeeId);

                if (
                    $employee
                    && $this->availabilityService->employeeCanCoverZone($employee->id, $zone->id)
                    && $this->availabilityService->employeeIsAvailableForSlot(
                        $employee->id,
                        $date,
                        $heure,
                        $zone,
                        $estimatedDuration
                    )
                ) {
                    return [
                        'date' => $date,
                        'heure' => $heure,
                        'employee' => $employee,
                        'deadline' => $deadline,
                    ];
                }
            }

            $employee = $this->availabilityService->resolveBestAvailableEmployeeForSlot(
                $date,
                $heure,
                $zone,
                $estimatedDuration
            );

            if ($employee) {
                return [
                    'date' => $date,
                    'heure' => $heure,
                    'employee' => $employee,
                    'deadline' => $deadline,
                ];
            }

            $cursor->addMinutes(15);
        }

        return null;
    }
}