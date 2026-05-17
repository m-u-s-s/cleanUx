<?php

namespace App\Services\Dispatch;

use App\Models\Booking;
use App\Models\User;
use App\Services\Booking\EmployeeAvailabilityService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AiDispatchService
{
    public function __construct(
        protected EmployeeAvailabilityService $availability,
    ) {}

    public function bestEmployeeFor(Booking $rdv): ?User
    {
        return $this->rankEmployees($rdv)->first()['employee'] ?? null;
        
    }

    public function rankEmployees(Booking $rdv): Collection
    {
        if (! $rdv->service_zone_id || ! $rdv->date || ! $rdv->heure) {
            return collect();
        }

        $duration = (int) ($rdv->duree_estimee ?: $rdv->duree ?: 90);

        return $this->availability
            ->sortedEligibleEmployeesForZone((int) $rdv->service_zone_id)
            ->filter(function (User $employee) use ($rdv) {
                
                if ($rdv->booking_mode === 'asap') {
                    $profile = $employee->providerProfile;
                    if (! $profile || ! $profile->is_online) {
                        return false;
                    }
                }

                return true;
            })
            ->map(fn(User $employee) => [
                'employee' => $employee,
                'score' => $this->score($employee, $rdv),
                'details' => $this->scoreDetails($employee, $rdv),
            ])
            ->sortByDesc('score')
            ->values();
    }

    public function score(User $employee, Booking $rdv): int
    {
        return array_sum($this->scoreDetails($employee, $rdv));
    }

    public function scoreDetails(User $employee, Booking $rdv): array
    {
        return [
            'zone' => $this->zoneScore($employee, $rdv),
            'quality' => $this->qualityScore($employee),
            'workload' => $this->workloadScore($employee, $rdv),
            'favorite' => $this->favoriteScore($employee, $rdv),
            'premium' => $this->premiumScore($rdv),
            'urgency' => $this->urgencyScore($rdv),
            'reliability' => $this->reliabilityScore($employee),
        ];
    }

    protected function zoneScore(User $employee, Booking $rdv): int
    {
        if ((int) $employee->primary_service_zone_id === (int) $rdv->service_zone_id) {
            return 300;
        }

        $assignment = $employee->zoneAssignments
            ->firstWhere('service_zone_id', $rdv->service_zone_id);

        return match ($assignment?->assignment_type) {
            'primary' => 250,
            'secondary' => 150,
            'backup' => 80,
            default => 0,
        };
    }

    protected function qualityScore(User $employee): int
    {
        $avg = (float) $employee->leadMissions()
            ->whereNotNull('quality_score')
            ->avg('quality_score');

        return $avg > 0 ? (int) round($avg * 2) : 120;
    }

    protected function workloadScore(User $employee, Booking $rdv): int
    {
        $count = $employee->rendezVousEmploye()
            ->whereDate('date', $rdv->date)
            ->whereIn('status', ['en_attente', 'confirme', 'en_route', 'sur_place'])
            ->count();

        return match (true) {
            $count === 0 => 220,
            $count === 1 => 140,
            $count === 2 => 50,
            default => -200,
        };
    }

    protected function favoriteScore(User $employee, Booking $rdv): int
    {
        if (! $rdv->client_id) {
            return 0;
        }

        if (! Schema::hasTable('client_provider_preferences')) {
            return false;
        }

        $clientId = $rendezVous->client_id
            ?? $rdv->client_id
            ?? $booking->client_id
            ?? null;

        if (! $clientId) {
            return false;
        }

        $query = DB::table('client_provider_preferences')
            ->where('provider_user_id', $employee->id);

        if (Schema::hasColumn('client_provider_preferences', 'client_user_id')) {
            $query->where('client_user_id', $clientId);
        } elseif (Schema::hasColumn('client_provider_preferences', 'client_id')) {
            $query->where('client_id', $clientId);
        } else {
            return false;
        }

        if (Schema::hasColumn('client_provider_preferences', 'is_favorite')) {
            $query->where('is_favorite', true);
        }

        return $query->exists();
    }

    protected function premiumScore(Booking $rdv): int
    {
        return $rdv->client && method_exists($rdv->client, 'isPremium') && $rdv->client->isPremium()
            ? 80
            : 0;
    }

    protected function urgencyScore(Booking $rdv): int
    {
        return $rdv->priorite === 'urgente' ? 120 : 0;
    }

    protected function reliabilityScore(User $employee): int
    {
        $missions = $employee->leadMissions()->count();

        if ($missions === 0) {
            return 100;
        }

        $completed = $employee->leadMissions()
            ->where('status', 'completed')
            ->count();

        return (int) round(($completed / max(1, $missions)) * 150);
    }
}
