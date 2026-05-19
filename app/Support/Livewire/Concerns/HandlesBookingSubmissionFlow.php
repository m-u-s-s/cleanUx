<?php

namespace App\Support\Livewire\Concerns;

use App\Models\Booking;
use App\Models\ServiceZone;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

trait HandlesBookingSubmissionFlow
{
    protected function rules(): array
    {
        // Phase F3 — quand le Trade fournit un schema dynamique, les champs
        // cleaning hardcodés deviennent NULLABLE : leur saisie est déléguée
        // au schema (rendu via <x-trade-form-fields>) et leur validation est
        // assurée par RendersTradeFormSchema::tradeFormAnswersRules().
        $hasTradeSchema = method_exists($this, 'hasTradeFormSchema') && $this->hasTradeFormSchema();

        $typeLieuRule  = $hasTradeSchema ? ['nullable', 'string', 'max:255'] : ['required', 'string', 'max:255'];
        $frequenceRule = $hasTradeSchema ? ['nullable', 'string', 'max:255'] : ['required', 'string', 'max:255'];
        $surfaceRule   = $hasTradeSchema ? ['nullable', 'string', 'max:255'] : ['required', Rule::in(array_keys($this->surfaces))];

        return [
            'selected_service_identifier' => ['required', 'string', 'max:255'],
            'booking_mode' => ['required', Rule::in(['scheduled', 'asap'])],
            'type_lieu' => $typeLieuRule,
            'frequence' => $frequenceRule,
            'surface' => $surfaceRule,

            'options_prestation' => ['nullable', 'array'],
            'zones_specifiques' => ['nullable', 'array'],
            'materiel_specifique' => ['nullable', 'string', 'max:255'],
            'commentaire_client' => ['nullable', 'string', 'max:2000'],

            'presence_animaux' => ['boolean'],
            'acces_parking' => ['boolean'],
            'materiel_fournit' => ['boolean'],

            'adresse' => ['required', 'string', 'max:255'],
            'ville' => ['required', 'string', 'max:255'],
            'postal_code_input' => ['required', 'string', 'max:20'],
            'telephone_client' => ['required', 'string', 'max:30'],
            'priorite' => ['required', Rule::in(['normale', 'haute', 'urgente'])],
            'organization_site_id' => $this->isEntrepriseCustomer()
                ? [
                    'required',
                    'integer',
                    Rule::exists('organization_sites', 'id')->where(function ($query) {
                        $query
                            ->where('organization_account_id', Auth::user()->organization_account_id)
                            ->where('is_active', true);
                    }),
                ]
                : ['nullable'],
            'site_contact_name' => ['nullable', 'string', 'max:255'],
            'site_contact_phone' => ['nullable', 'string', 'max:30'],
            'purchase_order_reference' => [Rule::requiredIf(fn() => $this->isEntrepriseCustomer() && (bool) ($this->currentEntreprisePolicy()['purchase_order_required'] ?? false)), 'nullable', 'string', 'max:100'],
            'cost_center' => ['nullable', 'string', 'max:100'],
            'site_instructions' => ['nullable', 'string', 'max:1000'],

            'employe_id' => $this->isPremiumClient()
                ? ['required', 'exists:users,id']
                : ['nullable'],

            'rdvDate' => ['required', 'date'],
            'rdvHeure' => ['required', 'date_format:H:i'],

            'is_recurrent' => ['boolean'],
            'recurrence_rule' => ['nullable', 'string', 'max:255'],
            'recurrence_frequency' => $this->is_recurrent ? ['required', Rule::in(['daily', 'weekly', 'monthly'])] : ['nullable'],
            'recurrence_interval' => $this->is_recurrent ? ['required', 'integer', 'min:1', 'max:12'] : ['nullable'],
            'recurrence_until' => $this->is_recurrent
                ? (filled($this->recurrence_until) ? ['date', 'after_or_equal:rdvDate'] : ['nullable'])
                : ['nullable'],
            'recurrence_count' => $this->is_recurrent ? ['nullable', 'integer', 'min:2', 'max:52'] : ['nullable'],
            'recurrence_days' => ['nullable', 'array'],
            'is_favorite_slot' => ['boolean'],

            'photos.*' => ['nullable', 'image', 'max:4096'],
        ];
    }

    public function nextStep(): void
    {
        $this->resetErrorBag();

        if ($this->step === 1) {
            $this->validateOnlyStep1();
        }

        if ($this->step === 2) {
            $this->validateOnlyStep2();
        }

        if ($this->step === 3) {
            $this->validateOnlyStep3();
        }

        if ($this->step === 4) {
            $this->validateOnlyStep4();
        }

        if ($this->step < 5) {
            $this->step++;
        }

        $this->refreshEstimations();

        if ($this->step === 4 || $this->step === 5) {
            $this->chargerCreneauxDisponibles();
        }
    }

    public function previousStep(): void
    {
        if ($this->step > 1) {
            $this->step--;
        }
    }

    protected function validateOnlyStep1(): void
    {
        $hasTradeSchema = method_exists($this, 'hasTradeFormSchema') && $this->hasTradeFormSchema();

        $this->validate([
            'selected_service_identifier' => ['required', 'string', 'max:255'],
            'type_lieu' => $hasTradeSchema ? ['nullable', 'string', 'max:255'] : ['required', 'string', 'max:255'],
            'frequence' => $hasTradeSchema ? ['nullable', 'string', 'max:255'] : ['required', 'string', 'max:255'],
            'surface'   => $hasTradeSchema
                ? ['nullable', 'string', 'max:255']
                : ['required', Rule::in(array_keys($this->surfaces))],
        ]);
    }

    protected function validateOnlyStep2(): void
    {
        $legacyRules = [
            'options_prestation' => ['nullable', 'array'],
            'zones_specifiques' => ['nullable', 'array'],
            'materiel_specifique' => ['nullable', 'string', 'max:255'],
            'commentaire_client' => ['nullable', 'string', 'max:2000'],
            'presence_animaux' => ['boolean'],
            'acces_parking' => ['boolean'],
            'materiel_fournit' => ['boolean'],
            'photos.*' => ['nullable', 'image', 'max:4096'],
        ];

        // Phase F3 — quand le Trade fournit un schema, valider ses answers ici
        if (method_exists($this, 'hasTradeFormSchema') && $this->hasTradeFormSchema()) {
            $legacyRules = array_merge($legacyRules, $this->tradeFormAnswersRules('tradeFormAnswers'));
        }

        $this->validate($legacyRules);
    }

    protected function validateOnlyStep3(): void
    {
        $rules = [
            'adresse' => ['required', 'string', 'max:255'],
            'ville' => ['required', 'string', 'max:255'],
            'postal_code_input' => ['required', 'string', 'max:20'],
            'telephone_client' => ['required', 'string', 'max:30'],
            'priorite' => ['required', Rule::in(['normale', 'haute', 'urgente'])],
            'site_contact_name' => ['nullable', 'string', 'max:255'],
            'site_contact_phone' => ['nullable', 'string', 'max:30'],
            'purchase_order_reference' => [Rule::requiredIf(fn() => $this->isEntrepriseCustomer() && (bool) ($this->currentEntreprisePolicy()['purchase_order_required'] ?? false)), 'nullable', 'string', 'max:100'],
            'cost_center' => ['nullable', 'string', 'max:100'],
            'site_instructions' => ['nullable', 'string', 'max:1000'],
        ];

        if ($this->isEntrepriseCustomer()) {
            $rules['organization_site_id'] = [
                'required',
                'integer',
                Rule::exists('organization_sites', 'id')->where(function ($query) {
                    $query
                        ->where('organization_account_id', Auth::user()->organization_account_id)
                        ->where('is_active', true);
                }),
            ];
        }

        $this->validate($rules);

        $this->ensureCoverageIsBookable();
    }

    protected function validateOnlyStep4(): void
    {
        $this->normalizeRecurringInputs();

        $rules = [
            'rdvDate' => ['required', 'date'],
            'rdvHeure' => ['required', 'date_format:H:i'],
            'is_recurrent' => ['boolean'],
            'recurrence_rule' => ['nullable', 'string', 'max:255'],
            'recurrence_frequency' => $this->is_recurrent ? ['required', Rule::in(['daily', 'weekly', 'monthly'])] : ['nullable'],
            'recurrence_interval' => $this->is_recurrent ? ['required', 'integer', 'min:1', 'max:12'] : ['nullable'],
            'recurrence_until' => $this->is_recurrent
                ? (filled($this->recurrence_until) ? ['date', 'after_or_equal:rdvDate'] : ['nullable'])
                : ['nullable'],
            'recurrence_count' => $this->is_recurrent ? ['nullable', 'integer', 'min:2', 'max:52'] : ['nullable'],
            'recurrence_days' => ['nullable', 'array'],
            'is_favorite_slot' => ['boolean'],
            'booking_mode' => ['required', Rule::in(['scheduled', 'asap'])],
        ];

        if ($this->isPremiumClient()) {
            $rules['employe_id'] = ['required', 'exists:users,id'];
        }

        $this->validate($rules);

        if (! $this->validateRecurringConfiguration()) {
            return;
        }

        $this->validateBookingBusinessRules();
    }

    public function updatedEmployeId(): void
    {
        if ($this->isPremiumClient()) {
            $this->chargerCreneauxDisponibles();
        }
    }


    public function updatedIsRecurrent(): void
    {
        if (! $this->is_recurrent) {
            $this->recurrence_frequency = null;
            $this->recurrence_interval = 1;
            $this->recurrence_until = null;
            $this->recurrence_count = null;
            $this->recurrence_days = [];
            $this->recurrence_rule = null;
        }
    }

    public function updatedRdvDate(): void
    {
        $this->chargerCreneauxDisponibles();
    }

    public function updatedSelectedServiceIdentifier(): void
    {
        $this->resolveCoverageContext();
        $this->chargerEmployesDisponibles();
        $this->chargerCreneauxDisponibles();
        $this->refreshEstimations();
    }

    public function updatedSurface(): void
    {
        $this->refreshEstimations();
    }

    public function updatedOptionsPrestation(): void
    {
        $this->refreshEstimations();
    }

    public function updatedFrequence(): void
    {
        $this->refreshEstimations();
    }

    public function updatedZonesSpecifiques(): void
    {
        $this->refreshEstimations();
    }

    public function updatedPresenceAnimaux(): void
    {
        $this->refreshEstimations();
    }

    public function updatedAccesParking(): void
    {
        $this->refreshEstimations();
    }

    public function updatedMaterielFournit(): void
    {
        $this->refreshEstimations();
    }

    public function updatedMaterielSpecifique(): void
    {
        $this->refreshEstimations();
    }

    protected function chargerEmployesDisponibles(): void
    {
        $this->employesDisponibles = $this->employes
            ->map(fn($employe) => [
                'id' => $employe->id,
                'name' => $employe->name,
            ])->toArray();

        if ($this->employe_id && ! collect($this->employesDisponibles)->pluck('id')->contains($this->employe_id)) {
            $this->employe_id = null;
        }
    }

    protected function chargerCreneauxDisponibles(): void
    {
        $this->creneauxDisponibles = [];

        if (! $this->rdvDate) {
            return;
        }

        $zone = $this->currentBookableServiceZone();
        $catalog = $this->currentServiceCatalog();
        $rule = $this->currentZoneServiceRule();

        $slots = ['08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00'];

        if (! $zone || ! $catalog || ! $rule) {
            $this->creneauxDisponibles = [];
            return;
        }

        $activeStatuses = ['en_attente', 'confirme', 'en_route', 'sur_place'];
        $zoneBookingsCount = Booking::query()
            ->where('service_zone_id', $zone->id)
            ->whereDate('date', $this->rdvDate)
            ->whereIn('status', $activeStatuses)
            ->count();

        if ($zone->maximum_daily_jobs && $zoneBookingsCount >= (int) $zone->maximum_daily_jobs) {
            return;
        }

        $serviceBookingsCount = Booking::query()
            ->where('service_zone_id', $zone->id)
            ->where('service_catalog_id', $catalog->id)
            ->whereDate('date', $this->rdvDate)
            ->whereIn('status', $activeStatuses)
            ->count();

        if ($rule->maximum_daily_capacity && $serviceBookingsCount >= (int) $rule->maximum_daily_capacity) {
            return;
        }

        $countryMarket = $this->currentCountryMarketContext();
        $minimumNoticeHours = max((int) ($zone->minimum_notice_hours ?? 0), (int) ($rule->minimum_notice_hours ?? 0), $this->countryMarketResolver()->minimumNoticeHours($countryMarket));
        $timezone = config('app.timezone', 'Europe/Brussels');

        $slots = collect($slots)->filter(function ($heure) use ($minimumNoticeHours, $timezone, $zone) {
            $requestedAt = Carbon::createFromFormat('Y-m-d H:i', $this->rdvDate . ' ' . $heure, $timezone);

            if ($requestedAt->lt(now($timezone)->addHours($minimumNoticeHours))) {
                return false;
            }

            if (! $this->isPremiumClient() || ! $this->employe_id) {
                return $this->hasAnyAvailableEmployeeForSlot($this->rdvDate, $heure, $zone);
            }

            if (! $this->employeeCanCoverZone((int) $this->employe_id, (int) $zone->id)) {
                return false;
            }

            return $this->employeeIsAvailableForSlot((int) $this->employe_id, $this->rdvDate, $heure, $zone);
        })->values()->toArray();

        $this->creneauxDisponibles = $slots;
    }

    protected function hasAnyAvailableEmployeeForSlot(string $date, string $heure, ServiceZone $zone): bool
    {
        return $this->resolveBestAvailableEmployeeForSlot($date, $heure, $zone) !== null;
    }

    protected function resolveBestAvailableEmployeeForSlot(string $date, string $heure, ServiceZone $zone): ?User
    {
        return $this->employeeAvailabilityService()->resolveBestAvailableEmployeeForSlot(
            $date,
            $heure,
            $zone,
            max(30, (int) ($this->duree_estimee ?: 90)),
        );
    }

    protected function employeeCanCoverZone(int $employeeId, int $zoneId): bool
    {
        return $this->employeeAvailabilityService()->employeeCanCoverZone($employeeId, $zoneId);
    }

    protected function employeeIsAvailableForSlot(int $employeeId, string $date, string $heure, ?ServiceZone $zone = null): bool
    {
        return $this->employeeAvailabilityService()->employeeIsAvailableForSlot(
            $employeeId,
            $date,
            $heure,
            $zone,
            max(30, (int) ($this->duree_estimee ?: 90)),
        );
    }

    protected function validateBookingBusinessRules(): bool
    {
        if (! $this->ensureCoverageIsBookable()) {
            return false;
        }

        $zone = $this->currentBookableServiceZone();
        $catalog = $this->currentServiceCatalog();
        $rule = $this->currentZoneServiceRule();

        if (! $zone || ! $catalog || ! $rule || ! $this->rdvDate || ! $this->rdvHeure) {
            $this->addError('rdvDate', 'Impossible de valider ce créneau pour cette zone et ce service.');
            return false;
        }

        $countryMarket = $this->currentCountryMarketContext();

        if (! $this->countryMarketResolver()->bookingEnabled($countryMarket)) {
            $this->addError('postal_code_input', 'La réservation n’est pas encore active pour ce marché.');
            return false;
        }

        if (! $this->countryMarketResolver()->serviceEnabled($countryMarket)) {
            $this->addError('selected_service_identifier', 'Ce service n’est pas encore disponible dans ce pays.');
            return false;
        }

        $timezone = config('app.timezone', 'Europe/Brussels');
        $requestedAt = Carbon::createFromFormat('Y-m-d H:i', $this->rdvDate . ' ' . $this->rdvHeure, $timezone);

        $minimumNoticeHours = max(
            (int) ($zone->minimum_notice_hours ?? 0),
            (int) ($rule->minimum_notice_hours ?? 0),
            $this->countryMarketResolver()->minimumNoticeHours($countryMarket)
        );

        if ($this->booking_mode === 'asap') {
            if ($requestedAt->gt(now($timezone)->addHours(2))) {
                $this->addError('booking_mode', 'Le mode ASAP doit trouver un créneau dans les 2 heures.');
                return false;
            }
        } else {
            if ($requestedAt->lt(now($timezone)->addHours($minimumNoticeHours))) {
                $this->addError('rdvDate', 'Ce créneau est trop proche par rapport au délai minimum de réservation de votre zone.');
                return false;
            }
        }

        $activeStatuses = ['en_attente', 'confirme', 'en_route', 'sur_place'];

        $zoneBookingsCount = Booking::query()
            ->where('service_zone_id', $zone->id)
            ->whereDate('date', $this->rdvDate)
            ->whereIn('status', $activeStatuses)
            ->count();

        if ($zone->maximum_daily_jobs && $zoneBookingsCount >= (int) $zone->maximum_daily_jobs) {
            $this->addError('rdvDate', 'La capacité journalière maximale de cette zone est atteinte.');
            return false;
        }

        $serviceBookingsCount = Booking::query()
            ->where('service_zone_id', $zone->id)
            ->where('service_catalog_id', $catalog->id)
            ->whereDate('date', $this->rdvDate)
            ->whereIn('status', $activeStatuses)
            ->count();

        if ($rule->maximum_daily_capacity && $serviceBookingsCount >= (int) $rule->maximum_daily_capacity) {
            $this->addError('selected_service_identifier', 'La capacité maximale pour ce service est atteinte dans votre zone.');
            return false;
        }

        if ($this->isPremiumClient() && $this->employe_id) {
            if (! $this->employeeCanCoverZone((int) $this->employe_id, (int) $zone->id)) {
                $this->addError('employe_id', 'Cet employé ne couvre pas votre zone.');
                return false;
            }

            if (! $this->employeeIsAvailableForSlot((int) $this->employe_id, $this->rdvDate, $this->rdvHeure, $zone)) {
                $this->addError('rdvHeure', 'Ce créneau n’est plus disponible pour cet employé.');
                return false;
            }
        } elseif (! $this->hasAnyAvailableEmployeeForSlot($this->rdvDate, $this->rdvHeure, $zone)) {
            $this->addError('rdvHeure', 'Aucun employé disponible ne couvre cette zone pour ce créneau.');
            return false;
        }

        return true;
    }

    protected function refreshEstimations(): void
    {
        $catalog = $this->currentServiceCatalog();
        $zone = $this->currentServiceZone();
        $rule = $this->currentZoneServiceRule();

        $countryMarket = $this->currentCountryMarketContext();

        $context = [
            'service_identifier' => $this->selected_service_identifier,
            'surface' => $this->surface,
            'options_prestation' => $this->options_prestation,
            'zones_specifiques' => $this->zones_specifiques,
            'presence_animaux' => $this->presence_animaux,
            'frequence' => $this->frequence,
            'is_premium' => $this->isPremiumClient(),
            'country_price_multiplier' => $this->countryMarketResolver()->countryPriceMultiplier($countryMarket),
        ];

        $this->duree_estimee = $this->bookingEstimatorService()->estimateDuration($catalog, $context);
        $this->devis_estime = $this->bookingEstimatorService()->estimatePrice($catalog, $zone, $rule, $context);
    }

    protected function calculerDureeEstimee(): int
    {
        return $this->bookingEstimatorService()->estimateDuration($this->currentServiceCatalog(), [
            'service_identifier' => $this->selected_service_identifier,
            'surface' => $this->surface,
            'options_prestation' => $this->options_prestation,
            'zones_specifiques' => $this->zones_specifiques,
            'presence_animaux' => $this->presence_animaux,
        ]);
    }

    protected function calculerDevisEstime(): float
    {
        return $this->bookingEstimatorService()->estimatePrice(
            $this->currentServiceCatalog(),
            $this->currentServiceZone(),
            $this->currentZoneServiceRule(),
            [
                'service_identifier' => $this->selected_service_identifier,
                'surface' => $this->surface,
                'options_prestation' => $this->options_prestation,
                'zones_specifiques' => $this->zones_specifiques,
                'frequence' => $this->frequence,
                'is_premium' => $this->isPremiumClient(),
                'country_price_multiplier' => $this->countryMarketResolver()->countryPriceMultiplier($this->currentCountryMarketContext()),
            ]
        );
    }
}
