<?php

namespace App\Livewire\Client;

use App\Models\Trade;
use App\Services\Search\AddressAutocompleteService;
use App\Services\Search\ProviderSearchCriteria;
use App\Services\Search\ProviderSearchService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

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

    public string $postalSearch = '';
    public array $postalSuggestions = [];

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
        $this->postalSearch = $code . ' ' . $cityName;
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
        $this->reset(['query', 'tradeId', 'minRating', 'minPrice', 'maxPrice', 'postalCode', 'postalSearch', 'sort', 'onlineOnly', 'hasPhotoOnly']);
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
