<?php

namespace App\Admin\Resources;

use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\ApiTokenUsage;

/**
 * Le journal d’usage des jetons d’API.
 *
 * La RÉVOCATION d’un jeton ne se fait pas depuis son journal d’usage : elle vit sur la page des
 * jetons, avec le délai de grâce qui évite de couper une intégration en pleine requête.
 *
 * @extends EloquentResource<ApiTokenUsage>
 */
class ApiTokenUsageResource extends EloquentResource
{
    public function key(): string
    {
        return 'api-tokens';
    }

    protected function model(): string
    {
        return ApiTokenUsage::class;
    }

    protected function columnSpec(): array
    {
        return [
            'route_path' => ['Route'],
            'method' => ['Méthode', Column::TYPE_BADGE],
            'response_status' => ['Statut', Column::TYPE_NUMBER],
            'latency_ms' => ['Latence (ms)', Column::TYPE_NUMBER],
            'occurred_at' => ['Survenu le', Column::TYPE_DATETIME],
        ];
    }

    protected function searchable(): array
    {
        return ['route_path'];
    }

    protected function searchLabel(): string
    {
        return 'Route';
    }

    protected function selectFilters(): array
    {
        return [
            'method' => ['Méthode', 'method', [
                ['value' => 'GET', 'label' => 'GET'],
                ['value' => 'POST', 'label' => 'POST'],
                ['value' => 'PATCH', 'label' => 'PATCH'],
                ['value' => 'DELETE', 'label' => 'DELETE'],
            ]],
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'response_size_bytes' => 'Taille de réponse (octets)',
        ];
    }
}
