<?php

namespace App\Livewire\Client;

use App\Models\FinanceInvoice;
use App\Models\FinanceQuote;
use App\Models\User;
use App\Services\Entreprise\EntrepriseRoutingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class FinanceDocumentsClient extends Component
{
    public string $documentType = 'all';
    public string $status = 'all';

    protected function currentUser(): ?User
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user;
    }

    protected function entrepriseRouting(): EntrepriseRoutingService
    {
        return app(EntrepriseRoutingService::class);
    }

    protected function allowedSiteIdsForCurrentUser(): array
    {
        $user = $this->currentUser();

        if (! $user) {
            return [];
        }

        return $this->entrepriseRouting()->allowedSiteIdsForUser($user);
    }

    protected function applyClientScope(Builder $query, string $relationPath = 'rendezVous'): Builder
    {
        $user = $this->currentUser();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        $allowedSiteIds = $this->allowedSiteIdsForCurrentUser();
        $siteScope = (string) data_get($user->metadata, 'entreprise_context.site_scope', 'all');
        $organizationId = (int) ($user->organization_account_id ?? 0);

        return $query->where(function (Builder $scoped) use ($user, $allowedSiteIds, $siteScope, $organizationId, $relationPath) {
            $scoped->where('client_id', $user->id);

            if ($organizationId > 0) {
                $scoped->orWhere(function (Builder $orgQuery) use ($organizationId, $allowedSiteIds, $siteScope, $relationPath) {
                    $orgQuery->where('organization_account_id', $organizationId);

                    if ($siteScope === 'selected' && $allowedSiteIds !== []) {
                        $orgQuery->whereHas($relationPath, function (Builder $rdvQuery) use ($allowedSiteIds) {
                            $rdvQuery->whereIn('organization_site_id', $allowedSiteIds);
                        });
                    }
                });
            }
        });
    }

    protected function quotesQuery(): Builder
    {
        return $this->applyClientScope(
            FinanceQuote::query()->with(['rendezVous.serviceZone', 'rendezVous.organizationSite', 'invoice'])
        )
            ->when($this->status !== 'all', fn (Builder $query) => $query->where('status', $this->status))
            ->latest('issued_at')
            ->latest('id');
    }

    protected function invoicesQuery(): Builder
    {
        return $this->applyClientScope(
            FinanceInvoice::query()->with(['rendezVous.serviceZone', 'rendezVous.organizationSite', 'payments', 'reminders'])
        )
            ->when($this->status !== 'all', fn (Builder $query) => $query->where('status', $this->status))
            ->latest('issued_at')
            ->latest('id');
    }

    public function setDocumentType(string $type): void
    {
        $allowed = ['all', 'quotes', 'invoices'];
        $this->documentType = in_array($type, $allowed, true) ? $type : 'all';
    }

    public function setStatus(string $status): void
    {
        $allowed = ['all', 'draft', 'sent', 'accepted', 'issued', 'partial', 'paid', 'overdue'];
        $this->status = in_array($status, $allowed, true) ? $status : 'all';
    }

    public function getQuotesProperty(): Collection
    {
        if ($this->documentType === 'invoices') {
            return collect();
        }

        return $this->quotesQuery()->limit(8)->get();
    }

    public function getInvoicesProperty(): Collection
    {
        if ($this->documentType === 'quotes') {
            return collect();
        }

        return $this->invoicesQuery()->limit(8)->get();
    }

    public function getFinanceSummaryProperty(): array
    {
        $quoteRows = $this->quotesQuery()->get(['id', 'total_amount', 'status']);
        $invoiceRows = $this->invoicesQuery()->get(['id', 'balance_due', 'status', 'due_at']);

        $currencySymbol = $this->quotes->first()?->documentCurrencySymbol()
            ?? $this->invoices->first()?->documentCurrencySymbol()
            ?? '€';

        return [
            'quotes_count' => $quoteRows->count(),
            'quotes_pending' => $quoteRows->whereIn('status', ['draft', 'sent'])->count(),
            'invoices_count' => $invoiceRows->count(),
            'outstanding_total' => round((float) $invoiceRows->sum('balance_due'), 2),
            'overdue_count' => $invoiceRows->where('status', 'overdue')->count(),
            'currency_symbol' => $currencySymbol,
        ];
    }

    public function getLatestPaymentEventsProperty(): Collection
    {
        return $this->invoices
            ->flatMap(fn (FinanceInvoice $invoice) => $invoice->payments)
            ->sortByDesc(fn ($payment) => $payment->paid_at ?? $payment->created_at)
            ->take(5)
            ->values();
    }

    public function getSubscriptionSummaryProperty(): array
    {
        $user = $this->currentUser();
        $subscription = $user?->subscription('default');

        return [
            'plan_type' => $user?->plan_type ?? 'standard',
            'plan_status' => $user?->plan_status ?? 'inactive',
            'is_premium' => (bool) $user?->isPremium(),
            'is_past_due' => $user ? (method_exists($user, 'hasBillingIssue') ? (bool) $user->hasBillingIssue() : false) : false,
            'renewal_at' => $user?->premium_renewal_at,
            'subscription' => $subscription,
        ];
    }

    public function quoteStatusBadge(string $status): string
    {
        return match ($status) {
            'accepted' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            'sent' => 'bg-sky-50 text-sky-700 border-sky-200',
            default => 'bg-slate-50 text-slate-700 border-slate-200',
        };
    }

    public function invoiceStatusBadge(string $status): string
    {
        return match ($status) {
            'paid' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            'partial' => 'bg-amber-50 text-amber-700 border-amber-200',
            'overdue' => 'bg-rose-50 text-rose-700 border-rose-200',
            default => 'bg-slate-50 text-slate-700 border-slate-200',
        };
    }

    public function render()
    {
        return view('livewire.client.finance-documents-client', [
            'quotes' => $this->quotes,
            'invoices' => $this->invoices,
            'financeSummary' => $this->financeSummary,
            'subscriptionSummary' => $this->subscriptionSummary,
            'latestPaymentEvents' => $this->latestPaymentEvents,
        ])->layout('layouts.app');
    }
}
