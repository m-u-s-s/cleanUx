<?php

namespace App\Livewire\Admin\Gdpr;

use App\Models\ActivityLog;
use App\Models\GdprDataRequest;
use App\Services\Gdpr\DataErasureService;
use App\Services\Gdpr\RetentionPolicyService;
use App\Support\ActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\WithPagination;

class GdprCenter extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public string $tab = 'requests';
    public string $filterStatus = '';
    public string $filterType = '';
    public string $search = '';

    public string $auditSearch = '';
    public string $auditDomain = '';

    public function cancelRequest(int $requestId): void
    {
        $req = GdprDataRequest::findOrFail($requestId);
        app(DataErasureService::class)->cancel($req, Auth::user(), 'Annulé par admin');
        $this->dispatch('toast', 'Demande annulée.', 'success');
    }

    public function executeNow(int $requestId): void
    {
        $req = GdprDataRequest::findOrFail($requestId);

        if ($req->type !== GdprDataRequest::TYPE_ERASURE) {
            $this->dispatch('toast', 'Action disponible uniquement pour les erasures.', 'error');
            return;
        }

        // Forcer l'exécution même si grace period non écoulée
        $req->forceFill(['grace_period_ends_at' => now()->subMinute()])->save();

        try {
            app(DataErasureService::class)->execute($req->fresh());
            $this->dispatch('toast', 'Erasure exécutée.', 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', 'Erreur: ' . $e->getMessage(), 'error');
        }
    }

    public function runRetention(): void
    {
        $stats = app(RetentionPolicyService::class)->enforceAll();
        $total = array_sum($stats);
        $this->dispatch('toast', "Retention appliquée — {$total} ligne(s) purgée(s).", 'success');
    }

    public function render(): View
    {
        $kpis = [
            'pending_export' => GdprDataRequest::query()
                ->ofType(GdprDataRequest::TYPE_EXPORT)
                ->whereIn('status', [GdprDataRequest::STATUS_PENDING, GdprDataRequest::STATUS_PROCESSING])
                ->count(),
            'awaiting_erasure' => GdprDataRequest::query()
                ->ofType(GdprDataRequest::TYPE_ERASURE)
                ->where('status', GdprDataRequest::STATUS_AWAITING_GRACE_PERIOD)
                ->count(),
            'ready_for_execution' => GdprDataRequest::query()->readyForExecution()->count(),
            'anonymized_total' => \App\Models\User::query()->whereNotNull('anonymized_at')->count(),
        ];

        $items = collect();
        $auditItems = collect();
        $view = $this->tab;

        if ($this->tab === 'requests') {
            $items = GdprDataRequest::query()
                ->with(['user:id,name,email', 'processedBy:id,name'])
                ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
                ->when($this->filterType, fn ($q) => $q->where('type', $this->filterType))
                ->when($this->search, function ($q) {
                    $term = '%' . $this->search . '%';
                    $q->where(function ($inner) use ($term) {
                        $inner->where('reference', 'like', $term)
                            ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $term)->orWhere('email', 'like', $term));
                    });
                })
                ->latest('id')
                ->paginate(15);
        } elseif ($this->tab === 'audit') {
            if (Schema::hasTable('activity_logs')) {
                $auditItems = ActivityLog::query()
                    ->when($this->auditDomain, fn ($q) => $q->where('domain', $this->auditDomain))
                    ->when($this->auditSearch, function ($q) {
                        $term = '%' . $this->auditSearch . '%';
                        $q->where(function ($inner) use ($term) {
                            $inner->where('action', 'like', $term)
                                ->orWhere('target_type', 'like', $term);
                        });
                    })
                    ->latest('id')
                    ->paginate(20);
            }
        }

        return view('livewire.admin.gdpr.gdpr-center', [
            'kpis' => $kpis,
            'items' => $items,
            'auditItems' => $auditItems,
            'currentView' => $view,
        ]);
    }
}
