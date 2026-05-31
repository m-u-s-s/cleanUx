<?php

namespace App\Livewire\ClientCompany;

use App\Models\Booking;
use App\Models\OrganizationSite;
use App\Models\ProviderProfile;
use App\Models\Trade;
use App\Services\PermissionService;
use App\Support\Livewire\Concerns\RendersTradeFormSchema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Booking> $bookings
 * @property-read \Illuminate\Database\Eloquent\Collection<int, OrganizationSite> $sites
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Trade> $trades
 * @property-read ?OrganizationSite $selectedSite
 * @property-read Collection<int, mixed> $availableProviders
 */
class BookingHub extends Component
{
    use RendersTradeFormSchema;

    // ──────────────────────────────────────────────────────
    // State
    // ──────────────────────────────────────────────────────
    public string $view = 'list'; // list | create

    public string $filterStatus = '';

    public ?int $filterSiteId = null;

    public int $step = 1;

    // Formulaire de réservation
    public ?int $selectedSiteId = null;

    public ?int $selectedTradeId = null;

    public ?int $selectedProviderId = null;

    public string $scheduledDate = '';

    public string $scheduledTime = '';

    public string $notes = '';

    public string $purchaseOrderRef = '';

    public ?int $estimatedHours = null;

    public bool $needsApproval = false;

    // ──────────────────────────────────────────────────────
    // Mount
    // ──────────────────────────────────────────────────────
    public function mount(?int $site = null): void
    {
        $user = Auth::user();
        abort_unless(
            app(PermissionService::class)->can($user, 'bookings.create', $user->currentOrganization),
            403
        );

        if ($site) {
            $this->selectedSiteId = $site;
            $this->view = 'create';
            $this->step = 2;
        }

        // Vérifier si l'organisation requiert une approbation
        $this->needsApproval = (bool) $user->currentOrganization?->requires_internal_approval;
    }

    // ──────────────────────────────────────────────────────
    // Computed
    // ──────────────────────────────────────────────────────
    public function getBookingsProperty()
    {
        $orgId = Auth::user()->current_organization_id;

        return Booking::where('client_organization_id', $orgId)
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterSiteId, fn ($q) => $q->where('organization_site_id', $this->filterSiteId))
            ->with([
                'organizationSite:id,name,city',
                'providerUser:id,name,profile_photo_path',
                'clientUser:id,name',
            ])
            ->latest('scheduled_at')
            ->get();
    }

    public function getSitesProperty()
    {
        return OrganizationSite::forOrg(Auth::user()->current_organization_id)
            ->active()
            ->orderBy('name')
            ->get();
    }

    public function getTradesProperty()
    {
        return Trade::active()->ordered()->get(['id', 'name', 'icon', 'color', 'short_description']);
    }

    public function getSelectedSiteProperty(): ?OrganizationSite
    {
        return $this->selectedSiteId
            ? OrganizationSite::forOrg(Auth::user()->current_organization_id)->find($this->selectedSiteId)
            : null;
    }

    public function getAvailableProvidersProperty()
    {
        if (! $this->selectedSiteId) {
            return collect();
        }

        $site = $this->selectedSite;

        return ProviderProfile::where('status', 'active')
            ->where('verification_status', 'verified')
            ->with('user:id,name,profile_photo_path')
            ->when(
                $this->selectedTradeId,
                fn ($q) => $q->whereHas(
                    'user.trades',
                    fn ($tq) => $tq->where('trades.id', $this->selectedTradeId)
                )
            )
            ->when(
                $site?->preferred_provider_id,
                fn ($q) => $q->orderByRaw(
                    'CASE WHEN user_id = ? THEN 0 ELSE 1 END',
                    [$site->preferred_provider_id]
                )
            )
            ->limit(10)
            ->get();
    }

    // ──────────────────────────────────────────────────────
    // Navigation
    // ──────────────────────────────────────────────────────
    public function selectSite(int $siteId): void
    {
        $this->selectedSiteId = $siteId;
        $this->step = 2;
    }

    public function selectTrade(int $tradeId): void
    {
        $this->selectedTradeId = $tradeId;
        $this->loadTradeFormSchemaForTrade($tradeId);
        $this->step = 3;
    }

    public function nextStep(): void
    {
        if ($this->step === 2 && ! $this->selectedSiteId) {
            return;
        }

        if ($this->step === 3 && ! $this->selectedTradeId) {
            $this->addError('selectedTradeId', 'Veuillez choisir un métier.');

            return;
        }

        if ($this->step === 4 && blank($this->scheduledDate)) {
            $this->addError('scheduledDate', 'Veuillez choisir une date.');

            return;
        }

        $this->step++;
    }

    public function prevStep(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    // ──────────────────────────────────────────────────────
    // Soumission
    // ──────────────────────────────────────────────────────
    public function submitBooking(): void
    {
        $rules = [
            'selectedSiteId' => ['required', 'integer'],
            'selectedTradeId' => ['required', 'integer'],
            'scheduledDate' => ['required', 'date', 'after:today'],
            'scheduledTime' => ['required'],
        ];

        $this->validate(array_merge($rules, $this->tradeFormAnswersRules()));

        $user = Auth::user();
        $orgId = $user->current_organization_id;
        OrganizationSite::forOrg($orgId)->findOrFail($this->selectedSiteId);

        $canApprove = app(PermissionService::class)->can($user, 'bookings.approve', $user->currentOrganization);

        $status = $this->needsApproval && ! $canApprove
            ? 'pending_approval'
            : 'pending';

        $booking = Booking::create([
            'client_user_id' => $user->id,
            'client_organization_id' => $orgId,
            'organization_site_id' => $this->selectedSiteId,
            'provider_user_id' => $this->selectedProviderId,
            'scheduled_at' => "{$this->scheduledDate} {$this->scheduledTime}",
            'status' => $status,
            'estimated_duration' => $this->estimatedHours ? $this->estimatedHours * 60 : null,
            'purchase_order_reference' => $this->purchaseOrderRef,
            'notes' => $this->notes,
            'trade_form_answers' => $this->hasTradeFormSchema() ? $this->tradeFormAnswers : null,
        ]);

        $this->reset([
            'selectedSiteId', 'selectedTradeId', 'selectedProviderId',
            'scheduledDate', 'scheduledTime', 'notes', 'purchaseOrderRef', 'estimatedHours',
        ]);
        $this->tradeFormSchema = null;
        $this->tradeFormAnswers = [];
        $this->step = 1;
        $this->view = 'list';

        $this->dispatch('booking-created', bookingId: $booking->id);
    }

    public function approveBooking(int $bookingId): void
    {
        $user = Auth::user();
        abort_unless(
            app(PermissionService::class)->can($user, 'bookings.approve', $user->currentOrganization),
            403
        );

        Booking::where('client_organization_id', $user->current_organization_id)
            ->findOrFail($bookingId)
            ->update(['status' => 'pending']); // → En attente prestataire
    }

    public function cancelBooking(int $bookingId): void
    {
        $user = Auth::user();
        abort_unless(
            app(PermissionService::class)->can($user, 'bookings.cancel', $user->currentOrganization),
            403
        );

        Booking::where('client_organization_id', $user->current_organization_id)
            ->findOrFail($bookingId)
            ->update(['status' => 'cancelled']);
    }

    public function render()
    {
        return view('livewire.client-company.booking-hub', [
            'bookings' => $this->bookings,
            'sites' => $this->sites,
            'trades' => $this->trades,
            'providers' => $this->availableProviders,
            'selectedSite' => $this->selectedSite,
        ])->layout('layouts.client-company');
    }
}
