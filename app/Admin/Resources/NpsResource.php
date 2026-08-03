<?php

namespace App\Admin\Resources;

use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\NpsResponse;

/**
 * Les réponses à l'enquête de satisfaction.
 *
 * Aucune action : un score NPS se reçoit, il ne se corrige pas. Le modifier après coup viderait
 * la mesure de son sens — et c’est la seule chose qu’elle apporte.
 *
 * @extends EloquentResource<NpsResponse>
 */
class NpsResource extends EloquentResource
{
    public function key(): string
    {
        return 'nps';
    }

    protected function model(): string
    {
        return NpsResponse::class;
    }

    protected function columnSpec(): array
    {
        return [
            'score' => ['Score', Column::TYPE_NUMBER],
            'category' => ['Catégorie', Column::TYPE_BADGE],
            'comment' => ['Commentaire'],
            'locale' => ['Langue'],
            'created_at' => ['Reçue le', Column::TYPE_DATE],
        ];
    }

    protected function searchable(): array
    {
        return ['comment', 'survey_code'];
    }

    protected function searchLabel(): string
    {
        return 'Commentaire ou enquête';
    }

    protected function selectFilters(): array
    {
        return [
            'category' => ['Catégorie', 'category', [
                ['value' => 'promoter', 'label' => 'Promoteur'],
                ['value' => 'passive', 'label' => 'Passif'],
                ['value' => 'detractor', 'label' => 'Détracteur'],
            ]],
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'survey_code' => 'Enquete',
            'responded_at' => 'Répondue le',
        ];
    }
}
