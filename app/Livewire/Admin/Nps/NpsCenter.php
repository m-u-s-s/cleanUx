<?php

namespace App\Livewire\Admin\Nps;

use App\Models\NpsResponse;
use App\Services\Nps\NpsService;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Component;
use Livewire\WithPagination;

class NpsCenter extends Component
{
    use EnforcesAdminAccess;
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public string $period = '30d';

    public string $surveyFilter = '';

    public string $categoryFilter = '';

    public function setPeriod(string $period): void
    {
        $this->period = $period;
        $this->resetPage();
    }

    public function render(): View
    {
        $since = match ($this->period) {
            '7d' => Carbon::now()->subDays(7),
            '30d' => Carbon::now()->subDays(30),
            '90d' => Carbon::now()->subDays(90),
            'all' => Carbon::create(2020, 1, 1),
            default => Carbon::now()->subDays(30),
        };
        $until = Carbon::now();

        $score = app(NpsService::class)->calculate($since, $until, $this->surveyFilter ?: null);

        $rows = NpsResponse::query()
            ->with('user:id,name,email')
            ->whereBetween('responded_at', [$since, $until])
            ->when($this->surveyFilter, fn ($q) => $q->where('survey_code', $this->surveyFilter))
            ->when($this->categoryFilter, fn ($q) => $q->where('category', $this->categoryFilter))
            ->orderByDesc('responded_at')
            ->paginate(20);

        return view('livewire.admin.nps.nps-center', [
            'score' => $score,
            'rows' => $rows,
        ]);
    }
}
