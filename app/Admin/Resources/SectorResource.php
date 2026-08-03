<?php

namespace App\Admin\Resources;

use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\Sector;

/**
 * Les secteurs du moteur de commande.
 *
 * L’ARBRE COMPLET — secteur, métier, questions — ne se rend pas en liste sans mentir sur sa
 * structure : c’est une hiérarchie où chaque question dépend d’un métier, et l’aplatir ferait
 * perdre ce qui dépend de quoi. Cette liste sert le premier niveau ; le constructeur de
 * questionnaire reste sur le web, qui peut montrer l’arbre.
 *
 * @extends EloquentResource<Sector>
 */
class SectorResource extends EloquentResource
{
    public function key(): string
    {
        return 'catalog';
    }

    protected function model(): string
    {
        return Sector::class;
    }

    protected function columnSpec(): array
    {
        return [
            'name' => ['Secteur'],
            'slug' => ['Identifiant'],
            'sort_order' => ['Ordre', Column::TYPE_NUMBER],
            'is_active' => ['Actif', Column::TYPE_BOOL],
            'published_at' => ['Publié le', Column::TYPE_DATE],
        ];
    }

    protected function searchable(): array
    {
        return ['name', 'slug', 'tagline'];
    }

    protected function searchLabel(): string
    {
        return 'Nom ou accroche';
    }

    protected function detailSpec(): array
    {
        return [
            'tagline' => 'Accroche',
            'icon' => 'Icône',
            'accent_color' => 'Couleur',
        ];
    }
}
