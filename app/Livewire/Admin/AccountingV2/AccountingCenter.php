<?php

namespace App\Livewire\Admin\AccountingV2;

use App\Models\AccountingEntry;
use App\Models\AccountingExport;
use App\Models\AccountingPeriod;
use App\Services\AccountingV2\ExportManager;
use App\Services\AccountingV2\PeriodCloser;
use App\Services\AccountingV2\ReglagesComptables;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class AccountingCenter extends Component
{
    use EnforcesAdminAccess;
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public string $tab = 'ledger';   // ledger | periods | exports

    public string $filterJournal = '';

    public string $filterAccount = '';

    public int $filterYear = 0;

    public int $filterMonth = 0;

    public int $exportYear;

    public int $exportMonth;

    public string $exportFormat = 'csv';

    // ── Fiscalité : ce que le comptable décide lui-même ──────────────────

    public bool $postageAutomatique = false;

    /** Vide = taux du pays ; « 0 » = hors champ de la TVA. Les deux sont des réponses. */
    public string $tvaFraisAnnulation = '';

    public string $modeleRevenu = 'principal';

    public function mount(): void
    {
        $this->filterYear = (int) now()->year;
        $this->filterMonth = (int) now()->month;
        $this->exportYear = $this->filterYear;
        $this->exportMonth = $this->filterMonth;

        $this->relireLaFiscalite();
    }

    /** LA CAPACITÉ EST VÉRIFIÉE SUR LE COMPOSANT, PAS SEULEMENT DANS LE MENU. */
    public function boot(): void
    {
        abort_unless(Gate::allows('manage-accounting'), 403);
    }

    public function closePeriod(int $year, int $month): void
    {
        try {
            app(PeriodCloser::class)->close($year, $month, Auth::user());
            $this->dispatch('toast', "Période {$year}-{$month} clôturée.", 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', 'Erreur : '.$e->getMessage(), 'error');
        }
    }

    /** ROUVRIR UNE PÉRIODE — le service savait le faire, l'écran ne le proposait pas. */
    public function reopenPeriod(int $periodId, string $motif): void
    {
        $motif = trim($motif);

        if ($motif === '') {
            $this->dispatch('toast', 'Un motif est requis pour rouvrir une période.', 'error');

            return;
        }

        try {
            $periode = AccountingPeriod::query()->findOrFail($periodId);
            app(PeriodCloser::class)->reopen($periode, Auth::user(), $motif);
            $this->dispatch('toast', "Période {$periode->label()} rouverte.", 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', 'Erreur : '.$e->getMessage(), 'error');
        }
    }

    /** ENREGISTRER LA POSITION FISCALE. */
    public function enregistrerLaFiscalite(): void
    {
        $this->validate([
            'tvaFraisAnnulation' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'modeleRevenu' => ['required', 'in:principal,agent'],
        ], [], [
            'tvaFraisAnnulation' => 'taux de TVA des frais d’annulation',
            'modeleRevenu' => 'modèle de revenu',
        ]);

        $reglages = app(ReglagesComptables::class);

        $reglages->poser(ReglagesComptables::POSTAGE_AUTOMATIQUE, $this->postageAutomatique);
        $reglages->poser(ReglagesComptables::MODELE_REVENU, $this->modeleRevenu);
        $reglages->poser(
            ReglagesComptables::TVA_FRAIS_ANNULATION,
            trim($this->tvaFraisAnnulation) === '' ? '' : $this->tvaFraisAnnulation,
        );

        $this->relireLaFiscalite();

        $this->dispatch('toast', 'Réglages comptables enregistrés.', 'success');
    }

    private function relireLaFiscalite(): void
    {
        $reglages = app(ReglagesComptables::class);

        $this->postageAutomatique = $reglages->postageAutomatique();
        $this->modeleRevenu = $reglages->modeleDeRevenu();

        $taux = $reglages->tvaDesFraisDAnnulation();
        $this->tvaFraisAnnulation = $taux === null ? '' : rtrim(rtrim(number_format($taux, 2, '.', ''), '0'), '.');
    }

    public function generateExport(): void
    {
        try {
            app(ExportManager::class)->generate(
                $this->exportFormat,
                $this->exportYear,
                $this->exportMonth ?: null,
                Auth::id(),
            );
            $this->dispatch('toast', 'Export généré.', 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', 'Erreur : '.$e->getMessage(), 'error');
        }
    }

    public function render(): View
    {
        $kpis = [
            'entries_total' => AccountingEntry::query()->count(),
            'periods_closed' => AccountingPeriod::query()->where('is_closed', true)->count(),
            'exports_ready' => AccountingExport::query()->where('status', AccountingExport::STATUS_READY)->count(),
            'period_label' => sprintf('%04d-%02d', $this->filterYear, $this->filterMonth),
            'period_debit' => (int) AccountingEntry::query()
                ->forPeriod($this->filterYear, $this->filterMonth)
                ->sum('debit_cents'),
            'period_credit' => (int) AccountingEntry::query()
                ->forPeriod($this->filterYear, $this->filterMonth)
                ->sum('credit_cents'),
        ];

        if ($this->tab === 'ledger') {
            $items = AccountingEntry::query()
                ->forPeriod($this->filterYear, $this->filterMonth)
                ->when($this->filterJournal, fn ($q) => $q->where('journal_code', $this->filterJournal))
                ->when($this->filterAccount, fn ($q) => $q->where('account_code', $this->filterAccount))
                ->orderByDesc('posting_date')
                ->orderByDesc('id')
                ->paginate(25);
        } elseif ($this->tab === 'periods') {
            $items = AccountingPeriod::query()
                ->orderByDesc('period_year')
                ->orderByDesc('period_month')
                ->paginate(20);
        } elseif ($this->tab === 'exports') {
            $items = AccountingExport::query()
                ->orderByDesc('created_at')
                ->paginate(20);
        } else {
            // L'ONGLET FISCALITÉ N'A PAS DE LISTE, MAIS LA VUE PAGINE TOUJOURS.
            $items = AccountingEntry::query()->whereRaw('1 = 0')->paginate(1);
        }

        return view('livewire.admin.accounting-v2.accounting-center', [
            'kpis' => $kpis,
            'items' => $items,
        ]);
    }
}
