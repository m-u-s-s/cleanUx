<?php

namespace App\Livewire\ClientCompany;

use App\Models\Booking;
use App\Models\FinanceInvoice;
use App\Models\OrganizationMember;
use App\Models\OrganizationSite;
use App\Services\Enterprise\MemberSiteAccessService;
use App\Support\Finance\ClientFinanceDocumentScope;
use App\Support\Livewire\Concerns\EnforcesActiveOrgMembership;
use App\View\Components\Money;
use Carbon\CarbonInterface;
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
 * @property-read string $devise
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
            'spend_period' => $this->depenseDeLaPeriode($from, $to),
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

    /** Booking counts grouped by trade name for the selected period. */
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

    /**
     * CE QUE LA SOCIETE A REELLEMENT DEPENSE SUR LA PERIODE.
     *
     * Ce chiffre valait `0`, avec un commentaire promettant de le brancher. Le tableau de bord
     * annoncait donc « 0 » de depenses a une societe qui facture — un zero qu'on lit comme une
     * information, pas comme un trou.
     *
     * La portee est celle du centre de facturation, a l'identique : un membre restreint a
     * certains locaux ne doit pas totaliser les factures des autres. La refaire a la main ici
     * ouvrirait une deuxieme regle d'acces, qui divergerait au premier changement.
     *
     * `issued_at` DATE LA FACTURE, pas `created_at` : une facture de janvier saisie en fevrier
     * appartient a janvier — meme regle que `BillingCenter`.
     */
    private function depenseDeLaPeriode(CarbonInterface $du, CarbonInterface $au): float
    {
        return round((float) ClientFinanceDocumentScope::apply(FinanceInvoice::query(), Auth::user())
            ->whereBetween('issued_at', [$du, $au])
            ->sum('total_amount'), 2);
    }

    /** La devise dans laquelle cette societe lit ses montants. */
    public function getDeviseProperty(): string
    {
        return Money::deviseDuContexte();
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
            'devise' => $this->devise,
        ])->layout('layouts.client-company');
    }
}
