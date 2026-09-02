<?php

namespace App\Livewire\Admin;

use App\Models\Booking;
use App\Models\FinanceInvoice;
use App\Models\OrganizationAccount;
use App\Services\Finance\B2BMonthlyInvoiceService;
use App\Services\Finance\FinanceDocumentService;
use App\Services\Localization\Money as MoneyService;
use App\Support\Domain\BookingStatus;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use App\View\Components\Money as MoneyComponent;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * LA CLOTURE MENSUELLE B2B, AVEC CE QU'IL FAUT POUR LA CONDUIRE.
 *
 * L'ecran proposait un bouton et une liste. On choisissait une periode, on cliquait, et on
 * obtenait « Aucun rendez-vous facturable » sans savoir POURQUOI — combien de rendez-vous la
 * periode contient, lesquels sont bloques, lesquels sont deja factures.
 *
 * Le service ecrivait pourtant un instantane complet : lignes datees, site, centre de cout,
 * echeance, TVA, solde. Le tableau n'en montrait aucun, alors que le sous-titre promettait un
 * groupement « par entreprise, site et centre de cout ».
 *
 * @property-read array<string, mixed> $apercu
 * @property-read array<string, mixed> $reperes
 * @property-read Collection<int, OrganizationAccount> $societes
 * @property-read ?FinanceInvoice $factureDetaillee
 */
class B2BMonthlyInvoicesCenter extends Component
{
    use EnforcesAdminAccess;
    use WithPagination;

    // ── Generateur ─────────────────────────────────────────────────────────
    public ?int $organization_account_id = null;

    public string $period_start = '';

    public string $period_end = '';

    // ── Filtres de la liste ────────────────────────────────────────────────
    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    #[Url(as: 'societe', except: '')]
    public string $filtreOrganisation = '';

    #[Url(as: 'statut', except: '')]
    public string $filtreStatut = '';

    // ── Panneaux ───────────────────────────────────────────────────────────
    #[Locked]
    public ?int $factureOuverte = null;

    #[Locked]
    public ?int $facturePourPaiement = null;

    public string $montantDuPaiement = '';

    public string $methodeDePaiement = 'transfer';

    public string $referenceDePaiement = '';

    protected $paginationTheme = 'tailwind';

    /**
     * LA CAPACITE EN PLUS DU ROLE. `module_gate` pose `manage-entreprises` sur la route, mais
     * `/livewire/update` ne rejoue aucun middleware : sans cette garde, tout administrateur
     * pouvait generer une facture ou enregistrer un paiement par un appel de composant.
     */
    public function boot(): void
    {
        Gate::authorize('manage-entreprises');
    }

    public function mount(): void
    {
        $this->period_start = now()->subMonth()->startOfMonth()->toDateString();
        $this->period_end = now()->subMonth()->endOfMonth()->toDateString();
    }

    public function updated(string $champ, mixed $valeur = null): void
    {
        if (in_array($champ, ['recherche', 'filtreOrganisation', 'filtreStatut'], true)) {
            $this->resetPage();
        }
    }

    public function reinitialiserLesFiltres(): void
    {
        $this->reset(['recherche', 'filtreOrganisation', 'filtreStatut']);
        $this->resetPage();
    }

    // ── Ce que la periode contient AVANT de facturer ────────────────────────

    /**
     * L'APERCU REPOND A « POURQUOI RIEN ? » AVANT LE CLIC.
     *
     * Il rejoue exactement les criteres du service — meme statuts, meme absence de facture — et
     * ajoute ce que le service ne dit pas : ce que la periode contient par ailleurs.
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function apercu(): array
    {
        if ($this->organization_account_id === null || $this->period_start === '' || $this->period_end === '') {
            return ['pret' => false];
        }

        $periode = [$this->period_start, $this->period_end];

        $dansLaPeriode = Booking::query()
            ->where('organization_account_id', $this->organization_account_id)
            ->whereBetween('date', $periode)
            ->get(['id', 'status', 'devis_estime']);

        $eligibles = Booking::query()
            ->with('organizationSite:id,name')
            ->where('organization_account_id', $this->organization_account_id)
            ->whereIn('status', [BookingStatus::TERMINE, BookingStatus::CONFIRME])
            ->whereBetween('date', $periode)
            ->whereDoesntHave('financeInvoice')
            ->get();

        $dejaFacturees = Booking::query()
            ->where('organization_account_id', $this->organization_account_id)
            ->whereIn('status', [BookingStatus::TERMINE, BookingStatus::CONFIRME])
            ->whereBetween('date', $periode)
            ->whereHas('financeInvoice')
            ->count();

        return [
            'pret' => true,
            'dans_la_periode' => $dansLaPeriode->count(),
            'eligibles' => $eligibles->count(),
            'deja_facturees' => $dejaFacturees,
            'montant' => round((float) $eligibles->sum('devis_estime'), 2),
            'statuts_ecartes' => $dansLaPeriode
                ->reject(fn (Booking $rdv) => in_array($rdv->status, [BookingStatus::TERMINE, BookingStatus::CONFIRME], true))
                ->groupBy('status')
                ->map(fn (Collection $lot) => $lot->count())
                ->all(),
            'par_site' => $eligibles
                ->groupBy(fn (Booking $rdv) => $rdv->organizationSite->name ?? 'Sans site')
                ->map(fn (Collection $lot, string $site) => [
                    'site' => $site,
                    'count' => $lot->count(),
                    'subtotal' => round((float) $lot->sum('devis_estime'), 2),
                ])
                ->values()
                ->all(),
        ];
    }

    // ── Generation ─────────────────────────────────────────────────────────

    public function generate(B2BMonthlyInvoiceService $service): void
    {
        $this->validate([
            'organization_account_id' => ['required', 'exists:organization_accounts,id'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
        ]);

        $organization = OrganizationAccount::findOrFail($this->organization_account_id);

        $invoice = $service->generateForOrganization($organization, $this->period_start, $this->period_end);

        unset($this->apercu, $this->reperes);

        if (! $invoice) {
            $this->dispatch('toast', 'Aucun rendez-vous facturable pour cette période.', 'warning');

            return;
        }

        $this->dispatch('toast', 'Facture B2B générée : '.$invoice->invoice_number, 'success');
    }

    /**
     * LA CLOTURE DU MOIS EN UN GESTE. Une societe sans rendez-vous facturable est simplement
     * sautee : le service rend nul, et le compte final dit combien de factures sont nees.
     */
    public function genererPourToutesLesSocietes(B2BMonthlyInvoiceService $service): void
    {
        $this->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
        ]);

        $nees = 0;

        foreach ($this->societesFacturables() as $organisation) {
            if ($service->generateForOrganization($organisation, $this->period_start, $this->period_end)) {
                $nees++;
            }
        }

        unset($this->apercu, $this->reperes);

        $this->dispatch('toast',
            $nees === 0
                ? 'Aucune société n’avait de rendez-vous facturable sur cette période.'
                : $nees.' facture(s) générée(s).',
            $nees === 0 ? 'warning' : 'success');
    }

    // ── Detail, paiement, relance ──────────────────────────────────────────

    public function ouvrirLaFacture(int $factureId): void
    {
        $this->factureOuverte = $factureId;
    }

    public function fermerLaFacture(): void
    {
        $this->factureOuverte = null;
    }

    #[Computed]
    public function factureDetaillee(): ?FinanceInvoice
    {
        if ($this->factureOuverte === null) {
            return null;
        }

        return FinanceInvoice::query()
            ->with(['organizationAccount:id,name', 'payments', 'reminders'])
            ->find($this->factureOuverte);
    }

    public function ouvrirLePaiement(int $factureId): void
    {
        $facture = FinanceInvoice::findOrFail($factureId);

        $this->facturePourPaiement = $facture->id;
        $this->montantDuPaiement = (string) $facture->balance_due;
        $this->referenceDePaiement = '';
    }

    public function fermerLePaiement(): void
    {
        $this->facturePourPaiement = null;
        $this->montantDuPaiement = '';
        $this->referenceDePaiement = '';
        $this->resetValidation();
    }

    public function enregistrerLePaiement(FinanceDocumentService $service): void
    {
        $this->validate([
            'montantDuPaiement' => ['required', 'numeric', 'min:0.01'],
            'methodeDePaiement' => ['required', 'string', 'max:40'],
            'referenceDePaiement' => ['nullable', 'string', 'max:120'],
        ]);

        $facture = FinanceInvoice::findOrFail($this->facturePourPaiement);

        // LE SERVICE EXISTAIT DEJA : il cree le paiement PUIS recalcule le solde et le statut.
        $service->recordPayment($facture, (float) $this->montantDuPaiement, [
            'method' => $this->methodeDePaiement,
            'external_reference' => $this->referenceDePaiement ?: null,
        ]);

        unset($this->reperes, $this->factureDetaillee);

        $this->fermerLePaiement();
        $this->dispatch('toast', 'Paiement enregistré', 'success');
    }

    public function envoyerUneRelance(int $factureId, FinanceDocumentService $service): void
    {
        $relance = $service->sendReminder(FinanceInvoice::findOrFail($factureId));

        unset($this->factureDetaillee);

        $this->dispatch('toast',
            $relance->status === 'sent' ? 'Relance envoyée' : 'Relance enregistrée, envoi impossible',
            $relance->status === 'sent' ? 'success' : 'warning');
    }

    // ── Reperes et listes ──────────────────────────────────────────────────

    /**
     * Les six reperes du portefeuille filtre, calcules par le service de finance.
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function reperes(): array
    {
        $resume = app(FinanceDocumentService::class)
            ->invoiceHealthSummary($this->requeteFiltree()->with('payments')->get());

        // LE MONTANT SE FORMATE ICI, PAS DANS LA VUE. Sans devise explicite un montant s'affiche
        // en euros a un client marocain : c'est la devise du CONTEXTE qui fait foi.
        $devise = MoneyComponent::deviseDuContexte();
        $argent = app(MoneyService::class);

        return $resume + [
            'outstanding_formatted' => $argent->format((float) $resume['outstanding_balance'], $devise),
            'paid_formatted' => $argent->format((float) $resume['paid_total'], $devise),
            'overdue_formatted' => $argent->format((float) $resume['overdue_balance'], $devise),
        ];
    }

    /** @return Collection<int, OrganizationAccount> */
    #[Computed]
    public function societes(): Collection
    {
        return $this->societesFacturables();
    }

    /** @return Collection<int, OrganizationAccount> */
    private function societesFacturables(): Collection
    {
        return OrganizationAccount::query()
            ->whereIn('status', ['active', 'pilot', 'signed'])
            ->orderBy('name')
            ->get();
    }

    /** @return Builder<FinanceInvoice> */
    private function requeteFiltree(): Builder
    {
        return FinanceInvoice::query()
            ->where('invoice_type', 'b2b_monthly')
            ->when($this->recherche !== '', function (Builder $q) {
                $terme = '%'.$this->recherche.'%';
                $q->where(function (Builder $sous) use ($terme) {
                    $sous->where('invoice_number', 'like', $terme)
                        ->orWhereHas('organizationAccount', fn (Builder $o) => $o->where('name', 'like', $terme));
                });
            })
            ->when($this->filtreOrganisation !== '', fn (Builder $q) => $q->where('organization_account_id', $this->filtreOrganisation))
            ->when($this->filtreStatut === 'retard', fn (Builder $q) => $q->where('balance_due', '>', 0)->whereNotNull('due_at')->where('due_at', '<', now()))
            ->when($this->filtreStatut !== '' && $this->filtreStatut !== 'retard', fn (Builder $q) => $q->where('status', $this->filtreStatut));
    }

    public function render(): View
    {
        return view('livewire.admin.b2b-monthly-invoices-center', [
            'organizations' => $this->societes,

            'invoices' => $this->requeteFiltree()
                ->with('organizationAccount:id,name')
                ->latest('issued_at')
                ->paginate(10),
        ]);
    }
}
