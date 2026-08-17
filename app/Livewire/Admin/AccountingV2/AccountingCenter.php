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

    /**
     * LA CAPACITÉ EST VÉRIFIÉE SUR LE COMPOSANT, PAS SEULEMENT DANS LE MENU.
     *
     * `EnforcesAdminAccess` ne demande qu'« est-ce un administrateur ». Le grand livre, la clôture
     * des périodes et les exports légaux ne sont pas de l'exploitation courante : ils forment le
     * périmètre qu'on confie à un comptable extérieur, et lui seul.
     *
     * Cacher la tuile sans fermer l'écran aurait été l'inverse exact du défaut que ce dépôt
     * corrige ailleurs : là c'était « une porte visible sans la clé », ici ce serait « une porte
     * invisible mais ouverte ». `boot()` rejoue à CHAQUE requête Livewire, pas seulement au
     * montage — une URL devinée comme un appel d'action passent tous les deux par ici.
     *
     * LE NOM COMPTE. `boot{NomDuComposant}` n'est pas un point d'entrée : Livewire ne reconnaît
     * cette forme que pour les TRAITS, d'où `bootEnforcesAdminAccess()` juste à côté. Écrite ainsi,
     * la méthode n'était jamais appelée et l'écran restait ouvert à tout administrateur — un garde
     * parfaitement rédigé et parfaitement inerte, que seul le témoin en sens inverse a révélé.
     */
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

    /**
     * ROUVRIR UNE PÉRIODE — le service savait le faire, l'écran ne le proposait pas.
     *
     * `PeriodCloser::reopen()` existe depuis l'origine du module. Sans bouton, une clôture
     * prématurée était définitive pour qui n'a pas accès à l'API : le comptable devait demander à
     * un développeur, ce qui est précisément la dépendance qu'on cherche à supprimer.
     *
     * LE MOTIF EST OBLIGATOIRE, et c'est le service qui l'exige, pas cet écran. Rouvrir un exercice
     * clos se justifie devant un contrôle.
     */
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

    /**
     * ENREGISTRER LA POSITION FISCALE.
     *
     * VIDE ET ZÉRO SONT DEUX RÉPONSES DIFFÉRENTES pour la TVA des frais d'annulation, et c'est le
     * piège de cet écran. Vide dit « traite-les au taux du pays, comme un produit ordinaire » ;
     * zéro dit « ces frais sont hors champ ». Un `empty()` les confondrait et ferait déclarer une
     * TVA que le comptable a décidé de ne pas devoir.
     */
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
            /*
             * L'ONGLET FISCALITÉ N'A PAS DE LISTE, MAIS LA VUE PAGINE TOUJOURS.
             *
             * Rendre `null` ferait tomber `$items->links()` en bas de page. On rend donc une
             * page vide plutôt que rien : la vue reste une seule vue, et l'onglet n'a pas besoin
             * de connaître la mécanique de pagination pour exister.
             */
            $items = AccountingEntry::query()->whereRaw('1 = 0')->paginate(1);
        }

        return view('livewire.admin.accounting-v2.accounting-center', [
            'kpis' => $kpis,
            'items' => $items,
        ]);
    }
}
