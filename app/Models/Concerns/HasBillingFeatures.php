<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait HasBillingFeatures
{
    public function isPremium(): bool
    {
        return ($this->plan_type ?? 'standard') === 'premium'
            && in_array(($this->plan_status ?? 'inactive'), ['active', 'trialing', 'paid'], true);
    }

    public function hasBillingIssue(): bool
    {
        $status = $this->plan_status ?? null;

        return in_array($status, ['past_due', 'unpaid', 'incomplete', 'incomplete_expired'], true);
    }

    public function canChooseEmployee(): bool
    {
        return in_array($this->plan_type ?? 'standard', ['premium', 'business', 'enterprise'], true)
            || $this->isEntreprise()
            || $this->isPlatformAdmin();
    }

    public function canViewEmployeeAvailability(): bool
    {
        return $this->isPremium()
            || $this->isAdmin()
            || $this->isEmploye()
            || $this->isEntreprise();
    }

    public function activeCreditBalance(): float
    {
        if (! Schema::hasTable('client_credits')) {
            return 0.0;
        }

        return (float) DB::table('client_credits')
            ->where('user_id', $this->id)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->sum('remaining_amount');
    }
}
