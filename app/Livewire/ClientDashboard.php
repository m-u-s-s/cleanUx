<?php

namespace App\Livewire;

use App\Models\FinanceInvoice;
use App\Models\FinanceQuote;
use App\Models\OrganizationSite;
use App\Models\Booking;
use App\Models\ServiceCatalog;
use App\Models\ServiceZone;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\Domain\BookingStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class ClientDashboard extends Component
{
    use WithPagination;

    public string $tri = 'asc';
    public $editRdvId = null;
    public $editDate = null;
    public $editHeure = null;

    protected $paginationTheme = 'tailwind';

    protected function currentUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    protected function coverageZoneIds(): Collection
    {
        $user = $this->currentUser();

        if (! $user) {
            return collect();
        }

        $zoneIds = collect([$user->primary_service_zone_id])->filter();

        if ($user->organization_account_id) {
            $zoneIds = $zoneIds->merge(
                OrganizationSite::query()
                    ->where('organization_account_id', $user->organization_account_id)
                    ->where('is_active', true)
                    ->whereNotNull('service_zone_id')
                    ->pluck('service_zone_id')
            );
        }

        if ($zoneIds->isEmpty() && $user->postal_code_id) {
            $zoneIds = $zoneIds->merge(
                ServiceZone::query()
                    ->whereHas('postalCodes', function ($query) use ($user) {
                        $query->where('postal_codes.id', $user->postal_code_id);
                    })
                    ->pluck('id')
            );
        }

        return $zoneIds->filter()->unique()->values();
    }

    public function isPremiumClient(): bool
    {
        $user = $this->currentUser();

        return $user?->isPremium() ?? false;
    }

    public function getActiveSubscriptionProperty()
    {
        $user = $this->currentUser();

        return $user?->subscription('default');
    }

    public function getCoverageZonesProperty()
    {
        $zoneIds = $this->coverageZoneIds();

        if ($zoneIds->isEmpty()) {
            return collect();
        }

        return ServiceZone::query()
            ->with(['postalCodes' => fn ($query) => $query->orderBy('code')])
            ->whereIn('id', $zoneIds)
            ->orderBy('priority')
            ->orderBy('name')
            ->get();
    }

    public function getAccountContextProperty(): array
    {
        $user = $this->currentUser();
        $organization = $user?->organizationAccount;
        $zones = $this->coverageZones;

        $typeLabel = match (true) {
            $user?->isEntreprise() ?? false => 'Entreprise',
            $user?->isPremium() ?? false => 'Premium',
            default => 'Standard',
        };

        return [
            'type_label' => $typeLabel,
            'zone_count' => $zones->count(),
            'primary_zone' => $zones->first()?->name,
            'organization_name' => $organization?->name,
            'has_multisite' => (bool) ($organization?->is_multisite),
        ];
    }

    public function getOrganizationSitesSummaryProperty()
    {
        $user = $this->currentUser();

        if (! $user?->organization_account_id) {
            return collect();
        }

        return OrganizationSite::query()
            ->with('serviceZone')
            ->where('organization_account_id', $user->organization_account_id)
            ->where('is_active', true)
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->limit(4)
            ->get();
    }

    public function getAvailableServicesProperty()
    {
        $user = $this->currentUser();
        $zoneIds = $this->coverageZoneIds();

        if (! $user || $zoneIds->isEmpty()) {
            return collect();
        }

        return ServiceCatalog::query()
            ->with(['zoneServiceRules' => function ($query) use ($zoneIds) {
                $query->whereIn('service_zone_id', $zoneIds)
                    ->where('is_enabled', true)
                    ->with('serviceZone');
            }])
            ->where('is_active', true)
            ->when(! $user->isEntreprise(), fn ($query) => $query->where('is_entreprise', false))
            ->whereHas('zoneServiceRules', function ($query) use ($zoneIds) {
                $query->whereIn('service_zone_id', $zoneIds)
                    ->where('is_enabled', true);
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit(6)
            ->get()
            ->map(function (ServiceCatalog $catalog) {
                $firstRule = $catalog->zoneServiceRules->first();

                return [
                    'id' => $catalog->id,
                    'name' => $catalog->name,
                    'service_type' => $catalog->service_type,
                    'zone_name' => $firstRule?->serviceZone?->name,
                    'base_price' => $firstRule?->base_price_override ?? $catalog->base_price,
                    'requires_manual_validation' => (bool) ($firstRule?->requires_manual_validation || $catalog->requires_manual_validation),
                    'is_entreprise' => (bool) $catalog->is_entreprise,
                ];
            });
    }

    public function getFavoriteEmployesProperty()
    {
        $user = $this->currentUser();

        if (! $user) {
            return collect();
        }

        $zoneIds = $this->coverageZoneIds();

        return $user->favoriteEmployes()
            ->when($zoneIds->isNotEmpty(), function ($query) use ($zoneIds) {
                $query->where(function ($employeeQuery) use ($zoneIds) {
                    $employeeQuery
                        ->whereIn('users.primary_service_zone_id', $zoneIds)
                        ->orWhereHas('zoneAssignments', function ($assignmentQuery) use ($zoneIds) {
                            $assignmentQuery->whereIn('service_zone_id', $zoneIds)
                                ->where('is_active', true);
                        });
                });
            })
            ->with(['serviceZones' => function ($query) use ($zoneIds) {
                if ($zoneIds->isNotEmpty()) {
                    $query->whereIn('service_zones.id', $zoneIds);
                }
                $query->orderBy('name');
            }])
            ->orderBy('name')
            ->get();
    }

    public function getRendezVousAvenirProperty()
    {
        return Booking::with(['employe', 'feedback', 'serviceZone', 'organizationSite', 'serviceCatalog', 'postalCode'])
            ->where('client_id', Auth::id())
            ->whereDate('date', '>=', now()->toDateString())
            ->orderBy('date', $this->tri)
            ->orderBy('heure', $this->tri)
            ->paginate(5);
    }

    public function getRendezVousPasseProperty()
    {
        return Booking::with(['employe', 'feedback', 'serviceZone', 'organizationSite', 'serviceCatalog', 'postalCode'])
            ->where('client_id', Auth::id())
            ->whereDate('date', '<', now()->toDateString())
            ->orderByDesc('date')
            ->orderByDesc('heure')
            ->limit(6)
            ->get();
    }

    public function getDernierRendezVousProperty()
    {
        return Booking::with(['employe', 'serviceZone', 'organizationSite', 'serviceCatalog', 'postalCode'])
            ->where('client_id', Auth::id())
            ->latest('date')
            ->latest('heure')
            ->first();
    }

    public function getProchainRendezVousProperty()
    {
        return Booking::with(['employe', 'feedback', 'serviceZone', 'organizationSite', 'serviceCatalog', 'postalCode'])
            ->where('client_id', Auth::id())
            ->whereDate('date', '>=', now()->toDateString())
            ->orderBy('date')
            ->orderBy('heure')
            ->first();
    }

    public function getAdressesRecentesProperty()
    {
        return Booking::query()
            ->where('client_id', Auth::id())
            ->whereNotNull('adresse')
            ->where('adresse', '!=', '')
            ->leftJoin('postal_codes', 'postal_codes.id', '=', 'bookings.postal_code_id')
            ->selectRaw("bookings.adresse, bookings.ville, COALESCE(bookings.code_postal, postal_codes.code) as code_postal, MAX(bookings.date) as last_date")
            ->groupBy('bookings.adresse', 'bookings.ville', DB::raw('COALESCE(bookings.code_postal, postal_codes.code)'))
            ->orderByDesc('last_date')
            ->limit(5)
            ->get();
    }

    public function getFinanceSnapshotProperty(): array
    {
        $user = $this->currentUser();

        if (! $user) {
            return [
                'quotes_count' => 0,
                'invoices_count' => 0,
                'outstanding_total' => 0.0,
                'overdue_count' => 0,
            ];
        }

        $quoteQuery = FinanceQuote::query()
            ->where(function ($query) use ($user) {
                $query->where('client_id', $user->id);

                if ($user->organization_account_id) {
                    $query->orWhere('organization_account_id', $user->organization_account_id);
                }
            });

        $invoiceQuery = FinanceInvoice::query()
            ->where(function ($query) use ($user) {
                $query->where('client_id', $user->id);

                if ($user->organization_account_id) {
                    $query->orWhere('organization_account_id', $user->organization_account_id);
                }
            });

        return [
            'quotes_count' => (clone $quoteQuery)->count(),
            'invoices_count' => (clone $invoiceQuery)->count(),
            'outstanding_total' => round((float) (clone $invoiceQuery)->sum('balance_due'), 2),
            'overdue_count' => (clone $invoiceQuery)->where('status', 'overdue')->count(),
        ];
    }

    public function getStatsClientProperty()
    {
        $clientId = Auth::id();

        return [
            'total' => Booking::query()
                ->where('client_id', $clientId)
                ->count(),

            'avenir' => Booking::query()
                ->where('client_id', $clientId)
                ->whereDate('date', '>=', now()->toDateString())
                ->count(),

            'termine' => Booking::query()
                ->where('client_id', $clientId)
                ->where('status', BookingStatus::TERMINE)
                ->count(),

            'feedbacks' => Booking::query()
                ->where('client_id', $clientId)
                ->whereHas('feedback')
                ->count(),
        ];
    }

    public function modifier($id)
    {
        $rdv = Booking::findOrFail($id);

        Gate::authorize('update', $rdv);

        if (! $rdv->canStillBeEditedByClient()) {
            $this->dispatch('toast', message: 'Ce rendez-vous ne peut plus être modifié.', type: 'error');
            return;
        }

        $this->editRdvId = $rdv->id;
        $this->editDate = $rdv->date?->format('Y-m-d') ?? $rdv->date;
        $this->editHeure = substr((string) $rdv->heure, 0, 5);
    }

    public function fermerEdition()
    {
        $this->editRdvId = null;
        $this->editDate = null;
        $this->editHeure = null;
    }

    public function enregistrerModif()
    {
        $rdv = Booking::where('id', $this->editRdvId)
            ->where('client_id', Auth::id())
            ->firstOrFail();

        Gate::authorize('update', $rdv);

        if (! $rdv->canStillBeEditedByClient()) {
            $this->dispatch('toast', message: 'Ce rendez-vous ne peut plus être modifié.', type: 'error');
            return;
        }

        $original = [
            'date' => $rdv->date,
            'heure' => $rdv->heure,
            'status' => $rdv->status,
            'priorite' => $rdv->priorite,
        ];

        $rdv->date = $this->editDate;
        $rdv->heure = $this->editHeure;
        $rdv->status = BookingStatus::EN_ATTENTE;

        $rdv->resetNotificationTrackingIfNeeded($original);
        $rdv->save();

        ActivityLogger::log('rdv_modifie_par_client', $rdv, [
            'ancienne_date' => $original['date']?->format('Y-m-d') ?? (string) $original['date'],
            'ancienne_heure' => $original['heure'],
            'nouvelle_date' => $rdv->date?->format('Y-m-d') ?? (string) $rdv->date,
            'nouvelle_heure' => $rdv->heure,
            'ancien_statut' => $original['status'],
            'nouveau_statut' => $rdv->status,
        ]);

        $this->fermerEdition();
        $this->dispatch('toast', message: 'Rendez-vous mis à jour.', type: 'success');
    }

    public function annuler($id)
    {
        $rdv = Booking::findOrFail($id);

        Gate::authorize('cancel', $rdv);

        if (! $rdv->canStillBeEditedByClient()) {
            $this->dispatch('toast', message: 'Ce rendez-vous ne peut plus être annulé.', type: 'error');
            return;
        }

        ActivityLogger::log('rdv_annule_par_client', $rdv, [
            'date' => $rdv->date?->format('Y-m-d') ?? (string) $rdv->date,
            'heure' => $rdv->heure,
            'service_type' => $rdv->service_type,
            'service_zone_id' => $rdv->service_zone_id,
        ]);

        $rdv->markCancelledByClient();

        $this->dispatch('toast', message: 'Rendez-vous annulé.', type: 'success');
    }

    public function render(): View
    {
        return view('livewire.client-dashboard', [
            'avenir' => $this->rendezVousAvenir,
            'passe' => $this->rendezVousPasse,
            'statsClient' => $this->statsClient,
            'dernierRendezVous' => $this->dernierRendezVous,
            'prochainRendezVous' => $this->prochainRendezVous,
            'adressesRecentes' => $this->adressesRecentes,
            'favoriteEmployes' => $this->favoriteEmployes,
            'activeSubscription' => $this->activeSubscription,
            'isPremium' => $this->isPremiumClient(),
            'coverageZones' => $this->coverageZones,
            'accountContext' => $this->accountContext,
            'availableServices' => $this->availableServices,
            'organizationSitesSummary' => $this->organizationSitesSummary,
            'financeSnapshot' => $this->financeSnapshot,
        ]);
    }
}