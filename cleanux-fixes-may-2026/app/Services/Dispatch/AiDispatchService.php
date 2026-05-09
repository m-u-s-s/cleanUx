<?php

namespace App\Services\Dispatch;

use App\Models\Booking;
use App\Models\User;
use App\Services\Booking\EmployeeAvailabilityService;
use Illuminate\Support\Collection;

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
                // Phase 11 — Pour les bookings ASAP, exiger que le prestataire
                // soit ONLINE (sinon il ne recevra pas le push, l'offre va
                // expirer en 15s et l'escalation va boucler dans le vide).
                //
                // Pour les bookings SCHEDULED, on accepte les prestataires
                // offline : le push arrivera quand ils repasseront online,
                // et la mission étant planifiée plus tard, ils auront le temps.
                //
                // BUG HISTORIQUE : la closure n'avait PAS de `return true`
                // explicite en bas, ce qui faisait qu'elle retournait null
                // (= falsy) pour tous les bookings non-ASAP, et le dispatch
                // ne trouvait jamais aucun candidat pour les missions
                // planifiées. Tout le flow Phase 11 était cassé pour
                // scheduled. Fixé en mai 2026.
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

        return $employee->preferredByClients()
            ->where('client_id', $rdv->client_id)
            ->wherePivot('is_favorite', true)
            ->exists()
            ? 180
            : 0;
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
