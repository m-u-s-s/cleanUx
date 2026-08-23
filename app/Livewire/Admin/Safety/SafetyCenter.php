<?php

namespace App\Livewire\Admin\Safety;

use App\Models\SafetyAlert;
use App\Models\UserBlock;
use App\Models\UserReport;
use App\Services\Safety\SafetyAlertService;
use App\Services\Safety\UserSafetyService;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class SafetyCenter extends Component
{
    use EnforcesAdminAccess;
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public string $tab = 'reports';

    public string $statusFilter = '';

    public string $categoryFilter = '';

    public string $search = '';

    public ?int $selectedReportId = null;

    public string $resolutionNotes = '';

    /** ACCUSER RÉCEPTION D'UNE ALERTE D'URGENCE (E33). */
    public function accuserReception(int $alerteId): void
    {
        $alerte = SafetyAlert::query()->find($alerteId);

        if ($alerte !== null) {
            app(SafetyAlertService::class)->accuserReception($alerte, Auth::user());
        }
    }

    public function cloreLAlerte(int $alerteId, bool $fausseAlerte = false): void
    {
        $alerte = SafetyAlert::query()->find($alerteId);

        if ($alerte === null) {
            return;
        }

        try {
            app(SafetyAlertService::class)->cloturer(
                $alerte,
                Auth::user(),
                $fausseAlerte,
                $this->resolutionNotes !== '' ? $this->resolutionNotes : null,
            );

            $this->resolutionNotes = '';
        } catch (\DomainException $e) {
            $this->addError('alerte', $e->getMessage());
        }
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
        $this->resetPage();
    }

    public function openReport(int $id): void
    {
        $this->selectedReportId = $id;
        $this->resolutionNotes = '';
    }

    public function closeReport(): void
    {
        $this->selectedReportId = null;
        $this->resolutionNotes = '';
    }

    public function resolve(string $resolution): void
    {
        $report = UserReport::find($this->selectedReportId);
        if (! $report) {
            return;
        }
        try {
            app(UserSafetyService::class)->resolveReport(
                $report,
                Auth::user(),
                $resolution,
                $this->resolutionNotes ?: null,
            );
            $this->dispatch('toast', 'Signalement résolu.', 'success');
            $this->closeReport();
        } catch (\Throwable $e) {
            $this->dispatch('toast', 'Erreur : '.$e->getMessage(), 'error');
        }
    }

    public function render(): View
    {
        $reports = UserReport::query()
            ->with(['reporter:id,name,email', 'reported:id,name,email', 'reviewer:id,name'])
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->categoryFilter, fn ($q) => $q->where('category', $this->categoryFilter))
            ->when($this->search, function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($w) use ($term) {
                    $w->where('code', 'like', $term)
                        ->orWhere('description', 'like', $term)
                        ->orWhereHas('reporter', fn ($u) => $u->where('email', 'like', $term))
                        ->orWhereHas('reported', fn ($u) => $u->where('email', 'like', $term));
                });
            })
            ->orderByDesc('created_at')
            ->paginate(20, ['*'], 'reportsPage');

        $blocks = UserBlock::query()
            ->with(['blocker:id,name,email', 'blocked:id,name,email'])
            ->orderByDesc('created_at')
            ->paginate(20, ['*'], 'blocksPage');

        $stats = [
            'pending' => UserReport::query()->where('status', UserReport::STATUS_PENDING)->count(),
            'under_review' => UserReport::query()->where('status', UserReport::STATUS_UNDER_REVIEW)->count(),
            'resolved_action' => UserReport::query()->where('status', UserReport::STATUS_RESOLVED_ACTION)->count(),
            'total_blocks' => UserBlock::query()->count(),
        ];

        $selectedReport = $this->selectedReportId
            ? UserReport::query()->with(['reporter:id,name,email', 'reported:id,name,email'])->find($this->selectedReportId)
            : null;

        return view('livewire.admin.safety.safety-center', [
            // LES ALERTES D'URGENCE (E33), EN TÊTE DE L'ÉCRAN.
            'alertes' => app(SafetyAlertService::class)->ouvertes(),
            'reports' => $reports,
            'blocks' => $blocks,
            'stats' => $stats,
            'selectedReport' => $selectedReport,
            'categories' => UserReport::CATEGORIES,
        ]);
    }
}
