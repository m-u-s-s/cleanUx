<?php

namespace App\Livewire\Admin;

use App\Models\EnterpriseWorkOrder;
use App\Models\FieldTeamLoadSnapshot;
use App\Models\MissionBatch;
use App\Models\ServicePartnerLoadSnapshot;
use App\Services\Missions\EnterpriseWorkOrderMissionGeneratorService;
use App\Services\Missions\OperationalLoadCalculator;
use Livewire\Component;

class AutomationMissionGenerationCenter extends Component
{
    public string $selectedDate;

    public function mount(): void
    {
        $this->selectedDate = now()->toDateString();
    }

    public function refreshSnapshots(OperationalLoadCalculator $calculator): void
    {
        $calculator->captureDailySnapshots($this->selectedDate);
        $this->dispatch('toast', 'Snapshots de charge recalculés.', 'success');
    }

    public function generateFromWorkOrder(int $workOrderId, EnterpriseWorkOrderMissionGeneratorService $generator): void
    {
        $workOrder = EnterpriseWorkOrder::query()->findOrFail($workOrderId);
        $result = $generator->runForApprovedWorkOrder($workOrder);

        $message = $result['status'] === 'generated'
            ? 'Ordre de service généré en missions avec succès.'
            : 'Ordre de service ignoré : approbation requise.';

        $this->dispatch('toast', $message, $result['status'] === 'generated' ? 'success' : 'error');
    }

    public function materializeBatch(int $batchId, EnterpriseWorkOrderMissionGeneratorService $generator): void
    {
        $batch = MissionBatch::query()->findOrFail($batchId);
        $created = $generator->materializePendingMissionsForBatch($batch);
        $this->dispatch('toast', $created->count() . ' mission(s) générée(s) depuis le lot.', 'success');
    }

    public function runPending(EnterpriseWorkOrderMissionGeneratorService $generator): void
    {
        $results = $generator->generateForApprovedPendingWorkOrders($this->selectedDate);
        $count = $results->sum(fn ($row) => (int) ($row['missions_created'] ?? 0));
        $this->dispatch('toast', 'Automatisation exécutée : ' . $count . ' mission(s) générée(s).', 'success');
    }

    public function getApprovedPendingWorkOrdersProperty()
    {
        return EnterpriseWorkOrder::query()
            ->with(['organizationAccount', 'organizationSite', 'generatedBatch'])
            ->approvedForGeneration()
            ->orderByRaw('COALESCE(scheduled_start_at, requested_start_at) asc')
            ->limit(15)
            ->get();
    }

    public function getRecentBatchesProperty()
    {
        return MissionBatch::query()
            ->with(['organizationAccount', 'organizationSite'])
            ->orderByDesc('created_at')
            ->limit(12)
            ->get();
    }

    public function getFieldTeamSnapshotsProperty()
    {
        return FieldTeamLoadSnapshot::query()
            ->with('fieldTeam')
            ->whereDate('snapshot_date', $this->selectedDate)
            ->orderByDesc('utilization_percent')
            ->limit(10)
            ->get();
    }

    public function getPartnerSnapshotsProperty()
    {
        return ServicePartnerLoadSnapshot::query()
            ->with('servicePartner')
            ->whereDate('snapshot_date', $this->selectedDate)
            ->orderByDesc('utilization_percent')
            ->limit(10)
            ->get();
    }

    public function render()
    {
        return view('livewire.admin.automation-mission-generation-center', [
            'approvedPendingWorkOrders' => $this->approvedPendingWorkOrders,
            'recentBatches' => $this->recentBatches,
            'fieldTeamSnapshots' => $this->fieldTeamSnapshots,
            'partnerSnapshots' => $this->partnerSnapshots,
        ])->layout('layouts.app');
    }
}
