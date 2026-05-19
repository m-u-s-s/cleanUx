<?php

namespace App\Livewire\Admin\Insurance;

use App\Models\BookingInsurance;
use App\Models\InsuranceClaim;
use App\Models\InsurancePlan;
use App\Support\ActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class InsuranceCenter extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public string $tab = 'claims';  // claims | policies | plans
    public string $filterStatus = '';

    public function setClaimStatus(int $claimId, string $newStatus, ?string $reason = null): void
    {
        $claim = InsuranceClaim::findOrFail($claimId);

        $allowed = [
            InsuranceClaim::STATUS_UNDER_REVIEW,
            InsuranceClaim::STATUS_INFO_REQUESTED,
            InsuranceClaim::STATUS_ACCEPTED,
            InsuranceClaim::STATUS_REJECTED,
            InsuranceClaim::STATUS_PAID,
        ];
        if (! in_array($newStatus, $allowed, true)) {
            $this->dispatch('toast', 'Statut invalide', 'error');
            return;
        }

        $claim->forceFill([
            'status' => $newStatus,
            'decision_reason' => $reason ?? $claim->decision_reason,
            'reviewed_at' => $claim->reviewed_at ?? now(),
            'decided_at' => in_array($newStatus, [
                InsuranceClaim::STATUS_ACCEPTED, InsuranceClaim::STATUS_REJECTED, InsuranceClaim::STATUS_PAID,
            ], true) ? now() : $claim->decided_at,
            'paid_at' => $newStatus === InsuranceClaim::STATUS_PAID ? now() : $claim->paid_at,
        ])->save();

        ActivityLogger::log('insurance.claim_status_updated', $claim, [
            'admin_user_id' => Auth::id(),
            'new_status' => $newStatus,
        ]);

        $this->dispatch('toast', "Claim {$newStatus}", 'success');
    }

    public function render(): View
    {
        $kpis = [
            'plans_active' => InsurancePlan::query()->where('is_active', true)->count(),
            'policies_active' => BookingInsurance::query()->where('status', BookingInsurance::STATUS_ACTIVE)->count(),
            'claims_open' => InsuranceClaim::query()->open()->count(),
            'claims_paid_30d' => InsuranceClaim::query()
                ->where('status', InsuranceClaim::STATUS_PAID)
                ->where('paid_at', '>=', now()->subDays(30))->count(),
        ];

        if ($this->tab === 'claims') {
            $items = InsuranceClaim::query()
                ->with(['claimant:id,email,name', 'insurance:id,booking_id,external_provider,policy_number'])
                ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
                ->orderByDesc('filed_at')
                ->paginate(20);
        } elseif ($this->tab === 'policies') {
            $items = BookingInsurance::query()
                ->with(['plan:id,code,name', 'user:id,email,name'])
                ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
                ->orderByDesc('purchased_at')
                ->paginate(20);
        } else {
            $items = InsurancePlan::query()
                ->orderBy('code')
                ->paginate(20);
        }

        return view('livewire.admin.insurance.insurance-center', [
            'kpis' => $kpis,
            'items' => $items,
        ]);
    }
}
