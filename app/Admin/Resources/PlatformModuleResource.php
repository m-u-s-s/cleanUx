<?php

namespace App\Admin\Resources;

use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\PlatformModule;

/**
 * Les modules de la plateforme et leur activation.
 *
 * Certains modules sont VERROUILLÉS (`is_locked`) : ce sont ceux dont dépend le fonctionnement
 * de base. Le rendu générique montre l’état ; la bascule reste sur la page dédiée, qui refuse
 * de toucher aux modules verrouillés.
 *
 * @extends EloquentResource<PlatformModule>
 */
class PlatformModuleResource extends EloquentResource
{
    public function key(): string
    {
        return 'platform-modules';
    }

    protected function model(): string
    {
        return PlatformModule::class;
    }

    protected function columnSpec(): array
    {
        return [
            'name' => ['Module'],
            'key' => ['Clé'],
            'category' => ['Catégorie', Column::TYPE_BADGE],
            'is_enabled' => ['Actif', Column::TYPE_BOOL],
            'is_locked' => ['Verrouillé', Column::TYPE_BOOL],
        ];
    }

    protected function searchable(): array
    {
        return ['name', 'key', 'description'];
    }

    protected function searchLabel(): string
    {
        return 'Nom, clé ou description';
    }

    protected function detailSpec(): array
    {
        return [
            'description' => 'Description',
            'rollout_strategy' => 'Stratégie de déploiement',
        ];
    }
}
