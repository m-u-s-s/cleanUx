<?php

namespace App\Livewire\Admin\Quality;

use App\Models\MissionQualityInspection;
use App\Models\QualityChecklist;
use App\Services\Quality\QualityInspectionService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class QualityCenter extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public string $tab = 'pending';  // pending | disputes | history | checklists
    public string $filterPhase = '';
    public string $search = '';

    public function validate_(int $inspectionId): void
    {
        $inspection = MissionQualityInspection::findOrFail($inspectionId);
        try {
            app(QualityInspectionService::class)->validateByAdmin($inspection, Auth::user());
            $this->dispatch('toast', 'Inspection validée admin.', 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', 'Erreur : ' . $e->getMessage(), 'error');
        }
    }

    public function reject(int $inspectionId): void
    {
        $inspection = MissionQualityInspection::findOrFail($inspectionId);
        try {
            app(QualityInspectionService::class)->reject($inspection, Auth::user(), 'Rejected via admin UI');
            $this->dispatch('toast', 'Inspection rejetée.', 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', 'Erreur : ' . $e->getMessage(), 'error');
        }
    }

    public function render(): View
    {
        $kpis = [
            'pending_validation' => MissionQualityInspection::query()
                ->where('status', MissionQualityInspection::STATUS_SUBMITTED)->count(),
            'disputed' => MissionQualityInspection::query()
                ->where('status', MissionQualityInspection::STATUS_DISPUTED)->count(),
            'validated_7d' => MissionQualityInspection::query()
                ->whereIn('status', [
                    MissionQualityInspection::STATUS_VALIDATED_CLIENT,
                    MissionQualityInspection::STATUS_VALIDATED_ADMIN,
                ])
                ->where('validated_at', '>=', now()->subDays(7))->count(),
            'avg_score_7d' => (float) MissionQualityInspection::query()
                ->whereNotNull('score_calculated')
                ->whereNotNull('score_max')
                ->where('score_max', '>', 0)
                ->where('submitted_at', '>=', now()->subDays(7))
                ->avg(\DB::raw('(score_calculated / score_max) * 100')),
        ];

        if ($this->tab === 'pending') {
            $items = MissionQualityInspection::query()
                ->with(['checklist:id,code,name,phase', 'submitter:id,email'])
                ->where('status', MissionQualityInspection::STATUS_SUBMITTED)
                ->when($this->filterPhase, fn ($q) => $q->where('phase', $this->filterPhase))
                ->orderByDesc('submitted_at')
                ->paginate(20);
        } elseif ($this->tab === 'disputes') {
            $items = MissionQualityInspection::query()
                ->with(['checklist:id,code,name', 'submitter:id,email', 'validator:id,email'])
                ->where('status', MissionQualityInspection::STATUS_DISPUTED)
                ->orderByDesc('disputed_at')
                ->paginate(20);
        } elseif ($this->tab === 'history') {
            $items = MissionQualityInspection::query()
                ->with(['checklist:id,code,name', 'submitter:id,email'])
                ->whereIn('status', [
                    MissionQualityInspection::STATUS_VALIDATED_CLIENT,
                    MissionQualityInspection::STATUS_VALIDATED_ADMIN,
                    MissionQualityInspection::STATUS_REJECTED,
                ])
                ->orderByDesc('validated_at')
                ->paginate(25);
        } else {
            $items = QualityChecklist::query()
                ->withCount('items')
                ->orderBy('code')
                ->paginate(25);
        }

        return view('livewire.admin.quality.quality-center', [
            'kpis' => $kpis,
            'items' => $items,
        ]);
    }
}
