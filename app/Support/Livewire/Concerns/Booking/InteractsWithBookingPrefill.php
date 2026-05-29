<?php

namespace App\Support\Livewire\Concerns\Booking;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

trait InteractsWithBookingPrefill
{
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

    public function getHasPrefillProperty(): bool
    {
        return $this->prefilledFromLast
            || $this->prefilledFromSource
            || $this->prefilledFromAddress;
    }
}
