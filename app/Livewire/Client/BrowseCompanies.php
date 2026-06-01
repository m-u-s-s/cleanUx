<?php

namespace App\Livewire\Client;

use App\Enums\OrganizationType;
use App\Models\OrganizationAccount;
use App\Services\Booking\EligibleCompaniesResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
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

    /**
     * SP-Polish — filtres calqués sur BrowseProviders. Recherche par nom,
     * note minimale et tri ; appliqués sur la collection retournée par
     * getCompaniesProperty sans changer son type (Collection Eloquent).
     */
    #[Url(as: 'q')]
    public string $query = '';

    #[Url(as: 'rating')]
    public ?float $minRating = null;

    #[Url(as: 'sort')]
    public string $sort = 'rating'; // rating | providers | name

    public function mount(bool $selectionMode = false, ?int $serviceZoneId = null, ?int $tradeId = null): void
    {
        $this->selectionMode = $selectionMode;
        $this->serviceZoneId = $serviceZoneId;
        $this->tradeId = $tradeId;
    }

    public function updating($name): void
    {
        if (in_array($name, ['query', 'minRating', 'sort'], true) && method_exists($this, 'resetPage')) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['query', 'minRating']);
        $this->sort = 'rating';
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
            $base = app(EligibleCompaniesResolver::class)
                ->forContext($this->serviceZoneId, $this->tradeId);
        } else {
            $base = OrganizationAccount::query()
                ->where('type', OrganizationType::PROVIDER_COMPANY->value)
                ->whereNotNull('rating_avg')
                ->withCount(['providerProfiles as providers_count' => function ($q) {
                    $q->where('provider_type', 'company_worker')
                        ->where('status', 'active')
                        ->where('verification_status', 'verified');
                }])
                ->orderByDesc('rating_avg')
                ->orderBy('name')
                ->limit(20)
                ->get();
        }

        return $base
            ->when($this->query !== '', fn (Collection $c): Collection => $c->filter(
                fn (OrganizationAccount $org): bool => str_contains(
                    mb_strtolower((string) $org->name),
                    mb_strtolower($this->query)
                )
            ))
            ->when($this->minRating !== null, fn (Collection $c): Collection => $c->filter(
                fn (OrganizationAccount $org): bool => (float) ($org->rating_avg ?? 0) >= $this->minRating
            ))
            ->sortBy(fn (OrganizationAccount $org): float|string => match ($this->sort) {
                'name' => mb_strtolower((string) $org->name),
                'providers' => -1 * (int) ($org->providers_count ?? 0),
                default => -1 * (float) ($org->rating_avg ?? 0), // rating desc
            })
            ->values();
    }

    public function render(): View
    {
        return view('livewire.client.browse-companies', [
            'companies' => $this->companies,
        ]);
    }
}
