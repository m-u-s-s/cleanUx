<?php

namespace App\Livewire\Admin\Kyc;

use App\Models\KycVerification;
use App\Services\Kyc\KycVerificationService;
use App\Support\ActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class KycVerificationsCenter extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public string $filterStatus = '';
    public string $filterDecision = '';
    public string $filterProvider = '';
    public string $search = '';

    public ?int $selectedId = null;
    public string $manualNote = '';
    public string $manualReason = '';

    public function select(int $id): void
    {
        $this->selectedId = $id;
        $this->reset(['manualNote', 'manualReason']);
    }

    public function closeDetail(): void
    {
        $this->reset(['selectedId', 'manualNote', 'manualReason']);
    }

    public function syncStatus(): void
    {
        $verification = KycVerification::findOrFail($this->selectedId);

        try {
            app(KycVerificationService::class)->syncStatus($verification);
            $this->dispatch('toast', 'Statut synchronisé.', 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', 'Erreur sync: ' . $e->getMessage(), 'error');
        }
    }

    public function approve(): void
    {
        $verification = KycVerification::findOrFail($this->selectedId);

        try {
            app(KycVerificationService::class)
                ->approveManually($verification, Auth::user(), $this->manualNote ?: null);
            $this->dispatch('toast', 'Vérification approuvée manuellement.', 'success');
            $this->reset(['manualNote']);
        } catch (\Throwable $e) {
            $this->dispatch('toast', 'Erreur: ' . $e->getMessage(), 'error');
        }
    }

    public function reject(): void
    {
        $this->validate([
            'manualReason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $verification = KycVerification::findOrFail($this->selectedId);

        try {
            app(KycVerificationService::class)
                ->rejectManually($verification, Auth::user(), $this->manualReason);
            $this->dispatch('toast', 'Vérification rejetée.', 'success');
            $this->reset(['manualReason']);
        } catch (\Throwable $e) {
            $this->dispatch('toast', 'Erreur: ' . $e->getMessage(), 'error');
        }
    }

    public function render(): View
    {
        $kpis = [
            'total' => KycVerification::query()->count(),
            'pending' => KycVerification::query()->pending()->count(),
            'requiring_review' => KycVerification::query()->requiringReview()->count(),
            'approved' => KycVerification::query()->where('decision', KycVerification::DECISION_APPROVED)->count(),
            'rejected' => KycVerification::query()->where('decision', KycVerification::DECISION_REJECTED)->count(),
        ];

        $list = KycVerification::query()
            ->with(['user:id,name,email', 'reviewer:id,name'])
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterDecision, fn ($q) => $q->where('decision', $this->filterDecision))
            ->when($this->filterProvider, fn ($q) => $q->where('provider', $this->filterProvider))
            ->when($this->search, function ($q) {
                $term = '%' . $this->search . '%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('external_applicant_id', 'like', $term)
                        ->orWhere('external_check_id', 'like', $term)
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $term)->orWhere('email', 'like', $term));
                });
            })
            ->latest('id')
            ->paginate(15);

        $selected = $this->selectedId
            ? KycVerification::query()
                ->with(['user:id,name,email', 'reviewer:id,name', 'checks'])
                ->find($this->selectedId)
            : null;

        return view('livewire.admin.kyc.kyc-verifications-center', [
            'kpis' => $kpis,
            'list' => $list,
            'selected' => $selected,
        ]);
    }
}
