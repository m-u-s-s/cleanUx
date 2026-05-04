<?php

namespace App\Livewire\ClientCompany;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\OrganizationMember;
use App\Models\OrganizationSite;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ClientCompanyDashboard extends Component
{
    public string $period = 'month';

    public function mount(): void
    {
        abort_unless(Auth::user()->isClientCompany(), 403);
    }

    public function getKpisProperty(): array
    {
        $user  = Auth::user();
        $orgId = $user->current_organization_id;

        [$from, $to] = $this->periodDates();

        $bookingBase = fn () => Booking::where('client_organization_id', $orgId);

        return [
            'sites_count'       => OrganizationSite::forOrg($orgId)->active()->count(),
            'bookings_active'   => $bookingBase()->whereIn('status', ['pending', 'confirmed', 'in_progress'])->count(),
            'bookings_period'   => $bookingBase()->whereBetween('created_at', [$from, $to])->count(),
            'pending_approval'  => $bookingBase()->where('status', 'pending_approval')->count(),
            'members_count'     => OrganizationMember::where('organization_account_id', $orgId)->where('status', 'active')->count(),
            'spend_period'      => 0, // À connecter à Invoice
        ];
    }

    public function getRecentBookingsProperty()
    {
        $orgId = Auth::user()->current_organization_id;

        return Booking::where('client_organization_id', $orgId)
            ->with(['organizationSite:id,name,city', 'providerUser:id,name,profile_photo_path'])
            ->latest()
            ->limit(8)
            ->get();
    }

    public function getSitesOverviewProperty()
    {
        $orgId = Auth::user()->current_organization_id;

        return OrganizationSite::forOrg($orgId)
            ->active()
            ->withCount(['bookings as active_bookings_count' => fn ($q) =>
                $q->whereIn('status', ['confirmed', 'in_progress'])
            ])
            ->orderBy('name')
            ->limit(6)
            ->get();
    }

    public function getPendingApprovalsProperty()
    {
        $orgId = Auth::user()->current_organization_id;

        return Booking::where('client_organization_id', $orgId)
            ->where('status', 'pending_approval')
            ->with('organizationSite:id,name', 'clientUser:id,name')
            ->latest()
            ->get();
    }

    private function periodDates(): array
    {
        return match ($this->period) {
            'week'  => [now()->startOfWeek(), now()->endOfWeek()],
            'year'  => [now()->startOfYear(), now()->endOfYear()],
            default => [now()->startOfMonth(), now()->endOfMonth()],
        };
    }

    public function render()
    {
        return view('livewire.client-company.client-company-dashboard', [
            'kpis'             => $this->kpisProperty,
            'recentBookings'   => $this->recentBookingsProperty,
            'sitesOverview'    => $this->sitesOverviewProperty,
            'pendingApprovals' => $this->pendingApprovalsProperty,
        ])->layout('layouts.client-company');
    }
}
