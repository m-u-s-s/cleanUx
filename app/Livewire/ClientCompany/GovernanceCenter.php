<?php

namespace App\Livewire\ClientCompany;

use App\Models\Booking;
use App\Models\OrganizationSite;
use App\Models\OrganizationSiteBudget;
use App\Services\Enterprise\ClientAccountingExportService;
use App\Services\Enterprise\InternalApprovalService;
use App\Services\Enterprise\ServiceLevelService;
use App\Services\Enterprise\SiteBudgetService;
use App\Services\PermissionService;
use App\Support\Livewire\Concerns\EnforcesActiveOrgMembership;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** LE PILOTAGE D'UNE ENTREPRISE CLIENTE — BUDGETS (E7), APPROBATIONS (E8), NIVEAU DE SERVICE (E9) ET EXPORTS COMPTABLES (E11). */
class GovernanceCenter extends Component
{
    use EnforcesActiveOrgMembership;

    public string $du = '';

    public string $au = '';

    /** Le formulaire de budget. */
    public ?int $budgetSiteId = null;

    public string $budgetPlafond = '';

    public int $budgetSeuil = 80;

    public string $motifRefus = '';

    #[Locked]
    public ?string $refus = null;

    public function mount(): void
    {
        $acteur = Auth::user();

        // Deux portes : la finance regarde les budgets, l'exploitation les approbations. Exiger les
        // deux fermerait l'écran à chacun.
        abort_unless(
            app(PermissionService::class)->can($acteur, 'finance.view', $acteur->currentOrganization)
                || app(PermissionService::class)->can($acteur, 'bookings.approve', $acteur->currentOrganization),
            403
        );

        $this->du = Carbon::now()->startOfMonth()->toDateString();
        $this->au = Carbon::now()->endOfMonth()->toDateString();
    }

    // ── E7 : les budgets ─────────────────────────────────────────────────────

    public function definirLeBudget(): void
    {
        $this->autoriser('finance.manage');

        $this->validate([
            'budgetPlafond' => ['required', 'numeric', 'min:1'],
            'budgetSeuil' => ['required', 'integer', 'min:10', 'max:100'],
        ]);

        $orgId = (int) Auth::user()->current_organization_id;

        // Le local vient du navigateur : on ne le retient que s'il appartient à cette société.
        $siteLegitime = $this->budgetSiteId !== null
            && OrganizationSite::query()
                ->where('organization_account_id', $orgId)
                ->whereKey($this->budgetSiteId)
                ->exists();

        OrganizationSiteBudget::query()->updateOrCreate(
            [
                'organization_account_id' => $orgId,
                // `null` = toute la société : c'est le premier budget que la plupart posent.
                'organization_site_id' => $siteLegitime ? $this->budgetSiteId : null,
                'period' => OrganizationSiteBudget::PERIOD_MONTHLY,
                'period_start' => Carbon::parse($this->du)->startOfMonth()->toDateString(),
            ],
            [
                'limit_cents' => (int) round(((float) str_replace(',', '.', $this->budgetPlafond)) * 100),
                'alert_threshold_percent' => $this->budgetSeuil,
                'created_by_user_id' => Auth::id(),
                // Le palier annoncé est remis à zéro : un plafond relevé doit pouvoir réalerter.
                'alerted_at' => null,
                'alerted_at_percent' => null,
            ],
        );

        $this->reset(['budgetPlafond', 'refus']);
    }

    // ── E8 : les approbations ────────────────────────────────────────────────

    public function approuver(int $bookingId): void
    {
        $this->autoriser('bookings.approve');

        $booking = $this->reservationDeLaSociete($bookingId);

        if ($booking === null) {
            return;
        }

        try {
            app(InternalApprovalService::class)->approuver($booking, Auth::user());
            $this->refus = null;
        } catch (DomainException $e) {
            $this->refus = $e->getMessage();
        }
    }

    public function refuserLaDemande(int $bookingId): void
    {
        $this->autoriser('bookings.approve');

        $booking = $this->reservationDeLaSociete($bookingId);

        if ($booking === null) {
            return;
        }

        try {
            app(InternalApprovalService::class)->refuser(
                $booking,
                Auth::user(),
                $this->motifRefus !== '' ? $this->motifRefus : 'Sans motif précisé.',
            );

            $this->reset(['motifRefus', 'refus']);
        } catch (DomainException $e) {
            $this->refus = $e->getMessage();
        }
    }

    // ── E11 : les exports ────────────────────────────────────────────────────

    public function exporter(string $format): StreamedResponse
    {
        $this->autoriser('finance.download');

        $service = app(ClientAccountingExportService::class);
        $debut = Carbon::parse($this->du);
        $fin = Carbon::parse($this->au);

        // Le FEC est tabulé, le CSV point-virgulé : produire un « FEC » en point-virgule donnerait
        // un fichier refusé au dépôt, après que le client aura cru l'avoir.
        $export = $format === 'fec'
            ? $service->fec(Auth::user(), $debut, $fin)
            : $service->csv(Auth::user(), $debut, $fin);

        return response()->streamDownload(
            fn () => print ($export['content']),
            $export['filename'],
            ['Content-Type' => 'text/plain; charset=UTF-8'],
        );
    }

    private function reservationDeLaSociete(int $bookingId): ?Booking
    {
        // Le scoping fait partie de la requête : une réservation d'une autre société n'est jamais
        // chargée.
        return Booking::query()
            ->where('customer_organization_id', Auth::user()->current_organization_id)
            ->find($bookingId);
    }

    private function autoriser(string $permission): void
    {
        $acteur = Auth::user();

        abort_unless(
            app(PermissionService::class)->can($acteur, $permission, $acteur->currentOrganization),
            403
        );
    }

    public function render(): View
    {
        $acteur = Auth::user();
        $orgId = (int) $acteur->current_organization_id;
        $permissions = app(PermissionService::class);

        $peutVoirLaFinance = $permissions->can($acteur, 'finance.view', $acteur->currentOrganization);
        $peutApprouver = $permissions->can($acteur, 'bookings.approve', $acteur->currentOrganization);

        return view('livewire.client-company.governance-center', [
            'budgets' => $peutVoirLaFinance
                ? app(SiteBudgetService::class)->etatsEnCours($orgId)
                : collect(),
            'approbations' => $peutApprouver
                ? app(InternalApprovalService::class)->enAttente($orgId)
                : collect(),
            'sla' => app(ServiceLevelService::class)->resume(
                $orgId,
                Carbon::parse($this->du)->startOfDay(),
                Carbon::parse($this->au)->endOfDay(),
            ),
            'slaParLocal' => app(ServiceLevelService::class)->parLocal(
                $orgId,
                Carbon::parse($this->du)->startOfDay(),
                Carbon::parse($this->au)->endOfDay(),
            ),
            'locaux' => OrganizationSite::query()
                ->where('organization_account_id', $orgId)
                ->orderBy('name')
                ->get(['id', 'name']),
            'peutVoirLaFinance' => $peutVoirLaFinance,
            'peutApprouver' => $peutApprouver,
            'peutGererLeBudget' => $permissions->can($acteur, 'finance.manage', $acteur->currentOrganization),
            'peutTelecharger' => $permissions->can($acteur, 'finance.download', $acteur->currentOrganization),
        ])->layout('layouts.client-company');
    }
}
