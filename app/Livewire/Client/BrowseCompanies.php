<?php

namespace App\Livewire\Client;

use App\Enums\OrganizationType;
use App\Enums\ProviderType;
use App\Models\OrganizationAccount;
use App\Services\Booking\EligibleCompaniesResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * SP3 Task 8 — miroir de BrowseProviders pour les SOCIÉTÉS prestataires.
 *
 * @property-read Collection<int, OrganizationAccount> $companies
 */
/**
 * @property-read bool $estPremium
 * @property-read Collection<int, OrganizationAccount> $companies
 */
class BrowseCompanies extends Component
{
    public bool $selectionMode = false;

    /** Contexte de réservation optionnel (passé par le picker embarqué). */
    public ?int $serviceZoneId = null;

    public ?int $tradeId = null;

    /** SP-Polish — filtres calqués sur BrowseProviders. */
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

    public function updating(string $name): void
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

    /** SP3 Task 8 — sélection d'une société en mode embarqué : émet l'event que le composant parent écoute. */
    /** Choisir sa societe est un service Premium. La source est `User::isPremium()`. */
    #[Computed]
    public function estPremium(): bool
    {
        return (bool) Auth::user()?->isPremium();
    }

    /**
     * Ouvre le parcours de commande avec cette societe en PREFERENCE. Le catalogue, le prix et
     * le dispatch ne changent pas.
     */
    public function reserverAvecLaSociete(int $organizationId): void
    {
        // Une methode Livewire est une porte HTTP a part entiere : la garde vit ici.
        abort_unless($this->estPremium && $this->estUneSocietePrestataire($organizationId), 403);

        $this->redirect(route('client.rendezvous.create', ['societe' => $organizationId]), navigate: true);
    }

    /** Un identifiant venu du navigateur ne designe pas forcement une societe prestataire. */
    private function estUneSocietePrestataire(int $organizationId): bool
    {
        return OrganizationAccount::query()
            ->whereKey($organizationId)
            ->where('type', OrganizationType::PROVIDER_COMPANY->value)
            ->exists();
    }

    public function selectCompany(int $organizationId): void
    {
        if ($this->selectionMode) {
            $this->dispatch('companySelected', organizationId: $organizationId);
        }
    }

    /**
     * Liste des sociétés à présenter.
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
                    $q->whereIn('provider_type', ProviderType::valeursDeSociete())
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
