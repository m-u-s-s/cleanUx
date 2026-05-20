<?php

namespace App\Livewire\Admin\KybV2;

use App\Models\BusinessDocument;
use App\Models\BusinessEntity;
use App\Models\BusinessSanctionsCheck;
use App\Models\BusinessVerification;
use App\Services\KybV2\BusinessOnboardingService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class KybCenter extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public string $tab = 'entities';   // entities | documents | verifications | sanctions
    public string $filterStatus = '';
    public string $filterRiskLevel = '';

    public function runVerifications(int $entityId): void
    {
        $entity = BusinessEntity::findOrFail($entityId);
        app(BusinessOnboardingService::class)->runVerifications($entity);
        $this->dispatch('toast', 'Vérifications lancées.', 'success');
    }

    public function runSanctions(int $entityId): void
    {
        $entity = BusinessEntity::findOrFail($entityId);
        app(BusinessOnboardingService::class)->runSanctionsScreening($entity);
        $this->dispatch('toast', 'Sanctions screening exécuté.', 'success');
    }

    public function approveEntity(int $entityId): void
    {
        $entity = BusinessEntity::findOrFail($entityId);
        app(BusinessOnboardingService::class)->approve($entity, Auth::user());
        $this->dispatch('toast', 'Entité approuvée.', 'success');
    }

    public function rejectEntity(int $entityId, string $reason = 'Rejet manuel via admin UI — voir motif détaillé'): void
    {
        $entity = BusinessEntity::findOrFail($entityId);
        try {
            app(BusinessOnboardingService::class)->reject($entity, $reason, Auth::user());
            $this->dispatch('toast', 'Entité rejetée.', 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', 'Erreur : ' . $e->getMessage(), 'error');
        }
    }

    public function approveDocument(int $documentId): void
    {
        $doc = BusinessDocument::findOrFail($documentId);
        $doc->update([
            'status' => BusinessDocument::STATUS_APPROVED,
            'reviewed_at' => now(),
            'reviewed_by_user_id' => Auth::id(),
        ]);
        $this->dispatch('toast', 'Document approuvé.', 'success');
    }

    public function render(): View
    {
        $kpis = [
            'entities_total' => BusinessEntity::query()->count(),
            'entities_verified' => BusinessEntity::query()->verified()->count(),
            'entities_pending' => BusinessEntity::query()->pending()->count(),
            'critical_risk' => BusinessEntity::query()->where('risk_level', BusinessEntity::RISK_CRITICAL)->count(),
            'sanctions_matches' => BusinessSanctionsCheck::query()->matches()->count(),
        ];

        if ($this->tab === 'entities') {
            $items = BusinessEntity::query()
                ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
                ->when($this->filterRiskLevel, fn ($q) => $q->where('risk_level', $this->filterRiskLevel))
                ->orderByDesc('created_at')
                ->paginate(25);
        } elseif ($this->tab === 'documents') {
            $items = BusinessDocument::query()
                ->with('entity:id,code,legal_name')
                ->orderByDesc('created_at')
                ->paginate(25);
        } elseif ($this->tab === 'verifications') {
            $items = BusinessVerification::query()
                ->with('entity:id,code,legal_name')
                ->orderByDesc('created_at')
                ->paginate(25);
        } else {
            $items = BusinessSanctionsCheck::query()
                ->with('entity:id,code,legal_name')
                ->orderByDesc('checked_at')
                ->paginate(25);
        }

        return view('livewire.admin.kyb-v2.kyb-center', [
            'kpis' => $kpis,
            'items' => $items,
        ]);
    }
}
