<?php

namespace App\Services\Search;

use App\Models\PostalCode;
use App\Models\ProviderProfile;
use App\Models\ServiceZone;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class ProviderSearchService
{
    public function search(ProviderSearchCriteria $criteria): LengthAwarePaginator
    {
        $query = User::query()
            ->select(['users.id', 'users.name', 'users.email', 'users.created_at'])
            ->join('provider_profiles', 'provider_profiles.user_id', '=', 'users.id')
            ->where('provider_profiles.status', 'active')
            ->where('provider_profiles.verification_status', 'verified')
            ->with(['providerProfile', 'trades:id,name,code']);

        $this->applyRatingFilter($query, $criteria);
        $this->applyPriceFilter($query, $criteria);
        $this->applyTradeFilter($query, $criteria);
        $this->applyZoneFilter($query, $criteria);
        $this->applyPostalCodeFilter($query, $criteria);
        $this->applyOnlineFilter($query, $criteria);
        $this->applyHasPhotoFilter($query, $criteria);
        $this->applyTextSearch($query, $criteria);
        $this->applyDistanceFilter($query, $criteria);

        $this->applySort($query, $criteria);

        $query->addSelect([
            'provider_profiles.rating_avg as profile_rating_avg',
            'provider_profiles.rating_count as profile_rating_count',
            'provider_profiles.hourly_rate as profile_hourly_rate',
            'provider_profiles.is_online as profile_is_online',
            'provider_profiles.bio as profile_bio',
            'provider_profiles.photo_path as profile_photo_path',
        ]);

        return $query->paginate(perPage: $criteria->perPage, page: $criteria->page);
    }

    protected function applyRatingFilter(Builder $query, ProviderSearchCriteria $criteria): void
    {
        if ($criteria->minRating !== null) {
            $query->where('provider_profiles.rating_avg', '>=', $criteria->minRating);
        }
    }

    protected function applyPriceFilter(Builder $query, ProviderSearchCriteria $criteria): void
    {
        if ($criteria->minPrice !== null) {
            $query->where('provider_profiles.hourly_rate', '>=', $criteria->minPrice);
        }
        if ($criteria->maxPrice !== null) {
            $query->where('provider_profiles.hourly_rate', '<=', $criteria->maxPrice);
        }
    }

    protected function applyTradeFilter(Builder $query, ProviderSearchCriteria $criteria): void
    {
        if (! $criteria->tradeId) {
            return;
        }
        if (! Schema::hasTable('trade_user')) {
            return;
        }

        $tradeId = $criteria->tradeId;
        $query->whereExists(function ($sub) use ($tradeId) {
            $sub->selectRaw('1')
                ->from('trade_user')
                ->whereColumn('trade_user.user_id', 'users.id')
                ->where('trade_user.trade_id', $tradeId);
        });
    }

    protected function applyZoneFilter(Builder $query, ProviderSearchCriteria $criteria): void
    {
        if ($criteria->serviceZoneId === null) {
            return;
        }

        $zoneId = $criteria->serviceZoneId;
        $query->where(function ($sub) use ($zoneId) {
            $sub->where('users.primary_service_zone_id', $zoneId);

            if (Schema::hasTable('employee_zone_assignments')) {
                $sub->orWhereExists(function ($inner) use ($zoneId) {
                    $inner->selectRaw('1')
                        ->from('employee_zone_assignments')
                        ->whereColumn('employee_zone_assignments.user_id', 'users.id')
                        ->where('employee_zone_assignments.service_zone_id', $zoneId)
                        ->where('employee_zone_assignments.is_active', true);
                });
            }
        });
    }

    protected function applyPostalCodeFilter(Builder $query, ProviderSearchCriteria $criteria): void
    {
        if (! $criteria->postalCode) {
            return;
        }

        $postal = PostalCode::query()
            ->where('code', $criteria->postalCode)
            ->where('is_active', true)
            ->first();

        if (! $postal || ! $postal->service_zone_id) {
            $query->whereRaw('1 = 0');
            return;
        }

        $zoneId = (int) $postal->service_zone_id;
        $query->where(function ($sub) use ($zoneId) {
            $sub->where('users.primary_service_zone_id', $zoneId);

            if (Schema::hasTable('employee_zone_assignments')) {
                $sub->orWhereExists(function ($inner) use ($zoneId) {
                    $inner->selectRaw('1')
                        ->from('employee_zone_assignments')
                        ->whereColumn('employee_zone_assignments.user_id', 'users.id')
                        ->where('employee_zone_assignments.service_zone_id', $zoneId)
                        ->where('employee_zone_assignments.is_active', true);
                });
            }
        });
    }

    protected function applyOnlineFilter(Builder $query, ProviderSearchCriteria $criteria): void
    {
        if ($criteria->onlineOnly) {
            $query->where('provider_profiles.is_online', true);
        }
    }

    protected function applyHasPhotoFilter(Builder $query, ProviderSearchCriteria $criteria): void
    {
        if ($criteria->hasPhotoOnly) {
            $query->whereNotNull('provider_profiles.photo_path');
        }
    }

    protected function applyTextSearch(Builder $query, ProviderSearchCriteria $criteria): void
    {
        if (! $criteria->query) {
            return;
        }
        $term = '%' . $criteria->query . '%';
        $query->where(function ($sub) use ($term) {
            $sub->where('users.name', 'like', $term)
                ->orWhere('provider_profiles.bio', 'like', $term);
        });
    }

    protected function applyDistanceFilter(Builder $query, ProviderSearchCriteria $criteria): void
    {
        if ($criteria->nearLat === null || $criteria->nearLng === null) {
            return;
        }

        $lat = (float) $criteria->nearLat;
        $lng = (float) $criteria->nearLng;
        $radius = $criteria->radiusKm ?? 50;

        // Haversine approximation (km) — colonne current_lat/current_lng
        $haversine = "(6371 * acos(
            cos(radians(?)) * cos(radians(provider_profiles.current_lat)) *
            cos(radians(provider_profiles.current_lng) - radians(?)) +
            sin(radians(?)) * sin(radians(provider_profiles.current_lat))
        ))";

        $query
            ->whereNotNull('provider_profiles.current_lat')
            ->whereNotNull('provider_profiles.current_lng')
            ->addSelect(\Illuminate\Support\Facades\DB::raw("$haversine AS distance_km"))
            ->whereRaw("$haversine <= ?", [$lat, $lng, $lat, $radius])
            ->addBinding([$lat, $lng, $lat], 'select');
    }

    protected function applySort(Builder $query, ProviderSearchCriteria $criteria): void
    {
        match ($criteria->sort) {
            'price_asc' => $query->orderByRaw('provider_profiles.hourly_rate IS NULL')->orderBy('provider_profiles.hourly_rate', 'asc'),
            'price_desc' => $query->orderByRaw('provider_profiles.hourly_rate IS NULL')->orderBy('provider_profiles.hourly_rate', 'desc'),
            'distance' => $criteria->nearLat !== null
                ? $query->orderBy('distance_km', 'asc')
                : $query->orderByRaw('provider_profiles.rating_avg IS NULL')->orderBy('provider_profiles.rating_avg', 'desc'),
            'popularity' => $query->orderBy('provider_profiles.rating_count', 'desc'),
            default => $query
                ->orderByRaw('provider_profiles.rating_avg IS NULL')
                ->orderBy('provider_profiles.rating_avg', 'desc')
                ->orderBy('provider_profiles.rating_count', 'desc'),
        };
    }
}
