<?php

namespace App\Livewire\Client;

use App\Models\Trade;
use App\Models\User;
use App\Services\Search\AddressAutocompleteService;
use App\Services\Search\ProviderSearchCriteria;
use App\Services\Search\ProviderSearchService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * @property-read bool $estPremium
 * @property-read array<int, int> $preferes
 */
class BrowseProviders extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    #[Url(as: 'q')]
    public string $query = '';

    #[Url(as: 'trade')]
    public ?int $tradeId = null;

    #[Url(as: 'rating')]
    public ?int $minRating = null;

    #[Url(as: 'min_price')]
    public ?float $minPrice = null;

    #[Url(as: 'max_price')]
    public ?float $maxPrice = null;

    #[Url(as: 'postal')]
    public string $postalCode = '';

    #[Url(as: 'sort')]
    public string $sort = 'rating';

    public bool $onlineOnly = false;

    public bool $hasPhotoOnly = false;

    /** LE FILTRE VENU DE LA PAGE DES FAVORIS, supprimee : ne montrer que mes preferes. */
    #[Url(as: 'preferes')]
    public bool $seulementPreferes = false;

    public string $postalSearch = '';

    public array $postalSuggestions = [];

    /** SP2 Task 6 — quand embarqué dans le picker premium du formulaire de réservation, chaque carte expose un bouton « Choisir » qui émet l'event `providerSelected` (capté par le parcours de commande) au lieu de naviguer. */
    public bool $selectionMode = false;

    public function mount(bool $selectionMode = false): void
    {
        $this->selectionMode = $selectionMode;
    }

    /** SP2 Task 6 — sélection d'un prestataire en mode embarqué : émet l'event que le composant parent écoute. */
    public function selectProvider(int $providerId): void
    {
        if ($this->selectionMode) {
            $this->dispatch('providerSelected', providerId: $providerId);
        }
    }

    /** Choisir son prestataire est un service Premium. La source est `User::isPremium()`. */
    #[Computed]
    public function estPremium(): bool
    {
        return (bool) Auth::user()?->isPremium();
    }

    /** @return array<int, int> Les prestataires deja preferes, pour marquer les cartes. */
    #[Computed]
    public function preferes(): array
    {
        $client = Auth::user();

        if (! $client || ! $client->isPremium()) {
            return [];
        }

        return $client->favoriteEmployes()->pluck('users.id')->all();
    }

    /**
     * Ouvre le parcours de commande avec ce prestataire en PREFERENCE. Le catalogue, le prix et
     * le dispatch ne changent pas : le parcours l'ecarte lui-meme s'il n'est pas eligible.
     */
    public function reserverAvec(int $providerId): void
    {
        // Une methode Livewire est une porte HTTP a part entiere : la garde vit ici.
        abort_unless($this->estPremium && $this->estUnPrestataire($providerId), 403);

        $this->redirect(route('client.rendezvous.create', ['prestataire' => $providerId]), navigate: true);
    }

    /** Ajoute ou retire ce prestataire des preferes du client. */
    public function basculerPrefere(int $providerId): void
    {
        abort_unless($this->estPremium && $this->estUnPrestataire($providerId), 403);

        $client = Auth::user();

        if ($client->favoriteEmployes()->where('users.id', $providerId)->exists()) {
            $client->favoriteEmployes()->detach($providerId);
            $this->dispatch('toast', message: __('Retiré de vos préférés.'), type: 'success');
        } else {
            $client->favoriteEmployes()->attach($providerId, ['is_favorite' => true]);
            $this->dispatch('toast', message: __('Ajouté à vos préférés.'), type: 'success');
        }

        unset($this->preferes);
    }

    /** Un identifiant venu du navigateur ne designe pas forcement un prestataire. */
    private function estUnPrestataire(int $providerId): bool
    {
        return User::query()->providers()->whereKey($providerId)->exists();
    }

    public function updatedPostalSearch(string $value): void
    {
        if (mb_strlen($value) < 2) {
            $this->postalSuggestions = [];

            return;
        }

        $this->postalSuggestions = app(AddressAutocompleteService::class)
            ->search($value, null, 6)
            ->toArray();
    }

    public function pickPostal(string $code, string $cityName): void
    {
        $this->postalCode = $code;
        $this->postalSearch = $code.' '.$cityName;
        $this->postalSuggestions = [];
        $this->resetPage();
    }

    public function clearPostal(): void
    {
        $this->postalCode = '';
        $this->postalSearch = '';
        $this->resetPage();
    }

    public function updating($name): void
    {
        if (in_array($name, ['query', 'tradeId', 'minRating', 'minPrice', 'maxPrice', 'sort', 'onlineOnly', 'hasPhotoOnly'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['query', 'tradeId', 'minRating', 'minPrice', 'maxPrice', 'postalCode', 'postalSearch', 'sort', 'onlineOnly', 'hasPhotoOnly', 'seulementPreferes']);
        $this->sort = 'rating';
        $this->resetPage();
    }

    public function render(): View
    {
        $criteria = new ProviderSearchCriteria(
            tradeId: $this->tradeId ?: null,
            minRating: $this->minRating ?: null,
            minPrice: $this->minPrice ?: null,
            maxPrice: $this->maxPrice ?: null,
            postalCode: $this->postalCode ?: null,
            onlineOnly: $this->onlineOnly,
            hasPhotoOnly: $this->hasPhotoOnly,
            query: $this->query ?: null,
            sort: $this->sort,
            page: $this->getPage(),
            perPage: 12,
            // Le filtre n'existe que pour un premium : force par le navigateur, il reste sans effet.
            onlyIds: $this->seulementPreferes && $this->estPremium ? $this->preferes : null,
        );

        $results = app(ProviderSearchService::class)->search($criteria);

        $trades = Trade::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return view('livewire.client.browse-providers', [
            'results' => $results,
            'trades' => $trades,
        ]);
    }
}
