<?php

namespace App\Admin\Resources;

use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Admin\Console\Field;
use App\Models\TranslationOverride;

/**
 * Les traductions surchargees sans déploiement.
 *
 * Une surcharge PUBLIÉE prend effet immédiatement pour toute la langue concernée : la bascule
 * de publication est donc annoncee comme destructive, meme si elle n'efface rien.
 *
 * @extends EloquentResource<TranslationOverride>
 */
class TranslationResource extends EloquentResource
{
    public function key(): string
    {
        return 'translations';
    }

    protected function model(): string
    {
        return TranslationOverride::class;
    }

    protected function columnSpec(): array
    {
        return [
            'locale' => ['Langue', Column::TYPE_BADGE],
            'group' => ['Groupe'],
            'key' => ['Clé'],
            'value' => ['Traduction'],
            'is_published' => ['Publiée', Column::TYPE_BOOL],
        ];
    }

    protected function searchable(): array
    {
        return ['key', 'value', 'group'];
    }

    protected function searchLabel(): string
    {
        return 'Clé, texte ou groupe';
    }

    protected function selectFilters(): array
    {
        return [
            'locale' => ['Langue', 'locale', [
                ['value' => 'fr', 'label' => 'Français'],
                ['value' => 'nl', 'label' => 'Néerlandais'],
                ['value' => 'en', 'label' => 'Anglais'],
                ['value' => 'de', 'label' => 'Allemand'],
                ['value' => 'es', 'label' => 'Espagnol'],
                ['value' => 'it', 'label' => 'Italien'],
            ]],
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'namespace' => 'Espace de noms',
            'updated_at' => 'Modifiée le',
        ];
    }

    public function formFields(): array
    {
        return [
            /*
             * La clé, le groupe et la locale IDENTIFIENT la surcharge : les modifier ne
             * corrigerait pas une traduction, cela en créerait une autre et laisserait la
             * première en place. Seule la VALEUR se modifie ici.
             */
            Field::make('value', 'Traduction', Field::TYPE_TEXTAREA)
                ->rules(['required', 'string', 'max:5000']),
        ];
    }
}
