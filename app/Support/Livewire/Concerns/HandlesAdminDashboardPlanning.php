<?php

namespace App\Support\Livewire\Concerns;


use App\Models\LimiteJournaliere;
use App\Models\RendezVous;
use App\Notifications\MissionReplanifieeNotification;
use Carbon\Carbon;

trait HandlesAdminDashboardPlanning
{
    public function ouvrirMission(int $id): void
    {
        $this->selectedMissionId = $id;
        $this->showMissionModal = true;
        $this->suggestedEmployees = $this->computeSuggestedEmployees($id);
    }

    public function fermerMission(): void
    {
        $this->selectedMissionId = null;
        $this->showMissionModal = false;
        $this->suggestedEmployees = [];
    }

    public function getSelectedMissionProperty()
    {
        if (! $this->selectedMissionId) {
            return null;
        }

        return $this->scopedRendezVousQuery(false)
            ->with(['client', 'employe', 'feedback', 'serviceZone'])
            ->find($this->selectedMissionId);
    }

    public function ouvrirPlanning(int $id): void
    {
        $rdv = $this->scopedRendezVousQuery(false)->findOrFail($id);

        $this->planningMissionId = $rdv->id;
        $this->planningEmployeId = $rdv->employe_id;
        $this->planningDate = $rdv->date?->format('Y-m-d') ?? $rdv->date;
        $this->planningHeure = substr((string) $rdv->heure, 0, 5);
        $this->showPlanningModal = true;
        $this->suggestedEmployees = $this->computeSuggestedEmployees($id, $this->planningDate, $this->planningHeure);
    }

    public function updatedPlanningDate()
    {
        if ($this->planningMissionId && $this->planningDate && $this->planningHeure) {
            $this->suggestedEmployees = $this->computeSuggestedEmployees(
                $this->planningMissionId,
                $this->planningDate,
                $this->planningHeure
            );
        }
    }

    public function updatedPlanningHeure()
    {
        if ($this->planningMissionId && $this->planningDate && $this->planningHeure) {
            $this->suggestedEmployees = $this->computeSuggestedEmployees(
                $this->planningMissionId,
                $this->planningDate,
                $this->planningHeure
            );
        }
    }

    public function fermerPlanning(): void
    {
        $this->reset([
            'showPlanningModal',
            'planningMissionId',
            'planningEmployeId',
            'planningDate',
            'planningHeure',
        ]);

        $this->suggestedEmployees = [];
    }

    public function appliquerSuggestionEmploye(int $employeId): void
    {
        $this->planningEmployeId = $employeId;
    }

    public function enregistrerPlanning(): void
    {
        $this->validate([
            'planningMissionId' => ['required', 'exists:rendez_vous,id'],
            'planningEmployeId' => ['required', 'exists:users,id'],
            'planningDate' => ['required', 'date'],
            'planningHeure' => ['required'],
        ]);

        $rdv = $this->scopedRendezVousQuery(false)
            ->with(['client', 'employe', 'serviceZone'])
            ->findOrFail($this->planningMissionId);

        $ancienEmployeNom = $rdv->employe->name ?? 'Employé';
        $ancienneDate = $rdv->date?->format('Y-m-d') ?? (string) $rdv->date;
        $ancienneHeure = substr((string) $rdv->heure, 0, 5);
        $ancienEmployeId = $rdv->employe_id;
        $ancienStatus = $rdv->status;

        $newStart = Carbon::parse($this->planningDate . ' ' . $this->planningHeure);
        $newDuration = $rdv->duree ?? $rdv->duree_estimee ?? 90;
        $bufferMinutes = 30;
        $newEnd = $newStart->copy()->addMinutes($newDuration + $bufferMinutes);

        $conflict = $this->scopedRendezVousQuery(false)
            ->where('id', '!=', $rdv->id)
            ->where('employe_id', $this->planningEmployeId)
            ->whereDate('date', $this->planningDate)
            ->whereIn('status', ['confirme', 'en_attente', 'en_route', 'sur_place'])
            ->get()
            ->contains(function ($other) use ($newStart, $newEnd, $bufferMinutes) {
                $otherStart = Carbon::parse($other->date . ' ' . $other->heure);
                $otherDuration = $other->duree ?? $other->duree_estimee ?? 90;
                $otherEnd = $otherStart->copy()->addMinutes($otherDuration + $bufferMinutes);

                return $newStart < $otherEnd && $newEnd > $otherStart;
            });

        if ($conflict) {
            $this->addError('planningHeure', 'Conflit détecté : cet employé a déjà une mission sur ce créneau.');
            return;
        }

        $original = [
            'date' => $rdv->date,
            'heure' => $rdv->heure,
            'status' => $rdv->status,
            'priorite' => $rdv->priorite,
        ];

        $rdv->employe_id = $this->planningEmployeId;
        $rdv->date = $this->planningDate;
        $rdv->heure = $this->planningHeure;

        if (in_array($rdv->status, ['confirme', 'en_route', 'sur_place'], true)) {
            $rdv->status = 'en_attente';
        }

        $rdv->resetNotificationTrackingIfNeeded($original);
        $rdv->save();
        $rdv->load(['client', 'employe', 'serviceZone']);

        if ($rdv->client) {
            $rdv->client->notify(
                new MissionReplanifieeNotification($rdv, $ancienEmployeNom, $ancienneDate, $ancienneHeure)
            );
        }

        if ($rdv->employe && $rdv->employe_id != $ancienEmployeId) {
            $rdv->employe->notify(
                new MissionReplanifieeNotification($rdv, $ancienEmployeNom, $ancienneDate, $ancienneHeure)
            );
        }

        $this->logActivity('mission_replanifiee', $rdv, [
            'ancienne_date' => $ancienneDate,
            'ancienne_heure' => $ancienneHeure,
            'nouvelle_date' => $rdv->date?->format('Y-m-d') ?? (string) $rdv->date,
            'nouvelle_heure' => $rdv->heure,
            'ancien_employe' => $ancienEmployeNom,
            'nouvel_employe' => $rdv->employe->name ?? 'Employé',
            'ancien_statut' => $ancienStatus,
            'nouveau_statut' => $rdv->status,
            'service_zone_id' => $rdv->service_zone_id,
        ]);

        $this->clearAdminCache();
        $this->chargerRdvs();
        $this->mettreAJourStats();
        $this->fermerPlanning();

        $this->dispatch('toast', 'Mission replanifiée avec succès.', 'success');
    }

    protected function computeSuggestedEmployees(int $missionId, ?string $date = null, ?string $heure = null): array
    {
        $rdv = $this->scopedRendezVousQuery(false)->find($missionId);

        if (! $rdv) {
            return [];
        }

        $date = $date ?: ($rdv->date?->format('Y-m-d') ?? (string) $rdv->date);
        $heure = $heure ?: substr((string) $rdv->heure, 0, 5);

        $employeeQuery = $this->scopedEmployeesQuery();

        if ($rdv->service_zone_id) {
            $employeeQuery->where(function (Builder $query) use ($rdv) {
                $query
                    ->where('primary_service_zone_id', $rdv->service_zone_id)
                    ->orWhereHas('zoneAssignments', function (Builder $assignmentQuery) use ($rdv) {
                        $assignmentQuery->where('service_zone_id', $rdv->service_zone_id)
                            ->where('is_active', true);
                    });
            });
        }

        return $employeeQuery
            ->get()
            ->map(function ($employe) use ($rdv, $date, $heure) {
                $score = $this->computeEmployeScore($employe->id, $date, $heure, $rdv);

                return [
                    'id' => $employe->id,
                    'name' => $employe->name,
                    'score' => $score['score'],
                    'load_minutes' => $score['load_minutes'],
                    'rdv_count' => $score['rdv_count'],
                    'has_conflict' => $score['has_conflict'],
                    'same_city_bonus' => $score['same_city_bonus'],
                ];
            })
            ->filter(fn ($row) => ! $row['has_conflict'])
            ->sortBy('score')
            ->values()
            ->take(5)
            ->toArray();
    }

    protected function computeEmployeScore(int $employeId, string $date, string $heure, RendezVous $rdv): array
    {
        $bufferMinutes = 30;
        $duration = $rdv->duree ?? $rdv->duree_estimee ?? 90;
        $start = Carbon::parse($date . ' ' . $heure);
        $end = $start->copy()->addMinutes($duration + $bufferMinutes);

        $rdvsJour = $this->scopedRendezVousQuery(false)
            ->where('employe_id', $employeId)
            ->whereDate('date', $date)
            ->whereIn('status', ['confirme', 'en_attente', 'en_route', 'sur_place'])
            ->get();

        $hasConflict = $rdvsJour->contains(function ($other) use ($start, $end, $bufferMinutes) {
            $otherStart = Carbon::parse($other->date . ' ' . $other->heure);
            $otherDuration = $other->duree ?? $other->duree_estimee ?? 90;
            $otherEnd = $otherStart->copy()->addMinutes($otherDuration + $bufferMinutes);

            return $start < $otherEnd && $end > $otherStart;
        });

        $loadMinutes = $rdvsJour->sum(function ($item) {
            return ($item->duree ?? $item->duree_estimee ?? 90) + 30;
        });

        $limit = LimiteJournaliere::where('user_id', $employeId)
            ->whereDate('date', $date)
            ->value('limite');

        $sameCityBonus = $rdvsJour->contains(fn ($item) => filled($rdv->ville) && $item->ville === $rdv->ville) ? -40 : 0;
        $score = $loadMinutes + ($rdvsJour->count() * 25) + $sameCityBonus;

        if ($limit && $rdvsJour->count() >= $limit) {
            $score += 500;
        }

        return [
            'score' => $score,
            'load_minutes' => $loadMinutes,
            'rdv_count' => $rdvsJour->count(),
            'has_conflict' => $hasConflict,
            'same_city_bonus' => $sameCityBonus,
        ];
    }

    public function getPremiumClientsCountProperty(): int
    {
        return $this->scopedClientsQuery()
            ->where('plan_type', 'premium')
            ->where('plan_status', 'active')
            ->count();
    }

    public function getStandardClientsCountProperty(): int
    {
        return $this->scopedClientsQuery()
            ->where('plan_type', 'standard')
            ->count();
    }

    public function getActiveSubscriptionsCountProperty(): int
    {
        return $this->scopedClientsQuery()
            ->where('plan_type', 'premium')
            ->where('plan_status', 'active')
            ->count();
    }

    public function getPremiumClientsProperty()
    {
        return $this->scopedClientsQuery()
            ->where('plan_type', 'premium')
            ->where('plan_status', 'active')
            ->latest()
            ->limit(8)
            ->get();
    }

    public function getPremiumRendezVousProperty()
    {
        return $this->scopedRendezVousQuery(false)
            ->with(['client', 'employe', 'serviceZone'])
            ->whereHas('client', function ($q) {
                $q->where('plan_type', 'premium')
                    ->where('plan_status', 'active');
            })
            ->orderBy('date')
            ->orderBy('heure')
            ->limit(10)
            ->get();
    }

    public function getRendezVousSansEmployeProperty()
    {
        return $this->scopedRendezVousQuery(false)
            ->with(['client', 'serviceZone'])
            ->whereNull('employe_id')
            ->whereIn('status', ['en_attente', 'confirme'])
            ->orderBy('date')
            ->orderBy('heure')
            ->limit(10)
            ->get();
    }

    public function getPremiumClientsWithoutFavoritesProperty()
    {
        return $this->scopedClientsQuery()
            ->where('plan_type', 'premium')
            ->where('plan_status', 'active')
            ->whereDoesntHave('favoriteEmployes')
            ->limit(10)
            ->get();
    }

}
