<?php

namespace App\Livewire\Employe;

use App\Models\FieldTeamMember;
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

    /**
     * LE PÉRIMÈTRE DU CHEF D'ÉQUIPE, ÉCRIT UNE SEULE FOIS et réutilisé en sous-requête par les
     * gardes ci-dessous. `EnsureFieldTeamLead` vérifie qu'on est chef d'UNE équipe, jamais de
     * laquelle : c'est ici que la question se pose.
     */
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

    /**
     * L'IDENTIFIANT VIENT DU CLIENT, comme pour `updateSelectedMemberStatus` : il se résout DANS le
     * périmètre, jamais par un `findOrFail` nu — sinon on affecte le segment d'un concurrent.
     */
    protected function segmentGere(?int $segmentId): MissionTaskSegment
    {
        $segment = MissionTaskSegment::query()
            ->whereIn('mission_batch_id', $this->managedBatches()->select('mission_batches.id'))
            ->find($segmentId);

        abort_if($segment === null, 403);

        return $segment;
    }

    /** On n'affecte pas n'importe qui : l'intervenant doit être un membre actif de CETTE équipe. */
    protected function membreDeLEquipe(MissionTaskSegment $segment, ?int $userId): User
    {
        $estMembre = $segment->field_team_id !== null
            && $userId !== null
            && FieldTeamMember::query()
                ->where('field_team_id', $segment->field_team_id)
                ->where('user_id', $userId)
                ->where('is_active', true)
                ->exists();

        abort_unless($estMembre, 403);

        return User::findOrFail($userId);
    }

    protected function currentSegments()
    {
        if (! $this->selectedBatchId) {
            return MissionTaskSegment::query()->whereRaw('1=0');
        }

        // QUATRE NOMS FAUX DANS UNE SEULE REQUÊTE (corrigés le 2026-08-06).
        return MissionTaskSegment::query()
            ->whereHas('day', fn ($q) => $q->where('mission_batch_id', $this->selectedBatchId))
            ->with(['assignments.user', 'memberStatuses'])
            ->orderBy('service_date')
            ->orderBy('sequence_order');
    }

    public function assignSelectedSegment(): void
    {
        $segment = $this->segmentGere($this->selectedSegmentId);
        $user = $this->membreDeLEquipe($segment, $this->selectedAssigneeId);

        $this->operations->assignSegment($segment, $user, [
            'assigned_by_user_id' => Auth::id(),
            'field_team_id' => $segment->field_team_id,
            'planned_minutes' => $segment->estimated_minutes,
        ]);

        $this->dispatch('toast', 'Segment affecté avec succès.', 'success');
    }

    public function updateSelectedMemberStatus(int $assignmentId): void
    {
        // L'IDENTIFIANT VIENT DU CLIENT (garde ajoutée le 2026-08-06).
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
        $segment = $this->segmentGere($this->selectedSegmentId);

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
        $mission = Mission::query()
            ->whereIn('id', MissionTaskSegment::query()
                ->whereIn('mission_batch_id', $this->managedBatches()->select('mission_batches.id'))
                ->select('mission_id')
            )
            ->find($missionId);

        abort_if($mission === null, 403);

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
