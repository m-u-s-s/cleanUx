<?php

namespace App\Livewire;

use App\Livewire\Concerns\AnnuleUnRendezVousClient;
use App\Models\Booking;
use App\Models\FinanceInvoice;
use App\Models\FinanceQuote;
use App\Models\OrganizationSite;
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
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

class ClientDashboard extends Component
{
    use AnnuleUnRendezVousClient;
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

    /** @var Collection<int, int<1, max>>|null memoised per render to avoid re-querying (L11) */
    private ?Collection $cachedZoneIds = null;

    protected function coverageZoneIds(): Collection
    {
        // L11 — this runs 3×/render (coverage zones, available services, favourite providers);
        // memoise so the OrganizationSite / ServiceZone lookups happen once per render.
        if ($this->cachedZoneIds !== null) {
            return $this->cachedZoneIds;
        }

        $user = $this->currentUser();

        if (! $user) {
            return $this->cachedZoneIds = collect();
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

        return $this->cachedZoneIds = $zoneIds->filter()->unique()->values();
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
            ->selectRaw('bookings.adresse, bookings.ville, COALESCE(bookings.code_postal, postal_codes.code) as code_postal, MAX(bookings.date) as last_date')
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

    /**
     * LES DÉPENSES DES SIX DERNIERS MOIS, en UNE requête.
     *
     * Le client n'avait aucune série : quatre compteurs, et rien qui montre une évolution.
     * Un compteur dit « vous avez 12 interventions » ; une courbe dit « vos dépenses ont
     * doublé en mars » — c'est la seconde qui sert à décider.
     *
     * `final_price` D'ABORD, `devis_estime` EN REPLI. Le prix final n'existe qu'une fois la
     * mission close ; s'en tenir à lui ferait disparaître du graphique toute intervention à
     * venir, et la courbe s'arrêterait au mois dernier sans que rien ne l'explique.
     *
     * LES MOIS SANS DÉPENSE SONT REMPLIS À ZÉRO. Une série qui saute les mois vides dessine
     * une pente continue entre janvier et avril : elle ment sur ce qui s'est passé entre les
     * deux.
     *
     * @return array<int, array{mois: string, libelle: string, montant: float}>
     */
    public function getDepensesParMoisProperty(): array
    {
        $clientId = Auth::id();

        if (! $clientId) {
            return [];
        }

        // LE PREMIER DU MOIS D'ABORD : soustraire depuis un 31 deborde sur le mois suivant.
        $debut = now()->copy()->startOfMonth()->subMonths(5);

        /*
         * DEUX DIALECTES, UNE SEULE EXPRESSION CHOISIE AVANT.
         *
         * La suite tourne sur SQLite, l'application sur MySQL : `DATE_FORMAT` n'existe pas
         * d'un cote, `strftime` de l'autre. Empiler deux `selectRaw` les CUMULERAIT — la
         * requete partirait avec deux colonnes `mois` et un GROUP BY ambigu.
         */
        $pilote = DB::connection()->getDriverName();
        $moisSql = $pilote === 'sqlite'
            ? "strftime('%Y-%m', date)"
            : "DATE_FORMAT(date, '%Y-%m')";

        $brut = Booking::query()
            ->where('client_id', $clientId)
            ->where('status', '!=', BookingStatus::ANNULE)
            ->where('date', '>=', $debut->toDateString())
            ->selectRaw("{$moisSql} as mois, SUM(COALESCE(final_price, devis_estime, 0)) as total")
            ->groupBy('mois')
            ->pluck('total', 'mois');

        $serie = [];

        for ($i = 0; $i < 6; $i++) {
            $mois = $debut->copy()->addMonths($i);
            $cle = $mois->format('Y-m');

            $serie[] = [
                'mois' => $cle,
                'libelle' => $mois->translatedFormat('M'),
                'montant' => round((float) ($brut[$cle] ?? 0), 2),
            ];
        }

        return $serie;
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

    /** Le bouton de la carte : il OUVRE le devis, il n'annule pas. */
    public function annuler($id): void
    {
        $this->demanderAnnulation((int) $id);
    }

    /**
     * La salutation de l'en-tête : elle suit l'heure DE L'UTILISATEUR, pas celle du serveur.
     *
     * La plateforme couvre la Belgique, la France et le Maroc : dire « Bonsoir » à seize heures
     * à Casablanca parce que le serveur est à Bruxelles serait faux pour de vrai.
     */
    public function salutation(): string
    {
        $utilisateur = Auth::user();
        $prenom = Str::before(trim((string) $utilisateur?->name), ' ');
        $heure = now($utilisateur?->timezone ?: config('app.timezone'))->hour;

        // Les six phrases sont ECRITES EN TOUTES LETTRES : composer la clé par concaténation la
        // rendrait introuvable pour qui cherche ce que la page affiche.
        if ($prenom === '') {
            return match (true) {
                $heure < 12 => __('Bonjour'),
                $heure < 18 => __('Bon après-midi'),
                default => __('Bonsoir'),
            };
        }

        return match (true) {
            $heure < 12 => __('Bonjour :prenom', ['prenom' => $prenom]),
            $heure < 18 => __('Bon après-midi :prenom', ['prenom' => $prenom]),
            default => __('Bonsoir :prenom', ['prenom' => $prenom]),
        };
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
            'salutation' => $this->salutation(),
        ]);
    }
}
