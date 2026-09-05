<?php

namespace App\Admin\Resources;

use App\Admin\Console\Action;
use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Admin\Console\Field;
use App\Livewire\Admin\IdentiteLegale;
use App\Models\Parametre;
use App\Support\ActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Les cinq mentions légales de la plateforme, côté console mobile.
 *
 * Le même geste que le web, sur les mêmes lignes de `parametres` : une mention corrigée depuis
 * un téléphone doit s'afficher sur la page publique aussi sûrement que depuis un bureau.
 *
 * @extends EloquentResource<Parametre>
 */
class LegalIdentityResource extends EloquentResource
{
    public function key(): string
    {
        return 'identite-legale';
    }

    protected function model(): string
    {
        return Parametre::class;
    }

    /** Seules les cinq clefs légales : `parametres` porte aussi des réglages sans rapport. */
    public function query(): Builder
    {
        return parent::query()->whereIn('cle', array_keys(IdentiteLegale::CHAMPS));
    }

    protected function columnSpec(): array
    {
        return [
            'cle' => ['Mention', Column::TYPE_BADGE],
            'valeur' => ['Valeur publiée', Column::TYPE_TEXT],
            'updated_at' => ['Modifiée le', Column::TYPE_DATETIME],
        ];
    }

    protected function searchable(): array
    {
        return ['cle', 'valeur'];
    }

    protected function searchLabel(): string
    {
        return 'Mention ou valeur';
    }

    /**
     * UNE MENTION LEGALE SE CORRIGE, ELLE NE SE SUPPRIME PAS.
     *
     * `formFields()` vide ferme deja la creation et l'edition (405), mais PAS la suppression :
     * effacer la ligne rendrait « (a completer) » sur la page publique, sans que rien ne le dise.
     */
    public function reasonsToRefuseDelete(Model $model): array
    {
        return ['Une mention legale se corrige depuis /admin/identite-legale, elle ne se supprime pas.'];
    }

    public function actions(): array
    {
        return [
            Action::make('modifier', 'Corriger la mention', function (Parametre $parametre, array $valeurs) {
                Parametre::setValeur((string) $parametre->cle, trim((string) $valeurs['valeur']));

                // Une mention legale est opposable : qui l'a changee reste lisible.
                ActivityLogger::log('platform.legal_identity_updated', $parametre, [
                    'domain' => 'compliance',
                    'cle' => $parametre->cle,
                ]);

                return ['ok' => true];
            })->requires([
                Field::make('valeur', 'Nouvelle valeur', Field::TYPE_TEXTAREA)
                    ->rules(['nullable', 'string', 'max:500']),
            ]),
        ];
    }
}
