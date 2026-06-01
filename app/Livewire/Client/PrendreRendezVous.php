<?php

namespace App\Livewire\Client;

use App\Actions\Booking\CreateRecurringSeriesAction;
use App\Models\Booking;
use App\Models\BookingFavorite;
use App\Models\Trade;
use App\Services\Booking\AsapBookingService;
use App\Services\Booking\BookingEstimatorService;
use App\Services\Booking\BookingIntelligenceService;
use App\Services\Booking\CreateBookingAction;
use App\Services\Booking\EmployeeAvailabilityService;
use App\Services\Booking\PreferredProviderResolver;
use App\Services\Booking\ProviderSelectionResolver;
use App\Services\Booking\ZoneCoverageService;
use App\Services\Promotion\PromoCodeService;
use App\Services\Promotion\PromoCodeValidationContext;
use App\Support\Livewire\Concerns\Booking\HandlesBookingCreation;
use App\Support\Livewire\Concerns\Booking\HandlesPublicBookingAuthentication;
use App\Support\Livewire\Concerns\Booking\ManagesPublicBookingDraft;
use App\Support\Livewire\Concerns\HandlesBookingSubmissionFlow;
use App\Support\Livewire\Concerns\InteractsWithBookingFormState;
use App\Support\Livewire\Concerns\RendersTradeFormSchema;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

class PrendreRendezVous extends Component
{
    use HandlesBookingCreation;
    use HandlesBookingSubmissionFlow {
        updatedSelectedServiceIdentifier as protected traitUpdatedSelectedServiceIdentifier;
        rules as protected traitRules;
    }
    use HandlesPublicBookingAuthentication;
    use InteractsWithBookingFormState {
        updatedPostalCodeInput as protected traitUpdatedPostalCodeInput;
    }
    use ManagesPublicBookingDraft;
    use RendersTradeFormSchema;
    use WithFileUploads;

    private const PUBLIC_BOOKING_DRAFT_SESSION_KEY = 'booking.public_draft';

    public int $step = 1;

    /** Selected trade filter — null means "show all trades". */
    public ?int $selectedTradeId = null;

    public ?string $selected_service_identifier = null;

    public ?string $type_lieu = null;

    public ?string $frequence = null;

    public ?string $surface = null;

    public array $options_prestation = [];

    public array $zones_specifiques = [];

    public ?string $materiel_specifique = null;

    public ?string $commentaire_client = null;

    public bool $presence_animaux = false;

    public bool $acces_parking = false;

    public bool $materiel_fournit = false;

    public bool $prefilledFromLast = false;

    public bool $prefilledFromSource = false;

    public bool $prefilledFromAddress = false;

    public ?int $resolvedPostalCodeId = null;

    public ?int $resolvedServiceZoneId = null;

    public ?int $resolvedServiceCatalogId = null;

    public ?string $coverageStatus = null;

    public ?string $coverageMessage = null;

    public ?string $adresse = null;

    public ?string $ville = null;

    public ?string $code_postal = null;

    public ?string $postal_code_input = null;

    public ?string $telephone_client = null;

    public ?string $priorite = 'normale';

    public ?int $organization_site_id = null;

    public ?string $site_contact_name = null;

    public ?string $site_contact_phone = null;

    public ?string $purchase_order_reference = null;

    public ?string $cost_center = null;

    public ?string $site_instructions = null;

    public ?int $employe_id = null;

    public ?string $rdvDate = null;

    public ?string $rdvHeure = null;

    public ?string $createdReference = null;

    public ?string $createdEmployeName = null;

    public ?string $createdStatusLabel = null;

    public bool $is_recurrent = false;

    public ?string $recurrence_rule = null;

    public ?string $recurrence_frequency = null;

    public int $recurrence_interval = 1;

    public ?string $recurrence_until = null;

    public ?int $recurrence_count = null;

    public array $recurrence_days = [];

    public bool $is_favorite_slot = false;

    // SP2 Task 6 — sélection du type de prestataire côté client (3 paliers).
    /** 'independent' | 'company' | 'any' (défaut : aucun filtre, auto-match). */
    public string $providerTypePreference = 'any';

    /** Prestataire imposé (favori re-réservé, ou nouveau presta si premium). */
    public ?int $preferredProviderUserId = null;

    /** Ouvre/affiche le panneau de recherche premium (BrowseProviders). */
    public bool $showProviderPicker = false;

    /** Créneaux alternatifs proposés si le presta préféré est indisponible. */
    public array $preferredProviderAlternativeSlots = [];

    public ?string $preferredProviderMessage = null;

    public array $photos = [];

    public array $creneauxDisponibles = [];

    public array $employesDisponibles = [];

    public int $duree_estimee = 0;

    public float $devis_estime = 0;

    public ?string $promo_code = null;

    public ?float $promo_discount_preview = null;

    public ?string $promo_message = null;

    public bool $promo_valid = false;

    public function previewPromoCode(): void
    {
        $this->promo_discount_preview = null;
        $this->promo_message = null;
        $this->promo_valid = false;

        if (empty(trim((string) $this->promo_code))) {
            return;
        }

        if ((float) $this->devis_estime <= 0) {
            $this->promo_message = 'Estimation indisponible pour évaluer le code.';

            return;
        }

        $service = app(PromoCodeService::class);

        $isFirstBooking = ! Booking::query()
            ->where('client_id', Auth::id())
            ->exists();

        $context = new PromoCodeValidationContext(
            user: Auth::user(),
            bookingAmount: (float) $this->devis_estime,
            serviceCatalogId: $this->resolvedServiceCatalogId,
            serviceZoneId: $this->resolvedServiceZoneId,
            isFirstBooking: $isFirstBooking,
        );

        $result = $service->validate((string) $this->promo_code, $context);

        if ($result->valid) {
            $this->promo_valid = true;
            $this->promo_discount_preview = (float) $result->discountAmount;
            $this->promo_message = sprintf(
                'Code valide : -%.2f € (total : %.2f €)',
                $result->discountAmount,
                $result->finalAmount
            );
        } else {
            $this->promo_message = $result->reason ?? 'Code invalide.';
        }
    }

    public function clearPromoCode(): void
    {
        $this->reset(['promo_code', 'promo_discount_preview', 'promo_message', 'promo_valid']);
    }

    public string $booking_mode = 'scheduled';

    public ?string $asapMessage = null;

    public ?string $google_place_id = null;

    public ?float $destination_lat = null;

    public ?float $destination_lng = null;

    public array $address_components = [];

    public function mount(): void
    {
        $this->hydrateFromQuery();
        $this->restorePublicBookingDraft();
        $this->normalizeBookingState();

        $this->chargerEmployesDisponibles();
        $this->refreshEstimations();

        if ($this->rdvDate) {
            $this->chargerCreneauxDisponibles();
        }
    }

    protected function zoneCoverageService(): ZoneCoverageService
    {
        return app(ZoneCoverageService::class);
    }

    protected function employeeAvailabilityService(): EmployeeAvailabilityService
    {
        return app(EmployeeAvailabilityService::class);
    }

    protected function bookingEstimatorService(): BookingEstimatorService
    {
        return app(BookingEstimatorService::class);
    }

    protected function bookingCreator(): CreateBookingAction
    {
        return app(CreateBookingAction::class);
    }

    protected function recurringBookingCreator(): CreateRecurringSeriesAction
    {
        return app(CreateRecurringSeriesAction::class);
    }

    /**
     * SP2 Task 6 — résout/valide le palier de sélection prestataire avant la
     * création de la réservation et fusionne le résultat dans $data.
     *
     * - Non-premium choisissant un nouveau presta (non favori) → refus :
     *   ProviderSelectionResolver lève AuthorizationException, on pose une
     *   erreur sur le champ et on retourne null (soumission annulée).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    protected function applyProviderSelectionToBookingData(array $data): ?array
    {
        $client = Auth::user();

        if (! $client) {
            return $data;
        }

        try {
            $selection = app(ProviderSelectionResolver::class)->resolve($client, [
                'provider_type_preference' => $this->providerTypePreference,
                'preferred_provider_user_id' => $this->preferredProviderUserId,
            ]);
        } catch (AuthorizationException $e) {
            $this->addError(
                'preferredProviderUserId',
                'Le choix d’un nouveau prestataire est réservé au pack Premium. Ajoutez-le à vos favoris ou passez Premium.'
            );

            return null;
        }

        return array_merge($data, $selection);
    }

    /**
     * Re-réserver un prestataire favori : impose son user_id comme préférence.
     */
    public function rebookFavorite(int $favoriteId): void
    {
        $favorite = BookingFavorite::query()
            ->where('id', $favoriteId)
            ->where('client_user_id', Auth::id())
            ->first();

        if (! $favorite) {
            return;
        }

        $this->preferredProviderUserId = (int) $favorite->preferred_provider_user_id;
        $this->preferredProviderMessage = null;
        $this->preferredProviderAlternativeSlots = [];
    }

    /**
     * Sélection d'un nouveau prestataire (palier premium, depuis BrowseProviders
     * ou la liste embarquée). Émise/appelée à la sélection d'un presta.
     */
    public function pickPreferredProvider(int $providerUserId): void
    {
        $this->preferredProviderUserId = $providerUserId;
        $this->showProviderPicker = false;
        $this->preferredProviderMessage = null;
        $this->preferredProviderAlternativeSlots = [];
    }

    /**
     * Vide la préférence prestataire (retour au palier auto-match).
     */
    public function clearPreferredProvider(): void
    {
        $this->preferredProviderUserId = null;
        $this->preferredProviderMessage = null;
        $this->preferredProviderAlternativeSlots = [];
    }

    public function toggleProviderPicker(): void
    {
        $this->showProviderPicker = ! $this->showProviderPicker;
    }

    /**
     * SP2 Task 6 — visibilité du palier premium « choisir un NOUVEAU prestataire ».
     *
     * Aligné sur le gate AUTORITAIRE du backend (ProviderSelectionResolver), qui
     * autorise ce palier uniquement si le CustomerProfile est premium
     * (plan_type==='premium' && plan_status==='active'), particulier OU société.
     *
     * Volontairement distinct de isPremiumClient()/canChooseEmployee() (source
     * User plan + isEntreprise + admin) utilisés par d'autres features hors SP2.
     */
    public function canPickPremiumProvider(): bool
    {
        return (bool) (Auth::user()?->customerProfile?->isPremium() ?? false);
    }

    /**
     * Bouton « je suis pressé » : abandonne le presta préféré indisponible et
     * re-soumet en auto-match.
     */
    public function bookAnyAvailableProvider(): void
    {
        $this->clearPreferredProvider();
        $this->providerTypePreference = 'any';
        $this->validerRdv();
    }

    /**
     * Favoris du client connecté pour la section « Mes prestataires favoris ».
     *
     * @return Collection<int, BookingFavorite>
     */
    public function getClientFavoritesProperty()
    {
        if (! Auth::check()) {
            return collect();
        }

        return BookingFavorite::query()
            ->where('client_user_id', Auth::id())
            ->with('provider:id,name')
            ->latest('last_used_at')
            ->latest('id')
            ->get();
    }

    protected function afterBookingCreated(Booking $rendezVous): void
    {
        $this->refreshPreferredProviderAvailability($rendezVous);
    }

    /**
     * Détecte l'indisponibilité d'un presta préféré après création et expose
     * des créneaux alternatifs (filtrés des heures passées si c'est aujourd'hui).
     */
    protected function refreshPreferredProviderAvailability(Booking $rdv): void
    {
        $this->preferredProviderAlternativeSlots = [];
        $this->preferredProviderMessage = null;

        if (! $rdv->preferred_provider_user_id) {
            return;
        }

        $result = app(PreferredProviderResolver::class)->resolve($rdv);

        if ($result['status'] !== 'unavailable') {
            return;
        }

        $this->preferredProviderMessage = 'Votre prestataire préféré n’est pas disponible sur ce créneau. Voici ses prochaines disponibilités.';
        $this->preferredProviderAlternativeSlots = $this->filterFutureSlots($result['alternative_slots']);
    }

    /**
     * Filtre les créneaux déjà dépassés (heures fixes proposées pour aujourd'hui).
     *
     * @param  list<array{date:string, heure:string}>  $slots
     * @return list<array{date:string, heure:string}>
     */
    protected function filterFutureSlots(array $slots): array
    {
        $timezone = config('app.timezone', 'Europe/Brussels');
        $now = now($timezone);

        return collect($slots)
            ->filter(function (array $slot) use ($timezone, $now) {
                $at = Carbon::createFromFormat('Y-m-d H:i', $slot['date'].' '.$slot['heure'], $timezone);

                return $at !== false && $at->greaterThanOrEqualTo($now);
            })
            ->values()
            ->all();
    }

    protected function normalizeBookingState(): void
    {
        if (! filled($this->selected_service_identifier) && request()->filled('service_identifier')) {
            $this->selected_service_identifier = (string) request()->query('service_identifier');
        }

        if (($this->postal_code_input ?? '') !== '') {
            $this->code_postal = $this->postal_code_input;
        } elseif (($this->code_postal ?? '') !== '') {
            $this->postal_code_input = $this->code_postal;
        }
    }

    protected function addServiceSelectionError(string $message): void
    {
        $this->addError('selected_service_identifier', $message);
    }

    public function updatedSelectedServiceIdentifier(): void
    {
        $this->normalizeBookingState();
        $this->traitUpdatedSelectedServiceIdentifier();

        // Phase F2 — charger le schema dynamique du Trade rattaché au service
        $catalog = $this->currentServiceCatalog();
        $this->loadTradeFormSchemaForTrade($catalog?->trade_id);
    }

    /**
     * Étend les règles de validation avec celles dérivées du schema dynamique
     * du Trade sélectionné (Phase F2). Les champs cleaning legacy restent
     * actifs en parallèle (back-compat).
     */
    protected function rules(): array
    {
        return array_merge(
            $this->traitRules(),
            $this->tradeFormAnswersRules('tradeFormAnswers')
        );
    }

    /**
     * Base price pour le calcul des pricings en "percent" dans le schema.
     * Utilisé par RendersTradeFormSchema::getTradeFormPriceDeltaProperty().
     */
    protected function tradeFormBasePriceContext(): ?float
    {
        return (float) ($this->devis_estime ?? 0.0);
    }

    public function updatedPostalCodeInput(): void
    {
        $this->code_postal = $this->postal_code_input;
        $this->traitUpdatedPostalCodeInput();
    }

    public function updatedCodePostal(): void
    {
        $this->postal_code_input = $this->code_postal;
        $this->traitUpdatedPostalCodeInput();
    }

    protected function asapBookingService(): AsapBookingService
    {
        return app(AsapBookingService::class);
    }

    public function updatedBookingMode(): void
    {
        if ($this->booking_mode === 'asap') {
            $this->is_recurrent = false;
            $this->priorite = 'urgente';
            $this->resolveAsapSlot();
        }
    }

    public function resolveAsapSlot(): void
    {
        $this->asapMessage = null;

        $zone = $this->currentBookableServiceZone();

        if (! $zone) {
            $this->asapMessage = 'Entrez d’abord une adresse couverte.';

            return;
        }

        $result = $this->asapBookingService()->findBestAsapSlot(
            zone: $zone,
            estimatedDuration: max(30, (int) ($this->duree_estimee ?: 90)),
            preferredEmployeeId: $this->isPremiumClient() ? $this->employe_id : null,
            maxDelayHours: 2,
        );

        if (! $result) {
            $zone = $this->currentBookableServiceZone();
            $catalog = $this->currentServiceCatalog();

            $suggestions = [];

            if ($zone && $catalog) {
                $suggestions = app(BookingIntelligenceService::class)
                    ->suggestAlternativeSlots($zone, $catalog);
            }

            $this->rdvDate = null;
            $this->rdvHeure = null;

            $this->asapMessage = count($suggestions)
                ? 'Aucun employé disponible sous 2h. Nous vous proposons les meilleurs créneaux alternatifs.'
                : 'Aucun employé disponible sous 2h. Choisissez un créneau planifié.';

            $this->creneauxDisponibles = collect($suggestions)
                ->map(fn ($slot) => $slot['date'].' '.$slot['heure'])
                ->toArray();

            return;
        }

        $this->rdvDate = $result['date'];
        $this->rdvHeure = $result['heure'];
        $this->employe_id = $result['employee']->id;

        $this->asapMessage = 'Créneau ASAP trouvé : aujourd’hui à '.$this->rdvHeure.'.';
    }

    /**
     * Select a trade to filter the service catalog. Clears service + schema
     * when the trade changes so the user starts fresh for the new trade.
     */
    public function selectTrade(int $tradeId): void
    {
        if ($this->selectedTradeId === $tradeId) {
            return;
        }

        $this->selectedTradeId = $tradeId;
        $this->selected_service_identifier = null;
        $this->tradeFormSchema = null;
        $this->tradeFormAnswers = [];
    }

    /**
     * Clear the trade filter (show all trades / all services).
     */
    public function clearTrade(): void
    {
        $this->selectedTradeId = null;
        $this->selected_service_identifier = null;
        $this->tradeFormSchema = null;
        $this->tradeFormAnswers = [];
    }

    /**
     * Active trades with icon and short_description for the selection grid.
     *
     * @return array<int, array{id: int, name: string, icon: string, short_description: string|null, sort_order: int}>
     */
    public function getAvailableTradesProperty(): array
    {
        return Trade::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'icon', 'short_description', 'sort_order'])
            ->map(fn (Trade $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'icon' => $t->icon ?: 'briefcase',
                'short_description' => $t->short_description,
                'sort_order' => $t->sort_order,
            ])
            ->values()
            ->all();
    }

    /**
     * Services filtered by the currently selected trade.
     * Falls back to servicesGroupedByTrade when no trade is selected.
     */
    public function getServicesForSelectedTradeProperty(): array
    {
        if (! $this->selectedTradeId) {
            return $this->servicesGroupedByTrade;
        }

        $grouped = $this->servicesGroupedByTrade;

        // Find the trade name to look up in the grouped map
        $trade = Trade::find($this->selectedTradeId);
        if (! $trade) {
            return $grouped;
        }

        $filtered = $grouped[$trade->name] ?? [];

        return $filtered ? [$trade->name => $filtered] : [];
    }

    public function render(): View
    {
        return view('livewire.client.prendre-rendez-vous', [
            'surfaces' => $this->surfaces,
            'services' => $this->services,
            // Phase 1 — version groupée par métier pour rendu en <optgroup>
            'servicesGroupedByTrade' => $this->servicesGroupedByTrade,
            // Phase F4 — trades pour la grille de sélection (step 1)
            'availableTrades' => $this->availableTrades,
            'selectedTradeId' => $this->selectedTradeId,
            'servicesForSelectedTrade' => $this->servicesForSelectedTrade,
            'typesLieu' => $this->typesLieux,
            'frequences' => $this->frequences,
            'priorites' => $this->priorites,
            'optionsDisponibles' => $this->optionsDisponibles,
            'zonesDisponibles' => $this->zonesDisponibles,
            'employesDisponibles' => $this->employesDisponibles,
            'creneauxDisponibles' => $this->creneauxDisponibles,
            'recurringFrequencyOptions' => $this->recurringFrequencyOptions,
            'recurringDayOptions' => $this->recurringDayOptions,
            'selectedServiceLabel' => $this->selectedServiceLabel,
            'bookingEntryRouteName' => $this->publicBookingEntryRouteName(),
            'isGuestBooking' => ! Auth::check(),
            'googleMapsApiKey' => config('services.google_maps.key'),
        ]);
    }
}
