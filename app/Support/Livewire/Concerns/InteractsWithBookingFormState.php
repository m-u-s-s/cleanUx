<?php

namespace App\Support\Livewire\Concerns;

use App\Data\ZoneCoverageResult;
use App\Models\OrganizationSite;
use App\Models\PostalCode;
use App\Models\Booking;
use App\Models\ServiceCatalog;
use App\Models\ServiceZone;
use App\Models\User;
use App\Models\ZoneServiceRule;
use App\Services\International\CountryMarketResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

trait InteractsWithBookingFormState
{
    public ?int $resolvedServiceZoneId = null;
    protected function countryMarketResolver(): CountryMarketResolver
    {
        return app(CountryMarketResolver::class);
    }

    protected function currentCountryMarketContext(): array
    {
        return $this->countryMarketResolver()->resolveForBooking(
            Auth::user(),
            $this->currentPostalCode(),
            $this->currentServiceZone(),
            $this->selectedOrganizationSite(),
            $this->currentServiceCatalog(),
        );
    }

    protected function hydrateFromQuery(): void
    {
        $requestedEmployeId = request()->integer('employe');
        $sourceRdvId = request()->integer('source_rdv');

        if (
            $this->isPremiumClient() &&
            $requestedEmployeId &&
            User::where('id', $requestedEmployeId)->where('role', 'employe')->exists()
        ) {
            $this->employe_id = $requestedEmployeId;
        }

        if ($sourceRdvId) {
            if ($this->prefillFromSourceRendezVous($sourceRdvId)) {
                $this->prefilledFromSource = true;
            }
        } elseif (request()->query('prefill') === 'last') {
            if ($this->prefillFromLastRendezVous()) {
                $this->prefilledFromLast = true;
            }
        }

        if ($this->prefillAdresseFromQuery()) {
            $this->prefilledFromAddress = true;
        }

        $this->resolveCoverageContext();
    }

    protected function prefillFromLastRendezVous(): bool
    {
        $lastRdv = Booking::query()
            ->where('client_id', Auth::id())
            ->latest('date')
            ->latest('heure')
            ->first();

        if (! $lastRdv) {
            return false;
        }

        $this->prefillFromRendezVousModel($lastRdv);

        return true;
    }

    protected function prefillFromSourceRendezVous(int $sourceRdvId): bool
    {
        $rdv = Booking::query()
            ->where('id', $sourceRdvId)
            ->where('client_id', Auth::id())
            ->first();

        if (! $rdv) {
            return false;
        }

        $this->prefillFromRendezVousModel($rdv);

        return true;
    }

    protected function prefillAdresseFromQuery(): bool
    {
        $adresse = request()->query('adresse');
        $ville = request()->query('ville');
        $codePostal = request()->query('code_postal');

        $hasPrefill = false;

        if ($adresse) {
            $this->adresse = $adresse;
            $hasPrefill = true;
        }

        if ($ville) {
            $this->ville = $ville;
            $hasPrefill = true;
        }

        if ($codePostal) {
            $this->postal_code_input = $codePostal;
            $hasPrefill = true;
        }

        if ($hasPrefill) {
            $this->step = max($this->step, 3);
        }

        return $hasPrefill;
    }

    protected function prefillFromRendezVousModel(Booking $rdv): void
    {
        $this->selected_service_identifier = data_get($rdv->pricing_snapshot, 'service_identifier')
            ?: data_get($rdv->pricing_snapshot, 'service.service_identifier')
            ?: $rdv->serviceCatalog?->code
            ?: $rdv->serviceCatalog?->slug
            ?: data_get($rdv->pricing_snapshot, 'service.code')
            ?: data_get($rdv->pricing_snapshot, 'service.slug');
        $this->type_lieu = $rdv->type_lieu;
        $this->frequence = $rdv->frequence;
        $this->surface = $rdv->surface;
        $this->options_prestation = $rdv->options_prestation ?? [];
        $this->zones_specifiques = $rdv->zones_specifiques ?? [];
        $this->materiel_specifique = is_array($rdv->materiel_specifique)
            ? implode(', ', $rdv->materiel_specifique)
            : $rdv->materiel_specifique;
        $this->commentaire_client = $rdv->commentaire_client;
        $this->presence_animaux = (bool) $rdv->presence_animaux;
        $this->acces_parking = (bool) $rdv->acces_parking;
        $this->materiel_fournit = (bool) $rdv->materiel_fournit;
        $this->adresse = $rdv->adresse;
        $this->ville = $rdv->ville;
        $this->postal_code_input = $rdv->postalCode?->code ?: $rdv->code_postal;
        $this->telephone_client = $rdv->telephone_client;
        $this->priorite = $rdv->priorite ?: 'normale';
        $this->organization_site_id = $rdv->organization_site_id;
        $this->is_recurrent = (bool) $rdv->is_recurrent;
        $this->recurrence_rule = $rdv->recurrence_rule;
        $this->recurrence_frequency = $rdv->recurrence_frequency;
        $this->recurrence_interval = (int) ($rdv->recurrence_interval ?: 1);
        $this->recurrence_until = optional($rdv->recurrence_until)->format('Y-m-d');
        $this->recurrence_count = $rdv->recurrence_count;
        $this->recurrence_days = $rdv->recurrence_days ?? [];
        $this->is_favorite_slot = (bool) $rdv->is_favorite_slot;
        $this->rdvDate = optional($rdv->date)->format('Y-m-d');
        $this->rdvHeure = substr((string) $rdv->heure, 0, 5);

        $corporateContext = data_get($rdv->pricing_snapshot, 'corporate_context', []);
        $this->site_contact_name = $corporateContext['site_contact_name'] ?? $rdv->organizationSite?->contact_name;
        $this->site_contact_phone = $corporateContext['site_contact_phone'] ?? $rdv->organizationSite?->phone;
        $this->purchase_order_reference = $corporateContext['purchase_order_reference'] ?? null;
        $this->cost_center = $corporateContext['cost_center'] ?? null;
        $this->site_instructions = $corporateContext['site_instructions'] ?? $rdv->organizationSite?->access_instructions;

        if ($rdv->organizationSite) {
            $this->applyOrganizationSite($rdv->organizationSite, overwriteAddress: false);
        }

        $this->step = max($this->step, 4);
    }

    public function isPremiumClient(): bool
    {
        return Auth::check()
            && ! Auth::user()->isEntreprise()
            && Auth::user()->canChooseEmployee();
    }

    public function isEntrepriseCustomer(): bool
    {
        return Auth::check()
            && Auth::user()->isEntreprise()
            && filled(Auth::user()->organization_account_id);
    }

    public function getOrganizationSitesProperty()
    {
        if (! $this->isEntrepriseCustomer()) {
            return collect();
        }

        return OrganizationSite::query()
            ->where('organization_account_id', Auth::user()->organization_account_id)
            ->where('is_active', true)
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get();
    }

    protected function selectedOrganizationSite(): ?OrganizationSite
    {
        if (! $this->organization_site_id || ! $this->isEntrepriseCustomer()) {
            return null;
        }

        return $this->organizationSites->firstWhere('id', $this->organization_site_id);
    }

    public function updatedOrganizationSiteId(): void
    {
        $site = $this->selectedOrganizationSite();

        if ($site) {
            $this->applyOrganizationSite($site);
            $policy = $this->currentEntreprisePolicy($site);
            if (! filled($this->cost_center) && filled($policy['default_cost_center'] ?? null)) {
                $this->cost_center = (string) $policy['default_cost_center'];
            }
        }

        $this->resolveCoverageContext();
        $this->chargerEmployesDisponibles();
        $this->chargerCreneauxDisponibles();
        $this->refreshEstimations();
    }

    protected function applyOrganizationSite(OrganizationSite $site, bool $overwriteAddress = true): void
    {
        if ($overwriteAddress) {
            $this->adresse = $site->address_line_1 ?: $this->adresse;
            $this->ville = $site->city ?: $this->ville;
            $this->postal_code_input = $site->postal_code ?: $this->postal_code_input;
        }

        $this->organization_site_id = $site->id;
        $this->site_contact_name = $this->site_contact_name ?: $site->contact_name;
        $this->site_contact_phone = $this->site_contact_phone ?: $site->phone;
        $this->site_instructions = $this->site_instructions ?: $site->access_instructions;
    }

    protected function currentEntreprisePolicy(?OrganizationSite $site = null): array
    {
        if (! $this->isEntrepriseCustomer()) {
            return [
                'approval_mode' => 'auto',
                'approval_required' => false,
                'purchase_order_required' => false,
                'default_cost_center' => null,
            ];
        }

        $site = $site ?? $this->selectedOrganizationSite();
        $account = Auth::user()?->organizationAccount;

        $accountMeta = (array) ($account?->metadata ?? []);
        $siteMeta = (array) ($site?->metadata ?? []);

        $approvalMode = (string) ($siteMeta['approval_mode'] ?? 'inherit');
        if ($approvalMode === 'inherit' || $approvalMode === '') {
            $approvalMode = (string) ($accountMeta['approval_mode'] ?? 'auto');
        }

        $purchaseOrderRequired = array_key_exists('purchase_order_required', $siteMeta)
            ? (bool) $siteMeta['purchase_order_required']
            : (bool) ($accountMeta['purchase_order_required'] ?? false);

        $defaultCostCenter = $siteMeta['default_cost_center'] ?? ($accountMeta['default_cost_center'] ?? null);

        return [
            'approval_mode' => $approvalMode ?: 'auto',
            'approval_required' => $approvalMode === 'manual',
            'purchase_order_required' => $purchaseOrderRequired,
            'default_cost_center' => $defaultCostCenter,
        ];
    }

    protected function currentServiceCatalog(): ?ServiceCatalog
    {
        if (! $this->selected_service_identifier) {
            $this->resolvedServiceCatalogId = null;
            return null;
        }

        $catalog = $this->zoneCoverageService()->resolveServiceCatalog(
            $this->selected_service_identifier,
            $this->currentServiceZone(),
        );

        $this->resolvedServiceCatalogId = $catalog?->id;

        return $catalog;
    }

    protected function currentPostalCode(): ?PostalCode
    {
        return $this->zoneCoverageService()->resolvePostalCode($this->postal_code_input, $this->ville);
    }

    protected function resolveServiceZoneCandidate(bool $bookableOnly = false): ?ServiceZone
    {
        return $this->zoneCoverageService()->resolveServiceZone(
            $this->currentPostalCode(),
            $this->selectedOrganizationSite(),
            $bookableOnly,
        );
    }

    protected function currentServiceZone(): ?ServiceZone
    {
        return $this->resolveServiceZoneCandidate(false);
    }

    protected function currentBookableServiceZone(): ?ServiceZone
    {
        return $this->resolveServiceZoneCandidate(true);
    }

    protected function currentZoneServiceRule(): ?ZoneServiceRule
    {
        return $this->zoneCoverageService()->resolveZoneServiceRule(
            $this->currentServiceZone(),
            $this->currentServiceCatalog(),
        );
    }

    protected function currentCoverageResolution(): ZoneCoverageResult
    {
        return $this->zoneCoverageService()->resolveCoverage(
            $this->postal_code_input,
            $this->ville,
            $this->selected_service_identifier,
            $this->selectedOrganizationSite(),
        );
    }

    protected function resolveCoverageContext(): void
    {
        $resolution = $this->currentCoverageResolution();

        $this->resolvedPostalCodeId = $resolution->postalCode?->id;
        $this->resolvedServiceZoneId = $resolution->zone?->id;
        $this->resolvedServiceCatalogId = $resolution->serviceCatalog?->id;
        $this->coverageStatus = $resolution->status;
        $this->coverageMessage = $resolution->message;
    }

    protected function ensureCoverageIsBookable(): bool
    {
        $this->resolveCoverageContext();
        $resolution = $this->currentCoverageResolution();

        if (! $resolution->postalCode) {
            $this->addError('postal_code_input', 'Code postal ou ville non reconnu.');
            return false;
        }

        if (! $resolution->zone) {
            $this->addError('postal_code_input', 'Cette zone n’est pas encore couverte.');
            return false;
        }

        if ($resolution->zone->status !== 'active') {
            $this->addError('postal_code_input', 'Cette zone est temporairement indisponible.');
            return false;
        }

        if (! $resolution->zone->is_bookable) {
            $this->addError('postal_code_input', 'Cette zone n’est pas réservable en ligne pour le moment.');
            return false;
        }

        if (! $resolution->serviceCatalog) {
            $this->addError('selected_service_identifier', 'Service introuvable.');
            return false;
        }

        if ($resolution->serviceCatalog->is_entreprise && ! $this->isEntrepriseCustomer()) {
            $this->addError('selected_service_identifier', 'Ce service est réservé aux comptes entreprise.');
            return false;
        }

        if (! $resolution->zoneServiceRule) {
            $this->addError('selected_service_identifier', 'Ce service n’est pas disponible dans votre zone.');
            return false;
        }

        return true;
    }

    protected function normalizedBookingLocationData(PostalCode $postal, ?OrganizationSite $organizationSite): array
    {
        if ($organizationSite) {
            return [
                'adresse' => $organizationSite->address_line_1 ?: $this->adresse,
                'ville' => $organizationSite->city ?: $postal->city_name,
                'code_postal' => $organizationSite->postal_code ?: $postal->code,
            ];
        }

        return [
            'adresse' => $this->adresse,
            'ville' => $postal->city_name,
            'code_postal' => $postal->code,
        ];
    }

    protected function bookingMotifFor(ServiceCatalog $catalog, PostalCode $postal, ?OrganizationSite $organizationSite): string
    {
        $locationLabel = $organizationSite?->name ?: $postal->city_name;

        return trim($catalog->name . ' · ' . $locationLabel);
    }

    public function getSurfacesProperty(): array
    {
        return [
            'moins_50' => 'Moins de 50 m²',
            '50_100' => '50 à 100 m²',
            '100_150' => '100 à 150 m²',
            '150_250' => '150 à 250 m²',
            'plus_250' => 'Plus de 250 m²',
        ];
    }

    public function getServicesProperty(): array
    {
        $resolvedServiceZoneId = $this->resolvedServiceZoneId ?? null;

        $query = ServiceCatalog::query()
            ->with('trade:id,name,slug,sort_order')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($resolvedServiceZoneId) {
            $query->whereHas('zoneServiceRules', function ($ruleQuery) use ($resolvedServiceZoneId) {
                $ruleQuery
                    ->where('service_zone_id', $resolvedServiceZoneId)
                    ->where('is_enabled', true);
            });
        }

        $catalogs = $query->get();


        // Pas de service ? Fallback flat — la vue gère "Autres"
        if ($catalogs->isEmpty()) {
            return [
                'Nettoyage' => [
                    'nettoyage_standard' => 'Nettoyage standard',
                ],
            ];
        }

        if ($catalogs->isNotEmpty()) {
            return $catalogs
                ->mapWithKeys(fn(ServiceCatalog $service) => [
                    ($service->code ?: $service->slug) => $service->name,
                ])
                ->toArray();
        }

        return [
            'nettoyage_standard' => 'Nettoyage standard',
            'nettoyage_profond' => 'Nettoyage en profondeur',
            'fin_de_chantier' => 'Nettoyage fin de chantier',
            'fin_de_bail' => 'Nettoyage fin de bail',
            'bureaux' => 'Nettoyage bureaux / professionnels',
        ];
    }

    /**
     * Phase 1 multi-métiers — version groupée par Trade pour rendu en
     * <optgroup>. Ne remplace PAS getServicesProperty() (back-compat) :
     * cette méthode est exposée en plus, sous le nom $servicesGroupedByTrade,
     * et utilisée uniquement par la vue de réservation.
     *
     * Format retourné :
     * [
     *   'Nettoyage' => ['nettoyage_standard' => 'Nettoyage standard', ...],
     *   'Peinture'  => ['peinture_interieure' => 'Peinture intérieure', ...],
     *   'Autres'    => [...services sans trade rattaché...],
     * ]
     *
     * Les services sans trade sont regroupés sous "Autres" (clé fallback)
     * pour ne PAS être perdus pendant la phase de transition multi-métiers.
     */
    public function getServicesGroupedByTradeProperty(): array
    {
        $resolvedServiceZoneId = $this->resolvedServiceZoneId ?? null;
        $query = ServiceCatalog::query()
            ->with('trade:id,name,slug,sort_order')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($resolvedServiceZoneId) {
            $query->whereHas('zoneServiceRules', function ($ruleQuery) use ($resolvedServiceZoneId){
                $ruleQuery
                    ->where('service_zone_id', $resolvedServiceZoneId)
                    ->where('is_enabled', true);
            });
        }

        $catalogs = $query->get();

        // Pas de service ? Fallback flat — la vue gère "Autres"
        if ($catalogs->isEmpty()) {
            return [
                'Nettoyage' => [
                    'nettoyage_standard' => 'Nettoyage standard',
                    'nettoyage_profond'  => 'Nettoyage en profondeur',
                    'fin_de_chantier'    => 'Nettoyage fin de chantier',
                    'fin_de_bail'        => 'Nettoyage fin de bail',
                    'bureaux'            => 'Nettoyage bureaux / professionnels',
                ],
            ];
        }

        // Group by trade name, preserve trade sort_order via collection
        return $catalogs
            ->groupBy(fn(ServiceCatalog $s) => $s->trade?->name ?: 'Autres')
            ->map(fn($group) => $group->mapWithKeys(fn(ServiceCatalog $s) => [
                ($s->code ?: $s->slug) => $s->name,
            ])->toArray())
            ->sortBy(function ($_, $tradeName) use ($catalogs) {
                // "Autres" en dernier, sinon respecter trade.sort_order
                if ($tradeName === 'Autres') return PHP_INT_MAX;
                $first = $catalogs->first(fn(ServiceCatalog $s) => $s->trade?->name === $tradeName);
                return $first?->trade?->sort_order ?? 0;
            })
            ->toArray();
    }

    public function updatedPostalCodeInput(): void
    {
        $this->resolveCoverageContext();
        $this->chargerEmployesDisponibles();
        $this->chargerCreneauxDisponibles();
        $this->refreshEstimations();
    }

    public function updatedVille(): void
    {
        $this->resolveCoverageContext();
        $this->chargerEmployesDisponibles();
        $this->chargerCreneauxDisponibles();
        $this->refreshEstimations();
    }

    public function getTypesLieuxProperty(): array
    {
        return [
            'appartement' => 'Appartement',
            'maison' => 'Maison',
            'bureau' => 'Bureau',
            'commerce' => 'Commerce',
            'autre' => 'Autre',
        ];
    }

    protected function makeReference(?string $prefix = 'CUX'): string
    {
        return $prefix . '-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
    }

    public function getFrequencesProperty(): array
    {
        return [
            'ponctuel' => 'Ponctuel',
            'hebdomadaire' => 'Hebdomadaire',
            'bihebdomadaire' => 'Toutes les 2 semaines',
            'mensuel' => 'Mensuel',
        ];
    }


    public function getRecurringFrequencyOptionsProperty(): array
    {
        return [
            'daily' => 'Chaque jour',
            'weekly' => 'Chaque semaine',
            'monthly' => 'Chaque mois',
        ];
    }

    public function getRecurringDayOptionsProperty(): array
    {
        return [
            1 => 'Lun',
            2 => 'Mar',
            3 => 'Mer',
            4 => 'Jeu',
            5 => 'Ven',
            6 => 'Sam',
            7 => 'Dim',
        ];
    }

    protected function normalizeRecurringInputs(): void
    {
        if (! $this->is_recurrent) {
            $this->recurrence_frequency = null;
            $this->recurrence_interval = 1;
            $this->recurrence_until = null;
            $this->recurrence_count = null;
            $this->recurrence_days = [];
            $this->recurrence_rule = null;

            return;
        }

        $this->recurrence_frequency = filled($this->recurrence_frequency) ? (string) $this->recurrence_frequency : null;
        $this->recurrence_interval = filled($this->recurrence_interval) ? (int) $this->recurrence_interval : 1;
        $this->recurrence_until = filled($this->recurrence_until) ? (string) $this->recurrence_until : null;
        $this->recurrence_count = filled($this->recurrence_count) ? (int) $this->recurrence_count : null;
        $this->recurrence_days = array_values(array_map('intval', (array) $this->recurrence_days));
    }

    protected function validateRecurringConfiguration(): bool
    {
        if (! $this->is_recurrent) {
            return true;
        }

        if (! $this->recurrence_frequency) {
            $this->addError('recurrence_frequency', 'Choisissez une fréquence de récurrence.');
            return false;
        }

        if (! $this->recurrence_until && ! $this->recurrence_count) {
            $this->addError('recurrence_count', 'Indiquez une date de fin ou un nombre d’occurrences.');
            return false;
        }

        if ($this->recurrence_frequency === 'weekly' && empty($this->normalizedRecurrenceDays())) {
            $this->addError('recurrence_days', 'Choisissez au moins un jour de passage.');
            return false;
        }

        return true;
    }

    protected function normalizedRecurrenceDays(): array
    {
        if (! $this->is_recurrent || $this->recurrence_frequency !== 'weekly') {
            return [];
        }

        $days = collect($this->recurrence_days)
            ->map(fn($day) => (int) $day)
            ->filter(fn($day) => $day >= 1 && $day <= 7)
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($days !== []) {
            return $days;
        }

        if ($this->rdvDate) {
            return [Carbon::parse($this->rdvDate)->isoWeekday()];
        }

        return [];
    }

    protected function recurringRuleLabel(): ?string
    {
        if (! $this->is_recurrent || ! $this->recurrence_frequency) {
            return null;
        }

        return match ($this->recurrence_frequency) {
            'daily' => 'daily',
            'weekly' => $this->recurrence_interval === 2 ? 'biweekly' : 'weekly',
            'monthly' => 'monthly',
            default => null,
        };
    }

    public function getPrioritesProperty(): array
    {
        return [
            'normale' => 'Normale',
            'haute' => 'Haute',
            'urgente' => 'Urgente',
        ];
    }

    public function getOptionsDisponiblesProperty(): array
    {
        return [
            'vitres' => 'Vitres',
            'frigo' => 'Frigo',
            'four' => 'Four',
            'repassage' => 'Repassage',
            'desinfection' => 'Désinfection',
        ];
    }

    public function getZonesDisponiblesProperty(): array
    {
        return [
            'cuisine' => 'Cuisine',
            'salle_de_bain' => 'Salle de bain',
            'salon' => 'Salon',
            'chambres' => 'Chambres',
            'bureau' => 'Bureau',
            'escaliers' => 'Escaliers',
        ];
    }

    protected function eligibleEmployeesQuery(?int $zoneId = null)
    {
        return $this->employeeAvailabilityService()->eligibleEmployeesQuery($zoneId);
    }

    protected function employeeCoverageScore(User $employee, int $zoneId): int
    {
        return $this->employeeAvailabilityService()->employeeCoverageScore($employee, $zoneId);
    }

    protected function sortedEligibleEmployeesForZone(int $zoneId)
    {
        return $this->employeeAvailabilityService()->sortedEligibleEmployeesForZone($zoneId);
    }

    public function getEmployesProperty()
    {
        $zoneId = $this->resolvedServiceZoneId ?: $this->currentBookableServiceZone()?->id;
        $employes = $zoneId
            ? $this->sortedEligibleEmployeesForZone($zoneId)
            : $this->eligibleEmployeesQuery()->get();

        if (! $this->isPremiumClient() || ! Auth::check()) {
            return $employes;
        }

        $favoriteIds = Auth::user()->favoriteEmployes()->pluck('users.id')->toArray();

        $favorites = $employes->filter(fn($e) => in_array($e->id, $favoriteIds));
        $others = $employes->reject(fn($e) => in_array($e->id, $favoriteIds));

        return $favorites->concat($others)->values();
    }

    public function getHasPrefillProperty(): bool
    {
        return $this->prefilledFromLast
            || $this->prefilledFromSource
            || $this->prefilledFromAddress;
    }

    public function getSelectedServiceLabelProperty(): string
    {
        $catalog = $this->currentServiceCatalog();

        if ($catalog?->name) {
            return (string) $catalog->name;
        }

        $label = $this->services[$this->selected_service_identifier] ?? null;

        if (filled($label)) {
            return (string) $label;
        }

        return '—';
    }
}
