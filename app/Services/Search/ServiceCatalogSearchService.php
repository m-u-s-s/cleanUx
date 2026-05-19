<?php

namespace App\Services\Search;

use App\Models\ServiceCatalog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;

class ServiceCatalogSearchService
{
    public function search(array $params = []): LengthAwarePaginator
    {
        $query = ServiceCatalog::query()
            ->where('is_active', true);

        if (! empty($params['trade_id'])) {
            $query->where('trade_id', (int) $params['trade_id']);
        }

        if (! empty($params['category'])) {
            $query->where('category', $params['category']);
        }

        if (! empty($params['service_type'])) {
            $query->where('service_type', $params['service_type']);
        }

        if (! empty($params['is_b2b_available'])) {
            $query->where('is_b2b_available', true);
        }
        if (! empty($params['is_personal_available'])) {
            $query->where('is_personal_available', true);
        }

        if (! empty($params['q'])) {
            $term = '%' . $params['q'] . '%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhere('code', 'like', $term);
            });
        }

        if (! empty($params['min_price'])) {
            $query->where('base_price', '>=', (float) $params['min_price']);
        }
        if (! empty($params['max_price'])) {
            $query->where('base_price', '<=', (float) $params['max_price']);
        }

        $sort = $params['sort'] ?? 'featured';
        match ($sort) {
            'price_asc' => $query->orderBy('base_price', 'asc'),
            'price_desc' => $query->orderBy('base_price', 'desc'),
            'name' => $query->orderBy('name', 'asc'),
            default => $query
                ->orderByDesc('is_featured')
                ->orderBy('sort_order')
                ->orderBy('name'),
        };

        $perPage = min(50, max(1, (int) ($params['per_page'] ?? 20)));
        $page = max(1, (int) ($params['page'] ?? 1));

        return $query->paginate(perPage: $perPage, page: $page);
    }
}
