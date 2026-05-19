<?php

namespace App\Livewire\Admin\Promotions;

use App\Models\Referral;
use App\Models\ReferralReward;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class ReferralsCenter extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public string $search = '';
    public string $filterStatus = '';

    public function flagFraud(int $referralId): void
    {
        $referral = Referral::findOrFail($referralId);
        $referral->update(['status' => Referral::STATUS_FRAUD]);

        $referral->rewards()->update([
            'status' => ReferralReward::STATUS_REVOKED,
            'revoked_at' => now(),
            'revoked_reason' => 'Marqué frauduleux par admin',
        ]);

        ActivityLogger::log('referral.flagged_fraud', $referral, [
            'admin_user_id' => auth()->id(),
        ]);

        $this->dispatch('toast', 'Parrainage marqué frauduleux et récompenses révoquées.', 'success');
    }

    public function render(): View
    {
        $kpis = [
            'total_referrals' => Referral::query()->count(),
            'qualified' => Referral::query()->whereIn('status', [
                Referral::STATUS_QUALIFIED,
                Referral::STATUS_REWARDED,
            ])->count(),
            'rewarded' => Referral::query()->where('status', Referral::STATUS_REWARDED)->count(),
            'fraud' => Referral::query()->where('status', Referral::STATUS_FRAUD)->count(),
            'total_rewards_value' => (float) ReferralReward::query()
                ->whereIn('status', [ReferralReward::STATUS_GRANTED, ReferralReward::STATUS_CONSUMED])
                ->sum('amount'),
        ];

        $topReferrers = User::query()
            ->select('users.id', 'users.name', 'users.email', 'users.referral_code')
            ->selectSub(
                Referral::query()
                    ->selectRaw('count(*)')
                    ->whereColumn('referrer_user_id', 'users.id')
                    ->whereIn('status', [Referral::STATUS_QUALIFIED, Referral::STATUS_REWARDED]),
                'qualified_count'
            )
            ->selectSub(
                ReferralReward::query()
                    ->selectRaw('COALESCE(SUM(amount), 0)')
                    ->whereColumn('beneficiary_user_id', 'users.id')
                    ->where('role', ReferralReward::ROLE_REFERRER)
                    ->whereIn('status', [ReferralReward::STATUS_GRANTED, ReferralReward::STATUS_CONSUMED]),
                'total_earned'
            )
            ->whereNotNull('users.referral_code')
            ->orderByDesc('qualified_count')
            ->limit(20)
            ->get();

        $referrals = Referral::query()
            ->with(['referrer:id,name,email', 'referee:id,name,email'])
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->search, function ($q) {
                $term = '%' . $this->search . '%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('referral_code', 'like', $term)
                        ->orWhere('referee_email', 'like', $term)
                        ->orWhereHas('referrer', fn ($u) => $u->where('name', 'like', $term)->orWhere('email', 'like', $term))
                        ->orWhereHas('referee', fn ($u) => $u->where('name', 'like', $term)->orWhere('email', 'like', $term));
                });
            })
            ->latest()
            ->paginate(15);

        return view('livewire.admin.promotions.referrals-center', [
            'kpis' => $kpis,
            'topReferrers' => $topReferrers,
            'referrals' => $referrals,
        ]);
    }
}
