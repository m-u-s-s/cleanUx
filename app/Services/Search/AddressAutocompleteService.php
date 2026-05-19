<?php

namespace App\Services\Search;

use App\Models\PostalCode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Autocomplétion d'adresse basée sur les `postal_codes` connus de la DB.
 * Pas de dépendance externe (Google Places) — fallback local performant.
 */
class AddressAutocompleteService
{
    public function search(string $query, ?int $countryId = null, int $limit = 10): Collection
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return collect();
        }

        $isNumeric = preg_match('/^\d+/', $query) === 1;

        $builder = PostalCode::query()
            ->select(['id', 'code', 'city_name', 'lat', 'lng', 'country_id', 'service_zone_id'])
            ->where('is_active', true);

        if ($countryId) {
            $builder->where('country_id', $countryId);
        }

        $term = $query . '%';
        if ($isNumeric) {
            $builder->where('code', 'like', $term);
        } else {
            $builder->where(function ($q) use ($term) {
                $q->where('city_name', 'like', $term);
            });
        }

        $results = $builder->orderBy('code')->limit($limit * 2)->get();

        return $results
            ->sortByDesc(fn ($p) => $this->scoreMatch($p, $query, $isNumeric))
            ->take($limit)
            ->values()
            ->map(fn (PostalCode $p) => [
                'id' => $p->id,
                'code' => $p->code,
                'city_name' => $p->city_name,
                'label' => $p->code . ' ' . $p->city_name,
                'lat' => $p->lat !== null ? (float) $p->lat : null,
                'lng' => $p->lng !== null ? (float) $p->lng : null,
                'service_zone_id' => $p->service_zone_id,
                'country_id' => $p->country_id,
            ]);
    }

    protected function scoreMatch(PostalCode $p, string $query, bool $isNumeric): int
    {
        $query = mb_strtolower($query);
        $code = mb_strtolower((string) $p->code);
        $city = mb_strtolower((string) $p->city_name);

        $score = 0;

        if ($isNumeric) {
            if ($code === $query) $score += 1000;
            elseif (str_starts_with($code, $query)) $score += 100;
        } else {
            if ($city === $query) $score += 1000;
            elseif (str_starts_with($city, $query)) $score += 100;
            elseif (str_contains($city, $query)) $score += 10;
        }

        return $score;
    }
}
