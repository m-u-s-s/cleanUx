<?php

namespace App\Support\Livewire\Concerns\Booking;

use App\Models\RendezVous;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

trait HandlesBookingCreation
{
    public function validerRdv(): void
    {
        if (! Auth::check()) {
            $this->queueBookingAuthenticationRedirect();

            return;
        }

        Gate::authorize('create', RendezVous::class);

        $this->normalizeBookingState();
        $this->normalizeRecurringInputs();

        $this->validate();

        if (! $this->ensureCoverageIsBookable()) {
            return;
        }

        $resolution = $this->currentCoverageResolution();

        $postal = $resolution->postalCode;
        $zone = $resolution->zone;
        $catalog = $resolution->serviceCatalog;
        $rule = $resolution->zoneServiceRule;
        $organizationSite = $this->selectedOrganizationSite();

        if (! $postal || ! $zone || ! $catalog || ! $rule) {
            $this->addError('postal_code_input', 'Impossible de finaliser la réservation pour cette zone.');

            return;
        }

        if (! $this->validateRecurringConfiguration()) {
            return;
        }

        if (! $this->is_recurrent && ! $this->validateBookingBusinessRules()) {
            return;
        }

        $photoPaths = [];
        foreach ($this->photos as $photo) {
            $photoPaths[] = $photo->store('rendezvous/references', 'public');
        }

        $bookingReference = $this->makeReference($this->is_recurrent ? 'CUXR' : 'CUX');
        $normalizedLocation = $this->normalizedBookingLocationData($postal, $organizationSite);
        $serviceIdentifier = (string) (
            $this->selected_service_identifier
            ?: $catalog->code
            ?: $catalog->slug
        );

        if ($serviceIdentifier === '') {
            throw new \LogicException('Impossible de créer un rendez-vous sans service_identifier.');
        }

        $commentaireClient = collect([
            $this->commentaire_client,
            $this->site_instructions ? 'Consignes site : ' . $this->site_instructions : null,
            $this->purchase_order_reference ? 'Référence PO : ' . $this->purchase_order_reference : null,
            $this->cost_center ? 'Centre de coût : ' . $this->cost_center : null,
        ])->filter()->implode("\n");

        $bookingData = [
            'booking_channel' => 'web',
            'booking_reference' => $bookingReference,
            'date' => $this->rdvDate,
            'heure' => $this->rdvHeure,
            'service_identifier' => $serviceIdentifier,
            'type_lieu' => $this->type_lieu,
            'frequence' => $this->frequence,
            'surface' => $this->surface,
            'motif' => $this->bookingMotifFor($catalog, $postal, $organizationSite),
            'adresse' => $normalizedLocation['adresse'],
            'ville' => $normalizedLocation['ville'],
            'code_postal' => $normalizedLocation['code_postal'],
            'telephone_client' => $this->telephone_client,
            'priorite' => $this->priorite,
            'commentaire_client' => $commentaireClient,
            'options_prestation' => $this->options_prestation,
            'zones_specifiques' => $this->zones_specifiques,
            'materiel_specifique' => $this->materiel_specifique ? [$this->materiel_specifique] : [],
            'presence_animaux' => $this->presence_animaux,
            'acces_parking' => $this->acces_parking,
            'materiel_fournit' => $this->materiel_fournit,
            'is_recurrent' => $this->is_recurrent,
            'recurrence_rule' => $this->recurringRuleLabel(),
            'recurrence_frequency' => $this->recurrence_frequency,
            'recurrence_interval' => $this->recurrence_interval,
            'recurrence_until' => $this->recurrence_until,
            'recurrence_count' => $this->recurrence_count,
            'recurrence_days' => $this->normalizedRecurrenceDays(),
            'is_favorite_slot' => $this->is_favorite_slot,
            'photos_reference' => $photoPaths,
            'duree_estimee' => $this->duree_estimee,
            'devis_estime' => $this->devis_estime,
            'status' => 'en_attente',
            'resolution_source' => $resolution->resolutionSource,
            'employe_id' => $this->employe_id,
            'corporate_context' => [
                'organization_account_id' => Auth::user()->organization_account_id,
                'organization_site_id' => $organizationSite?->id,
                'organization_site_name' => $organizationSite?->name,
                'site_contact_name' => $this->site_contact_name,
                'site_contact_phone' => $this->site_contact_phone,
                'purchase_order_reference' => $this->purchase_order_reference,
                'cost_center' => $this->cost_center,
                'site_instructions' => $this->site_instructions,
            ],
            'booking_mode' => $this->booking_mode,
            'asap_requested_at' => $this->booking_mode === 'asap' ? now() : null,
            'asap_deadline_at' => $this->booking_mode === 'asap' ? now()->addHours(2) : null,
            'matched_at' => $this->booking_mode === 'asap' ? now() : null,
            'matching_snapshot' => $this->booking_mode === 'asap' ? [
                'mode' => 'asap',
                'max_delay_minutes' => 120,
                'matched_employee_id' => $this->employe_id,
                'matched_date' => $this->rdvDate,
                'matched_time' => $this->rdvHeure,
            ] : null,
            'google_place_id' => $this->google_place_id,
            'destination_lat' => $this->destination_lat,
            'destination_lng' => $this->destination_lng,
            'address_components' => $this->address_components,
        ];

        $occurrencesCount = 1;

        if ($this->is_recurrent) {
            try {
                $series = $this->recurringBookingCreator()->execute(
                    client: Auth::user(),
                    postal: $postal,
                    zone: $zone,
                    catalog: $catalog,
                    rule: $rule,
                    data: $bookingData,
                    organizationSite: $organizationSite,
                    resolution: $resolution,
                );
            } catch (ValidationException $exception) {
                foreach ($exception->errors() as $field => $messages) {
                    foreach ($messages as $message) {
                        $this->addError($field, $message);
                    }
                }

                return;
            }

            $rendezVous = $series['master'];
            $occurrencesCount = (int) ($series['occurrences_count'] ?? 1);
        } else {
            $assignedEmployee = $this->isPremiumClient() && $this->employe_id
                ? \App\Models\User::query()->find($this->employe_id)
                : $this->resolveBestAvailableEmployeeForSlot($this->rdvDate, $this->rdvHeure, $zone);

            if (! $assignedEmployee) {
                $this->addError('rdvHeure', 'Impossible d’affecter un employé disponible pour ce créneau.');

                return;
            }

            $rendezVous = $this->bookingCreator()->execute(
                client: Auth::user(),
                postal: $postal,
                zone: $zone,
                catalog: $catalog,
                rule: $rule,
                assignedEmployee: $assignedEmployee,
                data: $bookingData,
                organizationSite: $organizationSite,
                resolution: $resolution,
            );
        }

        $rendezVous->load(['employe', 'organizationSite']);

        $manualValidationRequired = (bool) ($rule->requires_manual_validation
            || data_get($zone->metadata, 'requires_manual_validation', false)
            || $catalog->requires_manual_validation);

        $this->createdReference = $bookingReference;
        $this->createdEmployeName = $rendezVous->employe?->name;
        $this->createdStatusLabel = $this->is_recurrent
            ? 'Série créée • ' . $occurrencesCount . ' occurrence(s)'
            : ($manualValidationRequired ? 'En attente de validation' : 'En attente');

        $successMessage = $this->is_recurrent
            ? 'Votre série récurrente a bien été créée (' . $occurrencesCount . ' occurrence(s)).'
            : ($manualValidationRequired
                ? 'Votre demande a été enregistrée. Une validation manuelle est nécessaire avant confirmation.'
                : 'Votre demande a bien été envoyée.');

        $this->clearPublicBookingDraft();

        session()->flash('success', $successMessage);
        $this->dispatch('toast', $successMessage, 'success');

        $this->step = 5;
    }

    public function envoyerDemande(): void
    {
        $this->validerRdv();
    }
}
