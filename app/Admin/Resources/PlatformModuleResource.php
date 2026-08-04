<?php

namespace App\Admin\Resources;

use App\Admin\Console\Action;
use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Admin\Console\Field;
use App\Models\PlatformModule;
use App\Support\ActivityLogger;

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

    public function formFields(): array
    {
        return [
            // La clé technique identifie le module dans tout le code : elle est obligatoire à
            // la création, et le garde-fou des descripteurs l'a signalé avant la première
            // tentative — un formulaire incomplet rend 500, pas un message lisible.
            Field::make('key', 'Clé technique')->rules(['required', 'string', 'max:255']),
            Field::make('name', 'Nom')->rules(['required', 'string', 'max:255']),
            Field::make('description', 'Description', Field::TYPE_TEXTAREA)->rules(['nullable', 'string', 'max:2000']),
            Field::make('category', 'Catégorie')->rules(['required', 'string', 'max:100']),
            Field::select('rollout_strategy', 'Déploiement', [
                ['value' => 'global', 'label' => 'Global'],
                ['value' => 'role', 'label' => 'Par rôle'],
                ['value' => 'plan', 'label' => 'Par plan'],
                ['value' => 'zone', 'label' => 'Par zone'],
                ['value' => 'organization', 'label' => 'Par organisation'],
            ])->rules(['required', 'in:global,role,plan,zone,organization']),
        ];
    }

    public function actions(): array
    {
        return [
            /*
             * UN MODULE VERROUILLÉ NE SE BASCULE PAS, et le refus est explicite. Le verrou existe
             * pour les modules dont l'extinction casserait la plateforme ; l'ignorer ici donnerait
             * au mobile un pouvoir que le web refuse.
             */
            Action::make('toggle-enabled', 'Activer / désactiver', function (PlatformModule $module) {
                if ($module->is_locked) {
                    return ['ok' => false, 'message' => 'Ce module est verrouillé.'];
                }

                $module->forceFill(['is_enabled' => ! $module->is_enabled])->save();
                ActivityLogger::log('platform_module.toggled', $module, ['is_enabled' => $module->is_enabled]);

                return ['is_enabled' => (bool) $module->fresh()->is_enabled];
            }),
        ];
    }
}
