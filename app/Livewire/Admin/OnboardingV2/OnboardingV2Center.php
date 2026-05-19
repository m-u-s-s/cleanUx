<?php

namespace App\Livewire\Admin\OnboardingV2;

use App\Models\OnboardingJourney;
use App\Models\OnboardingProgress;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class OnboardingV2Center extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public string $tab = 'progress';  // progress | journeys
    public string $filterStatus = '';
    public string $filterRole = '';
    public string $search = '';

    public function render(): View
    {
        $kpis = [
            'in_progress' => OnboardingProgress::query()->where('status', OnboardingProgress::STATUS_IN_PROGRESS)->count(),
            'completed' => OnboardingProgress::query()->where('status', OnboardingProgress::STATUS_COMPLETED)->count(),
            'abandoned' => OnboardingProgress::query()->where('status', OnboardingProgress::STATUS_ABANDONED)->count(),
            'active_journeys' => OnboardingJourney::query()->where('is_active', true)->count(),
        ];

        if ($this->tab === 'progress') {
            $items = OnboardingProgress::query()
                ->with(['user:id,email,name', 'journey:id,code,name,role'])
                ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
                ->when($this->filterRole, fn ($q) => $q->whereHas('journey', fn ($j) => $j->where('role', $this->filterRole)))
                ->when($this->search, function ($q) {
                    $term = '%' . $this->search . '%';
                    $q->whereHas('user', fn ($u) => $u->where('email', 'like', $term)->orWhere('name', 'like', $term));
                })
                ->orderByDesc('updated_at')
                ->paginate(25);
        } else {
            $items = OnboardingJourney::query()
                ->withCount('steps')
                ->orderBy('role')
                ->orderBy('code')
                ->paginate(25);
        }

        return view('livewire.admin.onboarding-v2.onboarding-v2-center', [
            'kpis' => $kpis,
            'items' => $items,
        ]);
    }
}
