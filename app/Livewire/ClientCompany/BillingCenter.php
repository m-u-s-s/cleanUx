<?php

namespace App\Livewire\ClientCompany;

use App\Models\FinanceInvoice;
use App\Models\OrganizationSite;
use App\Services\PermissionService;
use App\Support\Finance\ClientFinanceDocumentScope;
use App\Support\Livewire\Concerns\EnforcesActiveOrgMembership;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * FACTURATION DE LA SOCIÉTÉ CLIENTE — sur les vraies factures.
 *
 * CET ÉCRAN MENTAIT. Les quatre indicateurs étaient des zéros écrits en dur (« Données simulées — à
 * connecter à Invoice model ») et la table de factures était une collection vide. Pendant ce temps,
 * l'application mobile affichait les VRAIS montants du même compte, par la même porte
 * (`ClientFinanceDocumentScope`). Une entreprise consultait donc son solde sur son téléphone et
 * voyait 0,00 € sur son ordinateur — l'écart n'était pas une panne, c'était l'écran web qui
 * n'avait jamais été branché.
 *
 * LE SCOPE N'EST PAS RÉÉCRIT ICI, et c'est le point important : `ClientFinanceDocumentScope` porte
 * déjà les règles d'isolation (ses propres factures, plus celles de son organisation, en honorant
 * la restriction par site d'un membre). Les redéfinir aurait créé une seconde vérité — exactement
 * ce qui a produit les fuites inter-organisations corrigées ailleurs dans ce dépôt.
 *
 * @property-read array<string, mixed> $summary
 * @property-read LengthAwarePaginator<int, FinanceInvoice> $invoices
 * @property-read Collection<int, OrganizationSite> $sites
 */
class BillingCenter extends Component
{
    use EnforcesActiveOrgMembership;
    use WithPagination;

    public string $filterStatus = '';

    public ?int $filterSiteId = null;

    public string $filterPeriod = 'month';

    public string $searchRef = '';

    public function mount(): void
    {
        abort_unless(
            app(PermissionService::class)->can(Auth::user(), 'finance.view', Auth::user()->currentOrganization),
            403
        );
    }

    /**
     * Chaque filtre remet la pagination à la première page : sans cela, filtrer depuis la page 3
     * affiche une liste vide et donne l'impression qu'il n'y a rien à voir.
     */
    public function updated(string $property): void
    {
        if (in_array($property, ['filterStatus', 'filterSiteId', 'filterPeriod', 'searchRef'], true)) {
            $this->resetPage();
        }
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function periodDates(): array
    {
        return match ($this->filterPeriod) {
            'quarter' => [now()->startOfQuarter(), now()->endOfQuarter()],
            'year' => [now()->startOfYear(), now()->endOfYear()],
            'all' => [now()->subYears(10), now()],
            default => [now()->startOfMonth(), now()->endOfMonth()],
        };
    }

    /**
     * Les factures que CE membre a le droit de voir, avant tout filtre d'écran.
     *
     * @return Builder<FinanceInvoice>
     */
    private function baseQuery(): Builder
    {
        return ClientFinanceDocumentScope::apply(FinanceInvoice::query(), Auth::user());
    }

    /** @return array<string, mixed> */
    public function getSummaryProperty(): array
    {
        [$from, $to] = $this->periodDates();

        /*
         * `issued_at` DATE LA FACTURE, pas `created_at` : une facture de janvier saisie en février
         * appartient à janvier. Compter sur la date d'écriture ferait sauter des montants d'un mois
         * à l'autre au gré de la saisie.
         */
        $duMois = (clone $this->baseQuery())->whereBetween('issued_at', [$from, $to]);

        return [
            'total_month' => round((float) (clone $duMois)->sum('total_amount'), 2),
            'total_year' => round((float) (clone $this->baseQuery())
                ->whereBetween('issued_at', [now()->startOfYear(), now()->endOfYear()])
                ->sum('total_amount'), 2),
            // L'impayé n'est PAS borné à la période : une facture en retard de l'an dernier reste
            // due aujourd'hui, et l'effacer du bandeau reviendrait à cacher la dette.
            'unpaid' => round((float) (clone $this->baseQuery())->sum('balance_due'), 2),
            'count_month' => (clone $duMois)->count(),
            'from' => $from->format('d/m/Y'),
            'to' => $to->format('d/m/Y'),
        ];
    }

    /** @return LengthAwarePaginator<int, FinanceInvoice> */
    public function getInvoicesProperty(): LengthAwarePaginator
    {
        [$from, $to] = $this->periodDates();

        return $this->baseQuery()
            ->with(['rendezVous.organizationSite'])
            ->whereBetween('issued_at', [$from, $to])
            ->when($this->filterStatus !== '', fn (Builder $q) => $q->where('status', $this->filterStatus))
            ->when(
                $this->filterSiteId,
                fn (Builder $q) => $q->whereHas(
                    'rendezVous',
                    fn (Builder $rdv) => $rdv->where('organization_site_id', $this->filterSiteId),
                ),
            )
            ->when(
                trim($this->searchRef) !== '',
                fn (Builder $q) => $q->where('invoice_number', 'like', '%'.trim($this->searchRef).'%'),
            )
            ->orderByDesc('issued_at')
            ->paginate(20);
    }

    /** @return Collection<int, OrganizationSite> */
    public function getSitesProperty(): Collection
    {
        return OrganizationSite::forOrg(Auth::user()->current_organization_id)
            ->active()->orderBy('name')->get(['id', 'name']);
    }

    /**
     * LE TÉLÉCHARGEMENT PASSE PAR LA ROUTE SIGNÉE EXISTANTE, jamais par une génération maison.
     *
     * La vue appelait `downloadInvoice()` depuis toujours ; la méthode n'existait pas, et le bouton
     * PDF levait une erreur de composant. Le contrôleur de téléchargement, lui, vérifie déjà la
     * propriété du document ET la restriction par site — dupliquer ce contrôle ici aurait été une
     * seconde chance de se tromper.
     */
    public function downloadInvoice(int $invoiceId): void
    {
        $autorisee = (clone $this->baseQuery())->whereKey($invoiceId)->exists();

        if (! $autorisee) {
            $this->dispatch('toast', 'Facture introuvable.', 'error');

            return;
        }

        $this->redirect(
            URL::temporarySignedRoute('client.finance.invoice.download', now()->addMinutes(5), ['invoice' => $invoiceId]),
        );
    }

    public function render()
    {
        return view('livewire.client-company.billing-center', [
            'summary' => $this->summary,
            'sites' => $this->sites,
            'invoices' => $this->invoices,
        ])->layout('layouts.client-company');
    }
}
