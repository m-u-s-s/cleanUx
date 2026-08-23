<?php

namespace App\Livewire\ProviderCompany;

use App\Models\TimeEntry;
use App\Services\PermissionService;
use App\Services\Workforce\ProfitabilityService;
use App\Services\Workforce\TimesheetService;
use App\Support\Livewire\Concerns\EnforcesActiveOrgMembership;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** LES HEURES (E20) ET CE QU'ELLES COÛTENT (E22). */
class TimesheetCenter extends Component
{
    use EnforcesActiveOrgMembership;

    public string $du = '';

    public string $au = '';

    /** `site`, `team` ou `agency` — la ventilation de la rentabilité. */
    public string $ventilation = 'site';

    #[Locked]
    public ?string $refus = null;

    /** OUVERT À TOUT MEMBRE, FILTRÉ PAR DROIT. */
    public function mount(): void
    {
        $this->du = Carbon::now()->startOfMonth()->toDateString();
        $this->au = Carbon::now()->endOfMonth()->toDateString();
    }

    /** Approuver — ou refuser — une correction saisie à la main. */
    public function statuer(int $entryId, bool $approuve): void
    {
        $acteur = Auth::user();

        abort_unless(
            app(PermissionService::class)->can($acteur, 'team.manage', $acteur->currentOrganization),
            403
        );

        // Scopé sur l'organisation active : un identifiant forgé ne doit pas atteindre la feuille
        // d'heures d'une autre société.
        $entry = TimeEntry::query()
            ->where('organization_account_id', $acteur->current_organization_id)
            ->find($entryId);

        if ($entry === null) {
            return;
        }

        try {
            app(TimesheetService::class)->statuer($entry, $acteur, $approuve);
            $this->refus = null;
        } catch (DomainException $e) {
            $this->refus = $e->getMessage();
        }
    }

    /** L'export paie, en CSV — destiné à être relu par un humain avant d'être versé. */
    public function exporter(): StreamedResponse
    {
        $acteur = Auth::user();

        abort_unless(
            app(PermissionService::class)->can($acteur, 'team.manage', $acteur->currentOrganization),
            403
        );

        $csv = app(TimesheetService::class)->exporterCsv(
            (int) $acteur->current_organization_id,
            Carbon::parse($this->du)->startOfDay(),
            Carbon::parse($this->au)->endOfDay(),
        );

        $nom = sprintf('heures-%s-%s.csv', $this->du, $this->au);

        return response()->streamDownload(fn () => print ($csv), $nom, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function render(): View
    {
        $acteur = Auth::user();
        $orgId = (int) $acteur->current_organization_id;
        $debut = Carbon::parse($this->du)->startOfDay();
        $fin = Carbon::parse($this->au)->endOfDay();

        $permissions = app(PermissionService::class);
        $peutVoirLEquipe = $permissions->can($acteur, 'team.view', $acteur->currentOrganization);
        $peutGerer = $permissions->can($acteur, 'team.manage', $acteur->currentOrganization);
        // LA MARGE N'EST PAS UNE DONNÉE D'ÉQUIPE.
        $peutVoirLaMarge = $permissions->can($acteur, 'analytics.view', $acteur->currentOrganization);

        $rentabilite = $peutVoirLaMarge
            ? app(ProfitabilityService::class)->pourLaPeriode($orgId, $debut, $fin, $this->ventilation)
            : collect();

        return view('livewire.provider-company.timesheet-center', [
            'feuille' => app(TimesheetService::class)
                ->feuilleDeLaPeriode($orgId, $debut, $fin)
                // Sans `team.view`, on ne lit que SES heures — ce qui reste la seule façon de
                // signaler une erreur avant la paie.
                ->when(! $peutVoirLEquipe, fn ($c) => $c->where('user_id', $acteur->id))
                ->values(),
            'enAttente' => $peutGerer
                ? TimeEntry::query()
                    ->where('organization_account_id', $orgId)
                    ->where('status', TimeEntry::STATUS_PENDING_APPROVAL)
                    ->with('user:id,name')
                    ->orderBy('started_at')
                    ->get()
                : collect(),
            'rentabilite' => $rentabilite,
            'peutVoirLaMarge' => $peutVoirLaMarge,
            // Le taux est une hypothèse : l'écran le dit plutôt que de présenter la marge comme un
            // fait établi.
            'tauxParDefautCents' => ProfitabilityService::DEFAULT_HOURLY_COST_CENTS,
            'sansPointage' => (int) $rentabilite->sum('missions_without_timesheet'),
            'peutGerer' => $peutGerer,
        ])->layout('layouts.provider-company');
    }
}
