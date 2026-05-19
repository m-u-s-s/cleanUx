<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AddressLookup;
use App\Models\DistanceCalculation;
use App\Models\GeocodingCacheEntry;
use App\Services\GeolocationV2\GeocodingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeolocationV2Controller extends Controller
{
    public function __construct(protected GeocodingService $svc) {}

    public function autocomplete(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:191'],
            'country' => ['nullable', 'string', 'size:2'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $suggestions = $this->svc->autocomplete(
            $data['q'],
            $data['country'] ?? null,
            $data['limit'] ?? null,
        );

        return response()->json([
            'data' => array_map(fn ($s) => $s->toArray(), $suggestions),
            'provider' => config('geolocation_v2.provider'),
        ]);
    }

    public function geocode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'address' => ['required', 'string', 'max:500'],
            'country' => ['nullable', 'string', 'size:2'],
        ]);

        $result = $this->svc->geocode($data['address'], $data['country'] ?? null);
        if (! $result) {
            return response()->json(['ok' => false, 'error' => 'not_found'], 404);
        }
        return response()->json(['ok' => true, 'data' => $result->toArray()]);
    }

    public function reverse(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $result = $this->svc->reverseGeocode((float) $data['lat'], (float) $data['lng']);
        if (! $result) {
            return response()->json(['ok' => false, 'error' => 'not_found'], 404);
        }
        return response()->json(['ok' => true, 'data' => $result->toArray()]);
    }

    public function distance(Request $request): JsonResponse
    {
        $data = $request->validate([
            'origin_lat' => ['required', 'numeric', 'between:-90,90'],
            'origin_lng' => ['required', 'numeric', 'between:-180,180'],
            'dest_lat' => ['required', 'numeric', 'between:-90,90'],
            'dest_lng' => ['required', 'numeric', 'between:-180,180'],
            'mode' => ['nullable', 'string', 'in:driving,walking,bicycling,transit'],
        ]);

        $result = $this->svc->distance(
            (float) $data['origin_lat'],
            (float) $data['origin_lng'],
            (float) $data['dest_lat'],
            (float) $data['dest_lng'],
            $data['mode'] ?? null,
        );
        return response()->json(['ok' => true, 'data' => $result->toArray()]);
    }

    public function adminLookups(Request $request): JsonResponse
    {
        $rows = AddressLookup::query()
            ->orderByDesc('queried_at')
            ->limit((int) $request->integer('limit', 50))
            ->get();
        return response()->json(['data' => $rows]);
    }

    public function adminStats(): JsonResponse
    {
        return response()->json([
            'provider' => config('geolocation_v2.provider'),
            'cache' => [
                'address_lookups' => AddressLookup::count(),
                'geocoding_results' => GeocodingCacheEntry::count(),
                'distance_calculations' => DistanceCalculation::count(),
            ],
            'distance_haversine_fallback_count' => DistanceCalculation::query()
                ->where('is_fallback_haversine', true)->count(),
        ]);
    }

    public function adminPurgeCache(Request $request): JsonResponse
    {
        $purged = $this->svc->purgeExpired();
        return response()->json(['ok' => true, 'purged' => $purged]);
    }
}
