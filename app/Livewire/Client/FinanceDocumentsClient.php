<?php

namespace App\Livewire\Client;

use App\Models\FinanceInvoice;
use App\Models\FinanceQuote;
use App\Models\User;
use App\Services\Entreprise\EntrepriseRoutingService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class FinanceDocumentsClient extends Component
{
    public string $documentType = 'all';
    public string $status = 'all';
    public string $search = '';
    public string $sort = 'recent';

    protected function currentUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
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

                    if ($siteScope === 'selected') {
                        if ($allowedSiteIds === []) {
                            $orgQuery->whereRaw('1 = 0');
                        } else {
                            $orgQuery->whereHas($relationPath, function (Builder $rdvQuery) use ($allowedSiteIds) {
                                $rdvQuery->whereIn('organization_site_id', $allowedSiteIds);
                            });
                        }
                    }
                });
            }
        });
    }

    protected function baseQuotesQuery(): Builder
    {
        return $this->applyClientScope(
            FinanceQuote::query()->with([
                'rendezVous.serviceZone',
                'rendezVous.organizationSite',
                'rendezVous.serviceCatalog',
                'invoice',
            ])
        );
    }

    protected function baseInvoicesQuery(): Builder
    {
        return $this->applyClientScope(
            FinanceInvoice::query()->with([
                'rendezVous.serviceZone',
                'rendezVous.organizationSite',
                'rendezVous.serviceCatalog',
                'payments',
                'reminders',
            ])
        );
    }

    protected function applySearch(Builder $query, string $numberColumn): Builder
    {
        $search = trim($this->search);

        if ($search === '') {
            return $query;
        }

        $like = '%' . $search . '%';

        return $query->where(function (Builder $searchQuery) use ($like, $numberColumn) {
            $searchQuery
                ->where($numberColumn, 'like', $like)
                ->orWhere('status', 'like', $like)
                ->orWhereHas('rendezVous', function (Builder $rdvQuery) use ($like) {
                    $rdvQuery
                        ->where('ville', 'like', $like)
                        ->orWhere('adresse', 'like', $like)
                        ->orWhere('booking_reference', 'like', $like)
                        ->orWhereHas('serviceCatalog', function (Builder $serviceQuery) use ($like) {
                            $serviceQuery->where('name', 'like', $like);
                        });
                });
        });
    }

    protected function applySort(Builder $query, string $amountColumn): Builder
    {
        return match ($this->sort) {
            'oldest' => $query->orderBy('issued_at')->orderBy('id'),
            'amount_desc' => $query->orderByDesc($amountColumn)->latest('id'),
            'amount_asc' => $query->orderBy($amountColumn)->latest('id'),
            default => $query->latest('issued_at')->latest('id'),
        };
    }

    protected function quotesQuery(): Builder
    {
        $query = $this->baseQuotesQuery()
            ->when($this->status !== 'all', fn (Builder $query) => $query->where('status', $this->status));

        $query = $this->applySearch($query, 'quote_number');

        return $this->applySort($query, 'total_amount');
    }

    protected function invoicesQuery(): Builder
    {
        $query = $this->baseInvoicesQuery()
            ->when($this->status !== 'all', fn (Builder $query) => $query->where('status', $this->status));

        $query = $this->applySearch($query, 'invoice_number');

        return $this->applySort($query, 'total_amount');
    }

    public function setDocumentType(string $type): void
    {
        $allowed = ['all', 'quotes', 'invoices'];

        $this->documentType = in_array($type, $allowed, true) ? $type : 'all';
    }

    public function setStatus(string $status): void
    {
        $allowed = array_keys($this->statusOptions);

        $this->status = in_array($status, $allowed, true) ? $status : 'all';
    }

    public function resetFilters(): void
    {
        $this->reset([
            'documentType',
            'status',
            'search',
            'sort',
        ]);
    }

    public function getQuotesProperty(): Collection
    {
        if ($this->documentType === 'invoices') {
            return collect();
        }

        return $this->quotesQuery()->limit(10)->get();
    }

    public function getInvoicesProperty(): Collection
    {
        if ($this->documentType === 'quotes') {
            return collect();
        }

        return $this->invoicesQuery()->limit(10)->get();
    }

    public function getFinanceSummaryProperty(): array
    {
        $quoteRows = $this->baseQuotesQuery()->get(['id', 'total_amount', 'status']);
        $invoiceRows = $this->baseInvoicesQuery()->get(['id', 'balance_due', 'status', 'due_at']);

        $firstQuote = $this->baseQuotesQuery()->latest('issued_at')->first();
        $firstInvoice = $this->baseInvoicesQuery()->latest('issued_at')->first();

        $currencySymbol = $firstQuote?->documentCurrencySymbol()
            ?? $firstInvoice?->documentCurrencySymbol()
            ?? '€';

        $nextDueInvoice = $invoiceRows
            ->filter(fn ($invoice) => (float) $invoice->balance_due > 0 && filled($invoice->due_at))
            ->sortBy('due_at')
            ->first();

        return [
            'quotes_count' => $quoteRows->count(),
            'quotes_pending' => $quoteRows->whereIn('status', ['draft', 'sent'])->count(),
            'quotes_accepted' => $quoteRows->where('status', 'accepted')->count(),
            'invoices_count' => $invoiceRows->count(),
            'paid_count' => $invoiceRows->where('status', 'paid')->count(),
            'partial_count' => $invoiceRows->where('status', 'partial')->count(),
            'overdue_count' => $invoiceRows->where('status', 'overdue')->count(),
            'outstanding_total' => round((float) $invoiceRows->sum('balance_due'), 2),
            'next_due_at' => $nextDueInvoice?->due_at,
            'currency_symbol' => $currencySymbol,
        ];
    }

    public function getPaymentHealthProperty(): array
    {
        $summary = $this->financeSummary;

        if (($summary['overdue_count'] ?? 0) > 0) {
            return [
                'tone' => 'rose',
                'label' => 'Action requise',
                'title' => 'Facture(s) en retard',
                'message' => 'Une ou plusieurs factures nécessitent votre attention.',
            ];
        }

        if (($summary['outstanding_total'] ?? 0) > 0) {
            return [
                'tone' => 'amber',
                'label' => 'À surveiller',
                'title' => 'Solde ouvert',
                'message' => 'Vous avez encore un montant à régler.',
            ];
        }

        return [
            'tone' => 'emerald',
            'label' => 'À jour',
            'title' => 'Situation saine',
            'message' => 'Aucun retard de paiement détecté.',
        ];
    }

    public function getLatestPaymentEventsProperty(): Collection
    {
        return $this->baseInvoicesQuery()
            ->with('payments')
            ->latest('issued_at')
            ->limit(15)
            ->get()
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

    public function getStatusOptionsProperty(): array
    {
        return [
            'all' => 'Tous',
            'draft' => 'Brouillon',
            'sent' => 'Envoyé',
            'accepted' => 'Accepté',
            'issued' => 'Émise',
            'partial' => 'Partiel',
            'paid' => 'Payée',
            'overdue' => 'En retard',
        ];
    }

    public function getSortOptionsProperty(): array
    {
        return [
            'recent' => 'Plus récent',
            'oldest' => 'Plus ancien',
            'amount_desc' => 'Montant décroissant',
            'amount_asc' => 'Montant croissant',
        ];
    }

    public function getActiveFilterLabelProperty(): string
    {
        $parts = [];

        $parts[] = match ($this->documentType) {
            'quotes' => 'Devis uniquement',
            'invoices' => 'Factures uniquement',
            default => 'Tous les documents',
        };

        if ($this->status !== 'all') {
            $parts[] = $this->statusOptions[$this->status] ?? $this->status;
        }

        if (trim($this->search) !== '') {
            $parts[] = 'Recherche : ' . trim($this->search);
        }

        return implode(' · ', $parts);
    }

    public function quoteStatusBadge(string $status): string
    {
        return match ($status) {
            'accepted' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            'sent' => 'bg-sky-50 text-sky-700 border-sky-200',
            'draft' => 'bg-amber-50 text-amber-700 border-amber-200',
            default => 'bg-slate-50 text-slate-700 border-slate-200',
        };
    }

    public function invoiceStatusBadge(string $status): string
    {
        return match ($status) {
            'paid' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            'partial' => 'bg-amber-50 text-amber-700 border-amber-200',
            'overdue' => 'bg-rose-50 text-rose-700 border-rose-200',
            'issued' => 'bg-sky-50 text-sky-700 border-sky-200',
            default => 'bg-slate-50 text-slate-700 border-slate-200',
        };
    }

    public function render(): View
    {
        return view('livewire.client.finance-documents-client', [
            'quotes' => $this->quotes,
            'invoices' => $this->invoices,
            'financeSummary' => $this->financeSummary,
            'subscriptionSummary' => $this->subscriptionSummary,
            'latestPaymentEvents' => $this->latestPaymentEvents,
            'paymentHealth' => $this->paymentHealth,
            'statusOptions' => $this->statusOptions,
            'sortOptions' => $this->sortOptions,
            'activeFilterLabel' => $this->activeFilterLabel,
        ]);
    }
}
