<?php

namespace App\Admin\Resources;

use App\Admin\Console\Action;
use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Admin\Console\Field;
use App\Models\Sector;
use App\Services\Catalog\CatalogOrdering;
use App\Services\OrderEngine\CatalogArchiver;

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

    /** Les champs d'un secteur : ce qui compose le carrousel client. */
    public function formFields(): array
    {
        return [
            Field::make('name', 'Nom')->rules(['required', 'string', 'max:120']),
            Field::make('slug', 'Identifiant (slug)')->rules(['required', 'string', 'max:80', 'regex:/^[a-z0-9\-]+$/']),
            Field::make('tagline', 'Accroche')->rules(['nullable', 'string', 'max:180']),
            Field::make('accent_color', 'Couleur')->rules(['nullable', 'string', 'max:16']),
            Field::make('sort_order', 'Ordre dans le carrousel', Field::TYPE_NUMBER)
                ->rules(['nullable', 'integer', 'min:0', 'max:9999']),
        ];
    }

    public function actions(): array
    {
        return [

            /*
             * Monter et descendre plutôt qu'un glisser-déposer : le glisser ne fonctionne ni au
             * clavier ni avec un lecteur d'écran, et sur un téléphone il se confond avec le
             * défilement de la liste. Le web garde les flèches pour la même raison.
             */
            Action::make('move-up', 'Monter dans le carrousel', function (Sector $secteur) {
                app(CatalogOrdering::class)->deplacer(Sector::query(), $secteur->id, -1);

                return ['ok' => true];
            }),

            Action::make('move-down', 'Descendre dans le carrousel', function (Sector $secteur) {
                app(CatalogOrdering::class)->deplacer(Sector::query(), $secteur->id, 1);

                return ['ok' => true];
            }),

            /*
             * ARCHIVER N'EST PAS SUPPRIMER. On passe par `CatalogArchiver`, le même service que le
             * web : il conserve la ligne et laisse les métiers intacts. Inventer un `delete` ici
             * ferait deux chemins vers la même table, avec deux résultats selon la porte.
             */
            Action::make('archive', 'Archiver le secteur', function (Sector $secteur) {
                app(CatalogArchiver::class)->archive($secteur);

                return ['ok' => true];
            })->destructive('Le secteur sera archivé. Ses métiers restent intacts.'),
            /*
             * « Retirer du carrousel » plutôt que « désactiver » : c'est ce que le geste FAIT, et
             * c'est ce que l'administrateur cherche. Un secteur inactif n'est pas supprimé, il
             * disparaît de ce que voit le visiteur.
             */
            Action::make('toggle-active', 'Retirer / remettre dans le carrousel', function (Sector $secteur) {
                $secteur->forceFill(['is_active' => ! $secteur->is_active])->save();

                return ['is_active' => (bool) $secteur->fresh()->is_active];
            }),
        ];
    }

    public function prepareForCreate(array $data): array
    {
        return $data + ['is_active' => true, 'sort_order' => 0];
    }
}
