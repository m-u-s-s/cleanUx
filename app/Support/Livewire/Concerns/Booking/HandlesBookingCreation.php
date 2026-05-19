<?php

namespace App\Support\Livewire\Concerns\Booking;

use App\Models\Booking;
use App\Models\OrganizationSite;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

trait HandlesBookingCreation
{
    public function validerRdv(): void
    {
        if (! Auth::check()) {
            $this->queueBookingAuthenticationRedirect();

            return;
        }

        Gate::authorize('create', Booking::class);

        $this->normalizeBookingState();
        $this->normalizeRecurringInputs();

        if (! $this->syncLocationFromSelectedOrganizationSite(Auth::user())) {
            return;
        }

        $this->validate();

        if (! $this->ensureCoverageIsBookable()) {
            return;
        }

        $resolution = $this->currentCoverageResolution();

        $postal = $resolution->postalCode;
        $zone = $resolution->zone;
        $catalog = $resolution->serviceCatalog;
        $rule = $resolution->zoneServiceRule;

        $organizationSite = $this->resolveOrganizationSiteForBooking();

        if ($organizationSite) {
            $this->applyResolvedOrganizationSiteToState($organizationSite);
        }

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

        $organizationAccountId = $organizationSite?->organization_account_id
            ?? Auth::user()?->organization_account_id;

        $commentaireClient = collect([
            $this->commentaire_client,
            $this->site_instructions ? 'Consignes site : ' . $this->site_instructions : null,
            $this->purchase_order_reference ? 'Référence PO : ' . $this->purchase_order_reference : null,
            $this->cost_center ? 'Centre de coût : ' . $this->cost_center : null,
        ])->filter()->implode("\n");

        $bookingData = [
            'booking_channel' => 'web',
            'booking_reference' => $bookingReference,

            'organization_account_id' => $organizationAccountId,
            'organization_site_id' => $organizationSite?->id,
            'service_zone_id' => $zone->id,
            'postal_code_id' => $postal->id,

            'date' => $this->rdvDate,
            'heure' => $this->rdvHeure,

            'service_identifier' => $serviceIdentifier,
            'service_catalog_id' => $catalog->id,

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
                'organization_account_id' => $organizationAccountId,
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

            // Phase F2 — réponses au schema dynamique du Trade (null si schema absent)
            'trade_form_answers' => (property_exists($this, 'tradeFormAnswers') && ! empty($this->tradeFormAnswers))
                ? $this->tradeFormAnswers
                : null,

            // Code promo saisi côté UI (validation/application déléguée à BookingPromoCodeApplier)
            'promo_code' => property_exists($this, 'promo_code') && filled($this->promo_code)
                ? (string) $this->promo_code
                : null,
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
                ? User::query()->find($this->employe_id)
                : $this->resolveBestAvailableEmployeeForSlot($this->rdvDate, $this->rdvHeure, $zone);

            if (! $assignedEmployee) {
                $this->addError('rdvHeure', 'Impossible d’affecter un employé disponible pour ce créneau.');

                return;
            }

            try {
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
            } catch (ValidationException $exception) {
                foreach ($exception->errors() as $field => $messages) {
                    foreach ($messages as $message) {
                        $this->addError($field, $message);
                    }
                }

                return;
            }
        }

        $rendezVous->load(['employe', 'organizationSite']);

        $manualValidationRequired = (bool) (
            $rule->requires_manual_validation
            || data_get($zone->metadata, 'requires_manual_validation', false)
            || $catalog->requires_manual_validation
        );

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

    protected function syncLocationFromSelectedOrganizationSite(?User $client = null): bool
    {
        $this->resetErrorBag(['organization_site_id', 'postal_code_input']);

        if (blank($this->organization_site_id)) {
            return true;
        }

        $client ??= Auth::user();

        $site = OrganizationSite::query()->find((int) $this->organization_site_id);

        if (! $site || ! $client || ! $this->userCanUseOrganizationSite($client, $site)) {
            $this->addError('organization_site_id', 'Le site sélectionné ne fait pas partie de votre organisation.');
            $this->addError('postal_code_input', 'Le site sélectionné ne fait pas partie de votre organisation.');

            return false;
        }

        $this->applyResolvedOrganizationSiteToState($site);

        $this->resetErrorBag(['organization_site_id', 'postal_code_input']);

        return true;
    }

    protected function resolveOrganizationSiteForBooking(): ?OrganizationSite
    {
        if (blank($this->organization_site_id)) {
            return null;
        }

        return OrganizationSite::query()->find((int) $this->organization_site_id);
    }

    protected function applyResolvedOrganizationSiteToState(OrganizationSite $site): void
    {
        $postalCode = $site->postal_code
            ?? $site->code_postal
            ?? $site->zip
            ?? null;

        $city = $site->city
            ?? $site->ville
            ?? null;

        $address = $site->address_line_1
            ?? $site->address
            ?? $site->adresse
            ?? null;

        if ($postalCode) {
            $this->postal_code_input = (string) $postalCode;

            if (property_exists($this, 'code_postal')) {
                $this->code_postal = (string) $postalCode;
            }
        }

        if ($city) {
            $this->ville = (string) $city;
        }

        if ($address) {
            $this->adresse = (string) $address;
        }

        $this->organization_site_id = (int) $site->id;

        if (property_exists($this, 'organization_account_id')) {
            $this->organization_account_id = (int) $site->organization_account_id;
        }

        if (property_exists($this, 'site_contact_name') && blank($this->site_contact_name)) {
            $this->site_contact_name = $site->contact_name;
        }

        if (property_exists($this, 'site_contact_phone') && blank($this->site_contact_phone)) {
            $this->site_contact_phone = $site->phone;
        }

        if (property_exists($this, 'site_instructions') && blank($this->site_instructions)) {
            $this->site_instructions = $site->access_instructions;
        }
    }

    private function userCanUseOrganizationSite($user, OrganizationSite $site): bool
    {
        if (! $user) {
            return false;
        }

        $siteId = (int) $site->getKey();

        $siteOrgId = $site->getRawOriginal('organization_account_id')
            ?? $site->organization_account_id
            ?? null;

        $siteClientUserId = $site->getRawOriginal('client_user_id')
            ?? $site->client_user_id
            ?? null;

        if ($siteClientUserId && (int) $siteClientUserId === (int) $user->id) {
            return true;
        }

        $userOrgId = $user->getRawOriginal('organization_account_id')
            ?? $user->organization_account_id
            ?? data_get($user->metadata, 'organization_account_id')
            ?? data_get($user->metadata, 'entreprise_context.organization_account_id')
            ?? null;

        if (! $userOrgId && $user->id && Schema::hasTable('users') && Schema::hasColumn('users', 'organization_account_id')) {
            $userOrgId = DB::table('users')
                ->where('id', $user->id)
                ->value('organization_account_id');
        }

        if ($userOrgId && $siteOrgId && (int) $userOrgId === (int) $siteOrgId) {
            return true;
        }

        if ($this->userIsMemberOfOrganization($user, $siteOrgId)) {
            return true;
        }

        $allowedSiteIds = data_get($user->metadata, 'entreprise_context.allowed_site_ids')
            ?? data_get($user->metadata, 'allowed_site_ids')
            ?? data_get($user->metadata, 'site_ids')
            ?? [];

        if (is_string($allowedSiteIds)) {
            $decoded = json_decode($allowedSiteIds, true);
            $allowedSiteIds = is_array($decoded) ? $decoded : [];
        }

        $allowedSiteIds = collect($allowedSiteIds)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        return in_array($siteId, $allowedSiteIds, true);
    }

    private function userIsMemberOfOrganization($user, $organizationAccountId): bool
    {
        if (! $user || ! $organizationAccountId || ! Schema::hasTable('organization_members')) {
            return false;
        }

        $query = DB::table('organization_members');

        if (Schema::hasColumn('organization_members', 'user_id')) {
            $query->where('user_id', $user->id);
        } elseif (Schema::hasColumn('organization_members', 'member_user_id')) {
            $query->where('member_user_id', $user->id);
        } else {
            return false;
        }

        if (Schema::hasColumn('organization_members', 'organization_account_id')) {
            $query->where('organization_account_id', $organizationAccountId);
        } elseif (Schema::hasColumn('organization_members', 'organization_id')) {
            $query->where('organization_id', $organizationAccountId);
        } else {
            return false;
        }

        if (Schema::hasColumn('organization_members', 'is_active')) {
            $query->where('is_active', true);
        }

        if (Schema::hasColumn('organization_members', 'status')) {
            $query->whereIn('status', ['active', 'accepted', 'enabled']);
        }

        return $query->exists();
    }
}