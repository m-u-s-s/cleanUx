<?php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Models\User;
use App\Services\Geo\GeoDistanceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Collection;

class SmartDispatchService
{
    public function __construct(
        protected EmployeeAvailabilityService $availabilityService,
        protected GeoDistanceService $geoDistanceService,
    ) {}



    protected function asapScore(User $employee, Booking $rdv): int
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

    protected function distanceScore(User $employee, Booking $rdv): int
    {
        if (! $employee->current_lat || ! $employee->current_lng) {
            return 0;
        }

        if (! $rdv->destination_lat || ! $rdv->destination_lng) {
            return 0;
        }

        $distanceKm = $this->geoDistanceService->haversineKm(
            (float) $employee->current_lat,
            (float) $employee->current_lng,
            (float) $rdv->destination_lat,
            (float) $rdv->destination_lng,
        );

        return match (true) {
            $distanceKm <= 2 => 500,
            $distanceKm <= 5 => 350,
            $distanceKm <= 10 => 200,
            $distanceKm <= 20 => 50,
            default => -200,
        };
    }

    public function explainBestMatch(Booking $rdv): array
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

    public function assignBestEmployee(Booking $rdv): ?User
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

    public function score(User $employee, Booking $rdv): int
    {
        $score = 0;

        $score += $this->zoneScore($employee, $rdv);
        $score += $this->favoriteScore($employee, $rdv);
        $score += $this->qualityScore($employee);
        $score += $this->workloadScore($employee, $rdv);
        $score += $this->premiumScore($employee, $rdv);
        $score += $this->asapScore($employee, $rdv);
        $score += $this->distanceScore($employee, $rdv);


        return $score;
    }

    protected function zoneScore(User $employee, Booking $rdv): int
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

    protected function favoriteScore(User $employee, Booking $rdv): int
    {
        if (! $rdv->client_id) {
            return 0;
        }

        $isFavorite = false;

        if (Schema::hasTable('client_provider_preferences')) {
            $clientId = $rendezVous->client_id
                ?? $rdv->client_id
                ?? $booking->client_id
                ?? null;

            if ($clientId) {
                $favoriteQuery = DB::table('client_provider_preferences')
                    ->where('provider_user_id', $employee->id);

                if (Schema::hasColumn('client_provider_preferences', 'client_user_id')) {
                    $favoriteQuery->where('client_user_id', $clientId);
                } elseif (Schema::hasColumn('client_provider_preferences', 'client_id')) {
                    $favoriteQuery->where('client_id', $clientId);
                } else {
                    $favoriteQuery = null;
                }

                if ($favoriteQuery && Schema::hasColumn('client_provider_preferences', 'is_favorite')) {
                    $favoriteQuery->where('is_favorite', true);
                }

                $isFavorite = $favoriteQuery ? $favoriteQuery->exists() : false;
            }
        }

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

    protected function workloadScore(User $employee, Booking $rdv): int
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

    protected function premiumScore(User $employee, Booking $rdv): int
    {
        $client = $rdv->client;

        if (! $client || ! method_exists($client, 'isPremium')) {
            return 0;
        }

        return $client->isPremium() ? 80 : 0;
    }

    public function explainScores(Booking $rdv): Collection
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
                    'distance_km' => $employee->current_lat && $employee->current_lng && $rdv->destination_lat && $rdv->destination_lng
                        ? $this->geoDistanceService->haversineKm(
                            (float) $employee->current_lat,
                            (float) $employee->current_lng,
                            (float) $rdv->destination_lat,
                            (float) $rdv->destination_lng,
                        )
                        : null,
                ];
            })
            ->sortByDesc('score')
            ->values();
    }
}
