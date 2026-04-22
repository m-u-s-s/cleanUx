<?php

namespace App\Livewire\Client;

use App\Actions\Booking\CreateRecurringSeriesAction;
use App\Models\RendezVous;
use App\Services\Booking\BookingEstimatorService;
use App\Services\Booking\CreateBookingAction;
use App\Services\Booking\EmployeeAvailabilityService;
use App\Services\Booking\ZoneCoverageService;
use App\Support\Livewire\Concerns\Booking\HandlesBookingCreation;
use App\Support\Livewire\Concerns\Booking\HandlesPublicBookingAuthentication;
use App\Support\Livewire\Concerns\Booking\ManagesPublicBookingDraft;
use App\Support\Livewire\Concerns\HandlesBookingSubmissionFlow;
use App\Support\Livewire\Concerns\InteractsWithBookingFormState;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

class PrendreRendezVous extends Component
{
    use HandlesBookingSubmissionFlow {
        updatedSelectedServiceIdentifier as protected traitUpdatedSelectedServiceIdentifier;
    }
    use InteractsWithBookingFormState {
        updatedPostalCodeInput as protected traitUpdatedPostalCodeInput;
    }
    use ManagesPublicBookingDraft;
    use HandlesPublicBookingAuthentication;
    use HandlesBookingCreation;
    use WithFileUploads;

    private const PUBLIC_BOOKING_DRAFT_SESSION_KEY = 'booking.public_draft';

    public int $step = 1;

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

    public array $photos = [];
    public array $creneauxDisponibles = [];
    public array $employesDisponibles = [];

    public int $duree_estimee = 0;
    public float $devis_estime = 0;

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

        public function render()
        {
            return view('livewire.client.prendre-rendez-vous', [
                'surfaces' => $this->surfaces,
                'services' => $this->services,
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
            ])->layout('layouts.app');
        }

}
