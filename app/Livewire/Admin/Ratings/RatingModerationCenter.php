<?php

namespace App\Livewire\Admin\Ratings;

use App\Models\Feedback;
use App\Models\RatingReport;
use App\Services\Rating\RatingModerationService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class RatingModerationCenter extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public string $tab = 'pending_reports';
    public string $search = '';

    public function hide(int $feedbackId): void
    {
        $feedback = Feedback::findOrFail($feedbackId);
        app(RatingModerationService::class)
            ->resolveReports($feedback, Auth::user(), keep: false);
        $this->dispatch('toast', 'Avis masqué.', 'success');
    }

    public function keep(int $feedbackId): void
    {
        $feedback = Feedback::findOrFail($feedbackId);
        app(RatingModerationService::class)
            ->resolveReports($feedback, Auth::user(), keep: true);
        $this->dispatch('toast', 'Avis conservé.', 'success');
    }

    public function restore(int $feedbackId): void
    {
        $feedback = Feedback::findOrFail($feedbackId);
        app(RatingModerationService::class)->restore($feedback, Auth::user());
        $this->dispatch('toast', 'Avis restauré.', 'success');
    }

    public function dismissReport(int $reportId): void
    {
        $report = RatingReport::findOrFail($reportId);
        $report->update([
            'status' => RatingReport::STATUS_DISMISSED,
            'reviewed_by_user_id' => Auth::id(),
            'reviewed_at' => now(),
        ]);
        $this->dispatch('toast', 'Signalement rejeté.', 'success');
    }

    public function render(): View
    {
        $kpis = [
            'pending_reports' => RatingReport::query()->where('status', RatingReport::STATUS_PENDING)->count(),
            'hidden_total' => Feedback::query()->where('is_hidden', true)->count(),
            'published_total' => Feedback::query()->where('status', Feedback::STATUS_PUBLISHED)->count(),
            'auto_hidden' => Feedback::query()
                ->where('is_hidden', true)
                ->where('hidden_reason', 'like', 'auto_hidden_%')
                ->count(),
        ];

        if ($this->tab === 'pending_reports') {
            $items = RatingReport::query()
                ->with(['feedback.client:id,name', 'feedback.provider:id,name', 'reporter:id,name,email'])
                ->where('status', RatingReport::STATUS_PENDING)
                ->latest()
                ->paginate(15);
            $view = 'pending';
        } elseif ($this->tab === 'hidden') {
            $items = Feedback::query()
                ->with(['client:id,name', 'provider:id,name'])
                ->where('is_hidden', true)
                ->latest('hidden_at')
                ->paginate(15);
            $view = 'hidden';
        } else {
            $items = Feedback::query()
                ->with(['client:id,name', 'provider:id,name'])
                ->where('status', Feedback::STATUS_PUBLISHED)
                ->when($this->search, function ($q) {
                    $term = '%' . $this->search . '%';
                    $q->where(function ($inner) use ($term) {
                        $inner->where('commentaire', 'like', $term)
                            ->orWhere('comment', 'like', $term)
                            ->orWhereHas('provider', fn ($p) => $p->where('name', 'like', $term))
                            ->orWhereHas('client', fn ($c) => $c->where('name', 'like', $term));
                    });
                })
                ->latest('published_at')
                ->paginate(15);
            $view = 'all';
        }

        return view('livewire.admin.ratings.rating-moderation-center', [
            'kpis' => $kpis,
            'items' => $items,
            'currentView' => $view,
        ]);
    }
}
