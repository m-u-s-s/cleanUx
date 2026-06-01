<?php

namespace App\Livewire\Client;

use App\Enums\OrganizationType;
use App\Models\OrganizationAccount;
use App\Services\Booking\EligibleCompaniesResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * SP3 Task 8 — miroir de BrowseProviders pour les SOCIÉTÉS prestataires.
 *
 * Embarqué dans le picker premium du formulaire de réservation : chaque carte
 * société expose un bouton « Choisir » qui émet l'event `companySelected`
 * (capté par PrendreRendezVous) au lieu de naviguer.
 *
 * La frontière de sécurité reste le ProviderSelectionResolver côté backend
 * (gate premium + validation d'éligibilité zone/métier).
 *
 * @property-read Collection<int, OrganizationAccount> $companies
 */
class BrowseCompanies extends Component
{
    public bool $selectionMode = false;

    /** Contexte de réservation optionnel (passé par le picker embarqué). */
    public ?int $serviceZoneId = null;

    public ?int $tradeId = null;

    public function mount(bool $selectionMode = false, ?int $serviceZoneId = null, ?int $tradeId = null): void
    {
        $this->selectionMode = $selectionMode;
        $this->serviceZoneId = $serviceZoneId;
        $this->tradeId = $tradeId;
    }

    /**
     * SP3 Task 8 — sélection d'une société en mode embarqué : émet l'event que
     * le composant parent (PrendreRendezVous) écoute. No-op hors selection mode.
     */
    public function selectCompany(int $organizationId): void
    {
        if ($this->selectionMode) {
            $this->dispatch('companySelected', organizationId: $organizationId);
        }
    }

    /**
     * Liste des sociétés à présenter. Lorsqu'un contexte zone est connu, on
     * délègue à EligibleCompaniesResolver (sociétés réellement éligibles pour
     * cette zone + métier) ; sinon fallback simple par note décroissante.
     *
     * @return Collection<int, OrganizationAccount>
     */
    public function getCompaniesProperty(): Collection
    {
        if ($this->serviceZoneId) {
            return app(EligibleCompaniesResolver::class)
                ->forContext($this->serviceZoneId, $this->tradeId);
        }

        return OrganizationAccount::query()
            ->where('type', OrganizationType::PROVIDER_COMPANY->value)
            ->whereNotNull('rating_avg')
            ->orderByDesc('rating_avg')
            ->orderBy('name')
            ->limit(20)
            ->get();
    }

    public function render(): View
    {
        return view('livewire.client.browse-companies', [
            'companies' => $this->companies,
        ]);
    }
}
