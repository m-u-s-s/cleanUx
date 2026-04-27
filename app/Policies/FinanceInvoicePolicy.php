<?php

namespace App\Policies;

use App\Models\FinanceInvoice;
use App\Models\User;

class FinanceInvoicePolicy
{
    public function view(User $user, FinanceInvoice $invoice): bool
    {
        if ($user->isAdmin() && $user->hasPermission('manage-finance')) {
            return true;
        }

        if ($user->isClient()) {
            return $invoice->client_id === $user->id
                || $invoice->organization_account_id === $user->organization_account_id;
        }

        return false;
    }

    public function download(User $user, FinanceInvoice $invoice): bool
    {
        return $this->view($user, $invoice);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin()
            && ! $user->isReadOnlyAdmin()
            && $user->hasPermission('manage-finance');
    }

    public function update(User $user, FinanceInvoice $invoice): bool
    {
        return $user->isAdmin()
            && ! $user->isReadOnlyAdmin()
            && $user->hasPermission('manage-finance');
    }

    public function delete(User $user, FinanceInvoice $invoice): bool
    {
        return $user->isAdmin()
            && $user->canPerformCriticalAdminActions();
    }
}