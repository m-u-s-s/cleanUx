<?php

namespace App\Livewire\ClientCompany;

use App\Models\Booking;
use App\Models\OrganizationMember;
use App\Models\OrganizationSite;
use App\Services\Enterprise\MemberSiteAccessService;
use App\Support\Livewire\Concerns\EnforcesActiveOrgMembership;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * @property-read array<string, mixed> $kpis
 * @property-read Collection<int, Booking> $recentBookings
 * @property-read Collection<int, OrganizationSite> $sitesOverview
 * @property-read Collection<int, Booking> $pendingApprovals
 * @property-read array<int, array<string, mixed>> $bookingsByTrade
 */
class ClientCompanyDashboard extends Component
{
    use EnforcesActiveOrgMembership;

    public string $period = 'month';

    public function mount(): void
    {
        abort_unless(Auth::user()->isClientCompany(), 403);
    }

    public function getKpisProperty(): array
    {
        $user = Auth::user();
        $orgId = $user->current_organization_id;

        [$from, $to] = $this->periodDates();

        $bookingBase = fn () => $this->limiterAuxLocaux(Booking::where('customer_organization_id', $orgId));

        return [
            'sites_count' => $this->limiterLesLocaux(OrganizationSite::forOrg($orgId)->active())->count(),
            'bookings_active' => $bookingBase()->whereIn('status', ['pending', 'confirmed', 'in_progress'])->count(),
            'bookings_period' => $bookingBase()->whereBetween('created_at', [$from, $to])->count(),
            'pending_approval' => $bookingBase()->where('status', 'pending_approval')->count(),
            'members_count' => OrganizationMember::where('organization_account_id', $orgId)->where('status', 'active')->count(),
            'spend_period' => 0, // À connecter à Invoice
        ];
    }

    public function getRecentBookingsProperty()
    {
        $orgId = Auth::user()->current_organization_id;

        return $this->limiterAuxLocaux(Booking::where('customer_organization_id', $orgId))
            ->with(['organizationSite:id,name,city', 'providerUser:id,name,profile_photo_path'])
            ->latest()
            ->limit(8)
            ->get();
    }

    public function getSitesOverviewProperty()
    {
        $orgId = Auth::user()->current_organization_id;

        return $this->limiterLesLocaux(OrganizationSite::forOrg($orgId)->active())
            ->withCount(['bookings as active_bookings_count' => fn ($q) => $q->whereIn('status', ['confirmed', 'in_progress']),
            ])
            ->orderBy('name')
            ->limit(6)
            ->get();
    }

    public function getPendingApprovalsProperty()
    {
        $orgId = Auth::user()->current_organization_id;

        return $this->limiterAuxLocaux(Booking::where('customer_organization_id', $orgId))
            ->where('status', 'pending_approval')
            ->with('organizationSite:id,name', 'clientUser:id,name')
            ->latest()
            ->get();
    }

    /**
     * Booking counts grouped by trade name for the selected period.
     * Returns an array of ['trade' => string, 'count' => int] sorted by count desc.
     */
    public function getBookingsByTradeProperty(): array
    {
        $orgId = Auth::user()->current_organization_id;
        [$from, $to] = $this->periodDates();

        $rows = $this->limiterAuxLocaux(Booking::where('customer_organization_id', $orgId))
            ->whereBetween('bookings.created_at', [$from, $to])
            ->whereNotNull('service_catalog_id')
            ->join('service_catalogs', 'bookings.service_catalog_id', '=', 'service_catalogs.id')
            ->join('trades', 'service_catalogs.trade_id', '=', 'trades.id')
            ->select(DB::raw('trades.name as trade_name'), DB::raw('COUNT(*) as total'))
            ->groupBy('trades.id', 'trades.name')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        return $rows->map(fn ($r) => [
            'trade' => $r->trade_name,
            'count' => (int) $r->total,
        ])->all();
    }

    /**
     * LE TABLEAU DE BORD IGNORAIT LE PÉRIMÈTRE DE QUI LE REGARDE.
     *
     * Toutes ses requêtes filtraient sur la SOCIÉTÉ et rien d'autre. Un responsable restreint à une
     * agence y lisait donc le nom et la ville des autres locaux, leurs réservations récentes avec
     * l'adresse du client, et les demandes en attente d'approbation — pendant que l'écran des
     * locaux, lui, le restreignait correctement. Deux écrans voisins, deux réponses.
     *
     * Aucune erreur n'était levée : la page s'affichait, elle affichait simplement trop.
     *
     * `null` veut dire « aucune restriction déclarée », pas « aucun accès » — la distinction est la
     * même que dans `MemberSiteAccessService`, et l'inverse viderait le tableau de bord de toutes
     * les sociétés qui n'ont jamais restreint personne.
     *
     * @param  Builder<Booking>  $requete
     * @return Builder<Booking>
     */
    private function limiterAuxLocaux($requete)
    {
        $autorises = $this->locauxAutorises();

        return $autorises === null
            ? $requete
            : $requete->whereIn('organization_site_id', $autorises);
    }

    /**
     * @param  Builder<OrganizationSite>  $requete
     * @return Builder<OrganizationSite>
     */
    private function limiterLesLocaux($requete)
    {
        $autorises = $this->locauxAutorises();

        return $autorises === null
            ? $requete
            : $requete->whereIn('id', $autorises);
    }

    /** @return array<int, int>|null */
    private function locauxAutorises(): ?array
    {
        return app(MemberSiteAccessService::class)->sitesAutorises(Auth::user());
    }

    private function periodDates(): array
    {
        return match ($this->period) {
            'week' => [now()->startOfWeek(), now()->endOfWeek()],
            'year' => [now()->startOfYear(), now()->endOfYear()],
            default => [now()->startOfMonth(), now()->endOfMonth()],
        };
    }

    public function render()
    {
        return view('livewire.client-company.client-company-dashboard', [
            'kpis' => $this->kpis,
            'recentBookings' => $this->recentBookings,
            'sitesOverview' => $this->sitesOverview,
            'pendingApprovals' => $this->pendingApprovals,
            'bookingsByTrade' => $this->bookingsByTrade,
        ])->layout('layouts.client-company');
    }
}
