<?php

namespace App\Services\Booking;

use App\Models\RendezVous;
use App\Models\User;
use Illuminate\Support\Collection;

class SmartDispatchService
{
    public function __construct(
        protected EmployeeAvailabilityService $availabilityService,
    ) {}



    protected function asapScore(User $employee, RendezVous $rdv): int
    {
        if (($rdv->booking_mode ?? 'scheduled') !== 'asap') {
            return 0;
        }

        $score = 200;

        $todayMissions = $employee->rendezVousEmploye()
            ->whereDate('date', now()->toDateString())
            ->whereIn('status', ['en_attente', 'confirme', 'en_route', 'sur_place'])
            ->count();

        if ($todayMissions === 0) {
            $score += 150;
        }

        if ($todayMissions >= 3) {
            $score -= 200;
        }

        return $score;
    }

    public function explainBestMatch(RendezVous $rdv): array
    {
        $candidates = $this->explainScores($rdv);

        return [
            'booking_id' => $rdv->id,
            'booking_reference' => $rdv->booking_reference,
            'booking_mode' => $rdv->booking_mode ?? 'scheduled',
            'selected_employee_id' => $rdv->employe_id,
            'candidates_count' => $candidates->count(),
            'candidates' => $candidates->take(10)->values()->all(),
        ];
    }
    
    public function assignBestEmployee(RendezVous $rdv): ?User
    {
        if (! $rdv->service_zone_id || ! $rdv->date || ! $rdv->heure) {
            return null;
        }

        $duration = (int) ($rdv->duree_estimee ?: $rdv->duree ?: 90);

        $employees = $this->availabilityService
            ->sortedEligibleEmployeesForZone((int) $rdv->service_zone_id);

        return $employees
            ->filter(fn(User $employee) => $this->availabilityService->employeeIsAvailableForSlot(
                $employee->id,
                $rdv->date->format('Y-m-d'),
                substr((string) $rdv->heure, 0, 5),
                $rdv->serviceZone,
                $duration,
                $rdv->id
            ))
            ->sortByDesc(fn(User $employee) => $this->score($employee, $rdv))
            ->first();
    }

    public function score(User $employee, RendezVous $rdv): int
    {
        $score = 0;

        $score += $this->zoneScore($employee, $rdv);
        $score += $this->favoriteScore($employee, $rdv);
        $score += $this->qualityScore($employee);
        $score += $this->workloadScore($employee, $rdv);
        $score += $this->premiumScore($employee, $rdv);
        $score += $this->asapScore($employee, $rdv);

        return $score;
    }

    protected function zoneScore(User $employee, RendezVous $rdv): int
    {
        if (! $rdv->service_zone_id) {
            return 0;
        }

        if ((int) $employee->primary_service_zone_id === (int) $rdv->service_zone_id) {
            return 500;
        }

        $assignment = $employee->zoneAssignments
            ->firstWhere('service_zone_id', $rdv->service_zone_id);

        if (! $assignment) {
            return 0;
        }

        return match ($assignment->assignment_type) {
            'primary' => 400,
            'secondary' => 250,
            'backup' => 120,
            default => 80,
        };
    }

    protected function favoriteScore(User $employee, RendezVous $rdv): int
    {
        if (! $rdv->client_id) {
            return 0;
        }

        $isFavorite = $employee->preferredByClients()
            ->where('client_id', $rdv->client_id)
            ->wherePivot('is_favorite', true)
            ->exists();

        return $isFavorite ? 300 : 0;
    }

    protected function qualityScore(User $employee): int
    {
        $average = $employee->leadMissions()
            ->whereNotNull('quality_score')
            ->avg('quality_score');

        if (! $average) {
            return 100;
        }

        return (int) round($average * 2);
    }

    protected function workloadScore(User $employee, RendezVous $rdv): int
    {
        $date = $rdv->date?->format('Y-m-d');

        if (! $date) {
            return 0;
        }

        $count = $employee->rendezVousEmploye()
            ->whereDate('date', $date)
            ->whereIn('status', ['en_attente', 'confirme', 'en_route', 'sur_place'])
            ->count();

        return match (true) {
            $count === 0 => 250,
            $count === 1 => 150,
            $count === 2 => 60,
            default => -150,
        };
    }

    protected function premiumScore(User $employee, RendezVous $rdv): int
    {
        $client = $rdv->client;

        if (! $client || ! method_exists($client, 'isPremium')) {
            return 0;
        }

        return $client->isPremium() ? 80 : 0;
    }

    public function explainScores(RendezVous $rdv): Collection
    {
        if (! $rdv->service_zone_id) {
            return collect();
        }

        return $this->availabilityService
            ->sortedEligibleEmployeesForZone((int) $rdv->service_zone_id)
            ->map(function (User $employee) use ($rdv) {
                return [
                    'employee_id' => $employee->id,
                    'name' => $employee->name,
                    'score' => $this->score($employee, $rdv),
                    'available' => $this->availabilityService->employeeIsAvailableForSlot(
                        $employee->id,
                        $rdv->date->format('Y-m-d'),
                        substr((string) $rdv->heure, 0, 5),
                        $rdv->serviceZone,
                        (int) ($rdv->duree_estimee ?: $rdv->duree ?: 90),
                        $rdv->id
                    ),
                ];
            })
            ->sortByDesc('score')
            ->values();
    }
}
