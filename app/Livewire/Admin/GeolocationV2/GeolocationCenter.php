<?php

namespace App\Livewire\Admin\GeolocationV2;

use App\Models\AddressLookup;
use App\Models\DistanceCalculation;
use App\Models\GeocodingCacheEntry;
use App\Services\GeolocationV2\GeocodingService;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class GeolocationCenter extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public string $tab = 'lookups';   // lookups | geocodings | distances

    public function purgeCache(): void
    {
        $purged = app(GeocodingService::class)->purgeExpired();
        $this->dispatch('toast', 'Cache purgé : ' . array_sum($purged) . ' lignes.', 'success');
    }

    public function render(): View
    {
        $kpis = [
            'provider' => (string) config('geolocation_v2.provider', 'mock'),
            'lookups_total' => AddressLookup::count(),
            'geocodings_total' => GeocodingCacheEntry::count(),
            'distances_total' => DistanceCalculation::count(),
            'haversine_fallback_count' => DistanceCalculation::query()
                ->where('is_fallback_haversine', true)->count(),
        ];

        if ($this->tab === 'lookups') {
            $items = AddressLookup::query()
                ->orderByDesc('queried_at')
                ->paginate(25);
        } elseif ($this->tab === 'geocodings') {
            $items = GeocodingCacheEntry::query()
                ->orderByDesc('created_at')
                ->paginate(25);
        } else {
            $items = DistanceCalculation::query()
                ->orderByDesc('created_at')
                ->paginate(25);
        }

        return view('livewire.admin.geolocation-v2.geolocation-center', [
            'kpis' => $kpis,
            'items' => $items,
        ]);
    }
}
