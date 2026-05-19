<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Services\Search\AddressAutocompleteService;
use App\Services\Search\ProviderSearchCriteria;
use App\Services\Search\ProviderSearchService;
use App\Services\Search\ServiceCatalogSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SearchController extends Controller
{
    public function providers(Request $request, ProviderSearchService $service): JsonResponse
    {
        $params = $request->validate([
            'trade_id' => ['nullable', 'integer'],
            'service_catalog_id' => ['nullable', 'integer'],
            'min_rating' => ['nullable', 'numeric', 'min:1', 'max:5'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'service_zone_id' => ['nullable', 'integer'],
            'postal_code' => ['nullable', 'string', 'max:16'],
            'country_id' => ['nullable', 'integer'],
            'near_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'near_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'radius_km' => ['nullable', 'integer', 'min:1', 'max:500'],
            'online_only' => ['nullable', 'boolean'],
            'has_photo' => ['nullable', 'boolean'],
            'q' => ['nullable', 'string', 'max:80'],
            'sort' => ['nullable', 'in:rating,price_asc,price_desc,distance,popularity'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $criteria = ProviderSearchCriteria::fromArray($params);
        $paginator = $service->search($criteria);

        return response()->json([
            'data' => collect($paginator->items())->map(fn ($u) => $this->serializeProvider($u))->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function services(Request $request, ServiceCatalogSearchService $service): JsonResponse
    {
        $params = $request->validate([
            'trade_id' => ['nullable', 'integer'],
            'category' => ['nullable', 'string', 'max:32'],
            'service_type' => ['nullable', 'string', 'max:32'],
            'is_b2b_available' => ['nullable', 'boolean'],
            'is_personal_available' => ['nullable', 'boolean'],
            'min_price' => ['nullable', 'numeric'],
            'max_price' => ['nullable', 'numeric'],
            'q' => ['nullable', 'string', 'max:80'],
            'sort' => ['nullable', 'in:featured,price_asc,price_desc,name'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $paginator = $service->search($params);

        return response()->json([
            'data' => collect($paginator->items())->map(fn ($s) => [
                'id' => $s->id,
                'trade_id' => $s->trade_id,
                'name' => $s->name,
                'slug' => $s->slug,
                'code' => $s->code,
                'description' => $s->description,
                'category' => $s->category,
                'service_type' => $s->service_type,
                'base_price' => $s->base_price !== null ? (float) $s->base_price : null,
                'currency' => $s->currency,
                'billing_unit' => $s->billing_unit,
                'default_duration_minutes' => $s->default_duration_minutes,
                'is_featured' => (bool) $s->is_featured,
                'icon' => $s->icon,
                'color' => $s->color,
                'cover_image_url' => $s->cover_image_path
                    ? Storage::url($s->cover_image_path)
                    : null,
            ])->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function postalAutocomplete(Request $request, AddressAutocompleteService $service): JsonResponse
    {
        $params = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:80'],
            'country_id' => ['nullable', 'integer'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:25'],
        ]);

        $results = $service->search(
            $params['q'],
            isset($params['country_id']) ? (int) $params['country_id'] : null,
            $params['limit'] ?? 10,
        );

        return response()->json(['data' => $results->values()->all()]);
    }

    protected function serializeProvider($u): array
    {
        $profile = $u->providerProfile ?? null;

        return [
            'id' => $u->id,
            'name' => $u->name,
            'photo_url' => $profile?->photo_path ? Storage::url($profile->photo_path) : null,
            'bio' => $profile?->bio,
            'rating' => [
                'avg' => $profile?->rating_avg !== null ? (float) $profile->rating_avg : null,
                'count' => (int) ($profile?->rating_count ?? 0),
            ],
            'hourly_rate' => $profile?->hourly_rate !== null ? (float) $profile->hourly_rate : null,
            'currency' => 'EUR',
            'is_online' => (bool) ($profile?->is_online ?? false),
            'trades' => $u->trades?->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'code' => $t->code,
            ]) ?? collect(),
            'distance_km' => isset($u->distance_km) ? round((float) $u->distance_km, 2) : null,
            'profile_url' => url('/providers/' . $u->id),
        ];
    }
}
