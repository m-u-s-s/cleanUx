<?php

namespace App\Models\Concerns;

use App\Models\CustomerCredit;

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

    /**
     * LA TABLE S'APPELLE `customer_credits`, ET LA COLONNE `client_id`.
     *
     * `Schema::hasTable('client_credits')` était toujours faux : cette table n'existe dans aucune
     * migration. La méthode rendait donc 0.0 en permanence, et la garde de `CreateBookingAction`
     * fermait DÉFINITIVEMENT l'unique appel à `applyAvailableCredits()` — les avoirs étaient
     * accordés, stockés, affichés, et jamais déduits d'une réservation.
     *
     * Le prédicat est celui de `CustomerCreditApplicationService` : promettre un solde que le
     * service ne saurait pas consommer serait le même défaut dans l'autre sens.
     */
    public function activeCreditBalance(): float
    {
        return (float) CustomerCredit::query()
            ->where('client_id', $this->id)
            ->where('status', 'active')
            ->where('remaining_amount', '>', 0)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->sum('remaining_amount');
    }
}
