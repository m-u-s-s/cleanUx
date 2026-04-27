<?php

namespace App\Livewire\Admin;

use App\Models\EnterpriseBookingApproval;
use App\Services\Enterprise\EnterpriseBookingApprovalService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class EnterpriseApprovalsCenter extends Component
{
    use WithPagination;

    public string $status = '';
    public string $search = '';
    public ?int $selectedApprovalId = null;
    public string $note = '';
    public string $rejectionReason = '';

    protected $paginationTheme = 'tailwind';

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function approveManager(int $approvalId, EnterpriseBookingApprovalService $service): void
    {
        $approval = EnterpriseBookingApproval::findOrFail($approvalId);

        $service->approveManager($approval, Auth::user(), $this->note ?: null);

        $this->note = '';
        $this->dispatch('toast', 'Validation manager effectuée.', 'success');
    }

    public function approveFinance(int $approvalId, EnterpriseBookingApprovalService $service): void
    {
        $approval = EnterpriseBookingApproval::findOrFail($approvalId);

        $service->approveFinance($approval, Auth::user(), $this->note ?: null);

        $this->note = '';
        $this->dispatch('toast', 'Validation finance effectuée.', 'success');
    }

    public function openRejectModal(int $approvalId): void
    {
        $this->selectedApprovalId = $approvalId;
        $this->rejectionReason = '';
    }

    public function closeRejectModal(): void
    {
        $this->selectedApprovalId = null;
        $this->rejectionReason = '';
    }

    public function reject(EnterpriseBookingApprovalService $service): void
    {
        $this->validate([
            'rejectionReason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        $approval = EnterpriseBookingApproval::findOrFail($this->selectedApprovalId);

        $service->reject($approval, Auth::user(), $this->rejectionReason);

        $this->closeRejectModal();

        $this->dispatch('toast', 'Demande refusée.', 'success');
    }

    public function render(): View
    {
        return view('livewire.admin.enterprise-approvals-center', [
            'approvals' => EnterpriseBookingApproval::query()
                ->with([
                    'rendezVous.client',
                    'rendezVous.serviceCatalog',
                    'organizationAccount',
                    'organizationSite',
                    'requestedBy',
                    'managerApprovedBy',
                    'financeApprovedBy',
                ])
                ->when($this->status, fn ($q) => $q->where('status', $this->status))
                ->when($this->search, function ($query) {
                    $query->where(function ($q) {
                        $q->whereHas('rendezVous.client', fn ($clientQuery) => $clientQuery->where('name', 'like', '%'.$this->search.'%'))
                            ->orWhereHas('organizationAccount', fn ($orgQuery) => $orgQuery->where('name', 'like', '%'.$this->search.'%'))
                            ->orWhereHas('organizationSite', fn ($siteQuery) => $siteQuery->where('name', 'like', '%'.$this->search.'%'));
                    });
                })
                ->latest()
                ->paginate(10),
        ]);
    }
}