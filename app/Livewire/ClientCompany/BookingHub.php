<?php

namespace App\Livewire\ClientCompany;

use App\Models\Booking;
use App\Models\OrganizationSite;
use App\Models\ProviderProfile;
use App\Models\ServiceCatalog;
use App\Models\Trade;
use App\Services\Booking\CreateBookingAction;
use App\Services\Booking\EmployeeAvailabilityService;
use App\Services\Booking\ProviderSelectionResolver;
use App\Services\Booking\ZoneCoverageService;
use App\Services\PermissionService;
use App\Support\Livewire\Concerns\EnforcesActiveOrgMembership;
use App\Support\Livewire\Concerns\RendersTradeFormSchema;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
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
    use EnforcesActiveOrgMembership;
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

    /** Palier de sélection SP2 : independent | company | any. */
    public string $providerTypePreference = 'any';

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

        return Booking::where('customer_organization_id', $orgId)
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterSiteId, fn ($q) => $q->where('organization_site_id', $this->filterSiteId))
            ->with([
                'organizationSite:id,name,city',
                'providerUser:id,name,profile_photo_path',
                'clientUser:id,name',
                'mission:id,booking_id',
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
        $site = OrganizationSite::forOrg($orgId)->findOrFail($this->selectedSiteId);

        // SP2 Task 8 — route via l'action canonique (CreateBookingAction) au lieu
        // d'un Booking::create() ad-hoc qui PERDAIT le service_catalog_id et ne
        // générait ni zone/prix/dispatch. On résout zone/catalog/rule depuis le
        // SITE (adresse/code postal) + le MÉTIER, comme le flux web public.

        // 1) Catalog du métier sélectionné.
        $catalog = ServiceCatalog::query()
            ->where('trade_id', $this->selectedTradeId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->first();

        if (! $catalog) {
            $this->addError('selectedTradeId', 'Aucun service n’est disponible pour ce métier.');

            return;
        }

        $serviceIdentifier = (string) ($catalog->code ?: $catalog->slug ?: $catalog->service_type);

        // 2) Résolution de couverture (postal/zone/catalog/rule) depuis le site,
        //    via le MÊME service que le flux web (ZoneCoverageService).
        $resolution = app(ZoneCoverageService::class)->resolveCoverage(
            $site->postal_code,
            $site->city,
            $serviceIdentifier,
            $site,
        );

        $postal = $resolution->postalCode;
        $zone = $resolution->zone;
        $catalog = $resolution->serviceCatalog ?? $catalog;
        $rule = $resolution->zoneServiceRule;

        // $catalog est garanti non-null ici (guard ligne 227 + fallback ligne 246).
        if (! $postal || ! $zone || ! $rule) {
            $this->addError('selectedSiteId', 'Ce site n’est pas couvert pour ce métier (zone/prix introuvable).');

            return;
        }

        // 3) Gating SP2 : valide le palier de sélection prestataire.
        try {
            $selection = app(ProviderSelectionResolver::class)->resolve($user, [
                'provider_type_preference' => $this->providerTypePreference,
                'preferred_provider_user_id' => $this->selectedProviderId,
            ]);
        } catch (AuthorizationException $e) {
            $this->addError('selectedProviderId', $e->getMessage());

            return;
        }

        // 4) Employé assigné requis par execute() — best available pour le créneau.
        $assignedEmployee = app(EmployeeAvailabilityService::class)->resolveBestAvailableEmployeeForSlot(
            $this->scheduledDate,
            $this->scheduledTime,
            $zone,
            $this->estimatedHours ? $this->estimatedHours * 60 : 90,
        );

        if (! $assignedEmployee) {
            $this->addError('scheduledTime', 'Aucun prestataire disponible pour ce créneau.');

            return;
        }

        $canApprove = app(PermissionService::class)->can($user, 'bookings.approve', $user->currentOrganization);
        $approvalRequired = $this->needsApproval && ! $canApprove;

        $data = array_merge([
            'booking_channel' => 'client_company',
            'organization_account_id' => $orgId,
            'organization_site_id' => $site->id,
            'service_zone_id' => $zone->id,
            'postal_code_id' => $postal->id,
            'service_identifier' => $serviceIdentifier,
            'date' => $this->scheduledDate,
            'heure' => $this->scheduledTime,
            'duree_estimee' => $this->estimatedHours ? $this->estimatedHours * 60 : null,
            'purchase_order_reference' => $this->purchaseOrderRef ?: null,
            'commentaire_client' => $this->notes ?: null,
            'trade_form_answers' => $this->hasTradeFormSchema() ? $this->tradeFormAnswers : null,
            'status' => $approvalRequired ? 'pending_approval' : 'pending',
            'entreprise_approval_required' => $approvalRequired,
            'site_instructions' => $site->access_instructions,
        ], $selection);

        try {
            $booking = app(CreateBookingAction::class)->execute(
                client: $user,
                postal: $postal,
                zone: $zone,
                catalog: $catalog,
                rule: $rule,
                assignedEmployee: $assignedEmployee,
                data: $data,
                organizationSite: $site,
                resolution: $resolution,
            );
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError('selectedSiteId', $message);
                }
            }

            return;
        }

        // CreateBookingAction renseigne organization_account_id mais pas le contexte
        // client société consommé par le hub (liste filtrée sur customer_*).
        //
        // L'action canonique (et EnterpriseBookingApprovalService) normalise le
        // statut dans le vocabulaire moteur ('en_attente'/'confirme'). Or la liste
        // du hub + approveBooking() raisonnent dans LEUR propre vocabulaire
        // ('pending_approval'/'pending'/'confirmed') : sans réalignement, une
        // booking 'en_attente' n'affiche jamais le bouton « Approuver ». On reposе
        // donc le statut attendu par le hub — sauf si le moteur a déjà confirmé
        // instantanément (ex. ASAP), auquel cas on respecte la confirmation.
        $hubStatus = match (true) {
            $booking->status === 'confirme' || $booking->status === 'confirmed' => 'confirmed',
            $approvalRequired => 'pending_approval',
            default => 'pending',
        };

        $booking->forceFill([
            'customer_user_id' => $user->id,
            'customer_organization_id' => $orgId,
            'status' => $hubStatus,
        ])->save();

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

        Booking::where('customer_organization_id', $user->current_organization_id)
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

        Booking::where('customer_organization_id', $user->current_organization_id)
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
