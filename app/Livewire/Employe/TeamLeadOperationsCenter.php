<?php

namespace App\Livewire\Employe;

use App\Models\Mission;
use App\Models\MissionBatch;
use App\Models\MissionReinforcementRequest;
use App\Models\MissionTaskSegment;
use App\Models\MissionTaskSegmentAssignment;
use App\Models\User;
use App\Services\Missions\TeamLeadOperationsService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class TeamLeadOperationsCenter extends Component
{
    public ?int $selectedBatchId = null;

    public ?int $selectedSegmentId = null;

    public ?int $selectedAssigneeId = null;

    public string $memberStatus = 'in_progress';

    public string $readinessStatus = 'ready';

    public int $progressPercent = 0;

    public int $minutesSpent = 0;

    public bool $isBlocked = false;

    public ?string $blockingReason = null;

    public ?string $memberNotes = null;

    public int $requestedMembers = 1;

    public int $requestedMinutes = 60;

    public string $reinforcementPriority = 'haute';

    public string $reinforcementReason = '';

    protected TeamLeadOperationsService $operations;

    public function boot(TeamLeadOperationsService $operations): void
    {
        $this->operations = $operations;
    }

    public function mount(): void
    {
        $this->selectedBatchId = $this->managedBatches()->value('id');
        $this->selectedSegmentId = $this->currentSegments()->value('id');
    }

    protected function managedBatches()
    {
        return MissionBatch::query()
            ->where(function ($query) {
                $query->where('team_lead_user_id', Auth::id())
                    ->orWhereHas('fieldTeam.members', function ($memberQuery) {
                        $memberQuery->where('user_id', Auth::id())
                            ->where('is_team_lead', true);
                    });
            })
            ->with(['days.segments'])
            ->latest('start_date');
    }

    protected function currentSegments()
    {
        if (! $this->selectedBatchId) {
            return MissionTaskSegment::query()->whereRaw('1=0');
        }

        /*
         * QUATRE NOMS FAUX DANS UNE SEULE REQUÊTE (corrigés le 2026-08-06).
         *
         *   - `missionBatchDay` : la relation s'appelle `day()` sur `MissionTaskSegment`.
         *     `whereHas` sur une relation absente lève une `RelationNotFoundException`.
         *   - `segment_date` : la colonne s'appelle `service_date`.
         *   - `assignments` et `memberStatuses` : relations qui n'étaient pas déclarées sur le
         *     segment, alors que leurs tables, leurs modèles et le côté direct existaient tous.
         *
         * Résultat : l'écran chef d'équipe tombait dès qu'un lot était sélectionné, et le panneau
         * « statut membre par membre » ne s'affichait jamais. Aucun test ne montait ce composant
         * avec des données — la couverture existante n'exerçait que le cas « aucun lot ».
         */
        return MissionTaskSegment::query()
            ->whereHas('day', fn ($q) => $q->where('mission_batch_id', $this->selectedBatchId))
            ->with(['assignments.user', 'memberStatuses'])
            ->orderBy('service_date')
            ->orderBy('sequence_order');
    }

    public function assignSelectedSegment(): void
    {
        $segment = MissionTaskSegment::findOrFail($this->selectedSegmentId);
        $user = User::findOrFail($this->selectedAssigneeId);

        $this->operations->assignSegment($segment, $user, [
            'assigned_by_user_id' => Auth::id(),
            'field_team_id' => $segment->field_team_id,
            'planned_minutes' => $segment->estimated_minutes,
        ]);

        $this->dispatch('toast', 'Segment affecté avec succès.', 'success');
    }

    public function updateSelectedMemberStatus(int $assignmentId): void
    {
        /*
         * L'IDENTIFIANT VIENT DU CLIENT (garde ajoutée le 2026-08-06).
         *
         * La liste des lots est bien limitée aux équipes dont on est chef — `managedBatches()`
         * filtre sur `team_lead_user_id` ou l'appartenance en tant que chef. Mais cette action
         * résolvait l'affectation par un `findOrFail()` non scopé, et `TeamLeadOperationsService`
         * n'autorise rien de son côté : un chef pouvait écrire le statut d'un membre d'une AUTRE
         * équipe.
         *
         * Même motif que la messagerie d'équipe — lecture gardée, écriture oubliée. On limite donc
         * la résolution aux équipes que cet utilisateur dirige réellement.
         */
        $assignment = MissionTaskSegmentAssignment::query()
            ->with('mission')
            ->whereHas('fieldTeam', fn ($q) => $q->where('team_lead_user_id', Auth::id())
                ->orWhereHas('members', fn ($m) => $m->where('user_id', Auth::id())->where('is_team_lead', true))
            )
            ->find($assignmentId);

        abort_if($assignment === null, 403);

        $this->operations->updateMemberStatus($assignment, $assignment->user, [
            'status' => $this->memberStatus,
            'readiness_status' => $this->readinessStatus,
            'progress_percent' => $this->progressPercent,
            'minutes_spent' => $this->minutesSpent,
            'is_blocked' => $this->isBlocked,
            'blocking_reason' => $this->blockingReason,
            'notes' => $this->memberNotes,
        ]);

        $this->dispatch('toast', 'Statut membre mis à jour.', 'success');
    }

    public function requestReinforcement(): void
    {
        $segment = MissionTaskSegment::findOrFail($this->selectedSegmentId);

        $this->operations->requestReinforcement($segment, Auth::user(), [
            'field_team_id' => $segment->field_team_id,
            'requested_members' => $this->requestedMembers,
            'requested_minutes' => $this->requestedMinutes,
            'priority' => $this->reinforcementPriority,
            'reason' => $this->reinforcementReason,
        ]);

        $this->dispatch('toast', 'Demande de renfort envoyée.', 'success');
    }

    public function closeSelectedBatchMission(int $missionId): void
    {
        $mission = Mission::findOrFail($missionId);
        $this->operations->closeInterventionGlobally($mission, Auth::user());

        $this->dispatch('toast', 'Clôture globale exécutée.', 'success');
    }

    public function render(): View
    {
        $batches = $this->managedBatches()->get();
        $segments = $this->currentSegments()->get();
        $selectedSegment = $segments->firstWhere('id', $this->selectedSegmentId);
        $reinforcementRequests = MissionReinforcementRequest::query()
            ->when($this->selectedBatchId, fn ($q) => $q->where('mission_batch_id', $this->selectedBatchId))
            ->latest()
            ->limit(8)
            ->get();

        return view('livewire.employe.team-lead-operations-center', [
            'batches' => $batches,
            'segments' => $segments,
            'selectedSegment' => $selectedSegment,
            'reinforcementRequests' => $reinforcementRequests,
        ]);
    }
}
