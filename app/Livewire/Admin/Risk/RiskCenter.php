<?php

namespace App\Livewire\Admin\Risk;

use App\Models\RiskEvaluation;
use App\Models\RiskHold;
use App\Services\Risk\RiskScoringEngine;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class RiskCenter extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public string $tab = 'pending';  // pending | history
    public string $filterDecision = '';
    public string $filterContext = '';
    public string $search = '';

    public function approve(int $holdId): void
    {
        $hold = RiskHold::findOrFail($holdId);

        try {
            app(RiskScoringEngine::class)->reviewHold(
                $hold, Auth::user(), 'approved', 'Approved via admin UI',
            );
            $this->dispatch('toast', 'Hold approuvé, user débloqué.', 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', 'Erreur : ' . $e->getMessage(), 'error');
        }
    }

    public function reject(int $holdId): void
    {
        $hold = RiskHold::findOrFail($holdId);

        try {
            app(RiskScoringEngine::class)->reviewHold(
                $hold, Auth::user(), 'rejected', 'Rejected via admin UI',
            );
            $this->dispatch('toast', 'Hold rejeté, action bloquée.', 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', 'Erreur : ' . $e->getMessage(), 'error');
        }
    }

    public function render(): View
    {
        $kpis = [
            'evaluations_24h' => RiskEvaluation::query()->recent(now()->subDay())->count(),
            'blocked_24h' => RiskEvaluation::query()->recent(now()->subDay())->blocked()->count(),
            'review_24h' => RiskEvaluation::query()->recent(now()->subDay())
                ->where('decision', RiskEvaluation::DECISION_REVIEW)->count(),
            'active_holds' => RiskHold::query()->where('status', RiskHold::STATUS_ACTIVE)->count(),
        ];

        if ($this->tab === 'pending') {
            $items = RiskHold::query()
                ->with(['user:id,name,email', 'evaluation:id,context,score,decision,reason'])
                ->where('status', RiskHold::STATUS_ACTIVE)
                ->orderByDesc('created_at')
                ->paginate(20);
        } else {
            $items = RiskEvaluation::query()
                ->with('user:id,name,email')
                ->when($this->filterDecision, fn ($q) => $q->where('decision', $this->filterDecision))
                ->when($this->filterContext, fn ($q) => $q->where('context', $this->filterContext))
                ->when($this->search, function ($q) {
                    $term = '%' . $this->search . '%';
                    $q->whereHas('user', fn ($u) => $u->where('email', 'like', $term)->orWhere('name', 'like', $term));
                })
                ->orderByDesc('evaluated_at')
                ->paginate(25);
        }

        return view('livewire.admin.risk.risk-center', [
            'kpis' => $kpis,
            'items' => $items,
        ]);
    }
}
