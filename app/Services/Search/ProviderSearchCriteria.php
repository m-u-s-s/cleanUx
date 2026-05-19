<?php

namespace App\Services\Search;

/**
 * DTO immuable des critères de recherche provider.
 */
class ProviderSearchCriteria
{
    public function __construct(
        public readonly ?int $tradeId = null,
        public readonly ?int $serviceCatalogId = null,
        public readonly ?float $minRating = null,
        public readonly ?float $minPrice = null,
        public readonly ?float $maxPrice = null,
        public readonly ?int $serviceZoneId = null,
        public readonly ?string $postalCode = null,
        public readonly ?int $countryId = null,
        public readonly ?float $nearLat = null,
        public readonly ?float $nearLng = null,
        public readonly ?int $radiusKm = null,
        public readonly bool $onlineOnly = false,
        public readonly bool $hasPhotoOnly = false,
        public readonly ?string $query = null,
        public readonly string $sort = 'rating',
        public readonly int $page = 1,
        public readonly int $perPage = 20,
    ) {}

    public static function fromArray(array $params): self
    {
        return new self(
            tradeId: isset($params['trade_id']) ? (int) $params['trade_id'] : null,
            serviceCatalogId: isset($params['service_catalog_id']) ? (int) $params['service_catalog_id'] : null,
            minRating: isset($params['min_rating']) ? (float) $params['min_rating'] : null,
            minPrice: isset($params['min_price']) ? (float) $params['min_price'] : null,
            maxPrice: isset($params['max_price']) ? (float) $params['max_price'] : null,
            serviceZoneId: isset($params['service_zone_id']) ? (int) $params['service_zone_id'] : null,
            postalCode: $params['postal_code'] ?? null,
            countryId: isset($params['country_id']) ? (int) $params['country_id'] : null,
            nearLat: isset($params['near_lat']) ? (float) $params['near_lat'] : null,
            nearLng: isset($params['near_lng']) ? (float) $params['near_lng'] : null,
            radiusKm: isset($params['radius_km']) ? (int) $params['radius_km'] : null,
            onlineOnly: (bool) ($params['online_only'] ?? false),
            hasPhotoOnly: (bool) ($params['has_photo'] ?? false),
            query: ! empty($params['q']) ? (string) $params['q'] : null,
            sort: in_array($params['sort'] ?? null, ['rating', 'price_asc', 'price_desc', 'distance', 'popularity'], true)
                ? $params['sort']
                : 'rating',
            page: max(1, (int) ($params['page'] ?? 1)),
            perPage: min(50, max(1, (int) ($params['per_page'] ?? 20))),
        );
    }
}
