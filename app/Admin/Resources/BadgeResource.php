<?php

namespace App\Admin\Resources;

use App\Admin\Console\Action;
use App\Admin\Console\AdminResource;
use App\Admin\Console\Column;
use App\Admin\Console\DefaultsResourceWrites;
use App\Admin\Console\Field;
use App\Admin\Console\Filter;
use App\Models\ProviderBadge;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Les badges décernés aux prestataires — leurs DÉFINITIONS, pas les attributions.
 *
 * Ce descripteur édite le catalogue : quel badge existe, à quel palier, sur quel critère et à
 * partir de quel seuil. Les attributions elles-mêmes (`ProviderBadgeAward`) sont posées par le
 * moteur d'évaluation et ne se saisissent pas à la main : décerner un badge « 100 missions » à
 * quelqu'un qui en a fait douze viderait le badge de son sens pour tous les autres.
 *
 * DÉSACTIVER PLUTÔT QUE SUPPRIMER : les badges déjà décernés pointent sur la définition, et
 * l'effacer laisserait des prestataires porteurs d'une distinction que plus rien ne décrit.
 *
 * @implements AdminResource<ProviderBadge>
 */
class BadgeResource implements AdminResource
{
    use DefaultsResourceWrites;

    public function key(): string
    {
        return 'badges';
    }

    /** @return Builder<ProviderBadge> */
    public function query(): Builder
    {
        return ProviderBadge::query();
    }

    public function defaultSort(): string
    {
        return 'id';
    }

    public function columns(): array
    {
        return [
            Column::make('name', 'Badge'),
            Column::make('tier', 'Palier', Column::TYPE_BADGE),
            Column::make('criterion_type', 'Critère'),
            Column::make('threshold', 'Seuil', Column::TYPE_NUMBER),
            Column::make('is_active', 'Actif', Column::TYPE_BOOL),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::search('q', 'Nom ou code'),
            Filter::select('tier', 'Palier', [
                ['value' => ProviderBadge::TIER_BRONZE, 'label' => 'Bronze'],
                ['value' => ProviderBadge::TIER_SILVER, 'label' => 'Argent'],
                ['value' => ProviderBadge::TIER_GOLD, 'label' => 'Or'],
                ['value' => ProviderBadge::TIER_PLATINUM, 'label' => 'Platine'],
            ]),
            Filter::bool('actifs', 'Badges actifs seulement'),
        ];
    }

    public function sorts(): array
    {
        return ['id', 'name', 'threshold'];
    }

    public function actions(): array
    {
        return [
            Action::make('activate', 'Activer', function (ProviderBadge $model) {
                $model->forceFill(['is_active' => true])->save();

                return ['ok' => true];
            }),

            Action::make('deactivate', 'Désactiver', function (ProviderBadge $model) {
                $model->forceFill(['is_active' => false])->save();

                return ['ok' => true];
            })->destructive('Le badge cessera d’être décerné. Ceux déjà attribués restent acquis.'),
        ];
    }

    public function formFields(): array
    {
        return [
            Field::make('code', 'Code')->rules(['required', 'string', 'max:64']),
            Field::make('name', 'Nom')->rules(['required', 'string', 'max:255']),
            Field::make('description', 'Description', Field::TYPE_TEXTAREA)
                ->rules(['nullable', 'string', 'max:1000']),
            Field::make('icon', 'Icône')->rules(['nullable', 'string', 'max:64']),
            Field::select('tier', 'Palier', [
                ['value' => ProviderBadge::TIER_BRONZE, 'label' => 'Bronze'],
                ['value' => ProviderBadge::TIER_SILVER, 'label' => 'Argent'],
                ['value' => ProviderBadge::TIER_GOLD, 'label' => 'Or'],
                ['value' => ProviderBadge::TIER_PLATINUM, 'label' => 'Platine'],
            ])->rules(['required', 'in:bronze,silver,gold,platinum']),
            Field::select('criterion_type', 'Critère', [
                ['value' => ProviderBadge::CRITERION_MISSIONS_COUNT, 'label' => 'Nombre de missions'],
                ['value' => ProviderBadge::CRITERION_RATING_AVG, 'label' => 'Note moyenne'],
                ['value' => ProviderBadge::CRITERION_TIPS_RECEIVED, 'label' => 'Pourboires reçus'],
                ['value' => ProviderBadge::CRITERION_TENURE_DAYS, 'label' => 'Ancienneté (jours)'],
                ['value' => ProviderBadge::CRITERION_LOYALTY_POINTS, 'label' => 'Points de fidélité'],
                ['value' => ProviderBadge::CRITERION_STREAK_5_STARS, 'label' => 'Série de 5 étoiles'],
            ])->rules(['required', 'string', 'max:64']),
            Field::make('threshold', 'Seuil', Field::TYPE_NUMBER)
                ->rules(['required', 'numeric', 'min:0']),
            Field::make('is_active', 'Actif', Field::TYPE_BOOL)->rules(['boolean']),
        ];
    }

    /**
     * @param  Builder<ProviderBadge>  $query
     * @return Builder<ProviderBadge>
     */
    public function applyFilter(Builder $query, string $key, mixed $value): Builder
    {
        return match ($key) {
            'q' => $query->where(function (Builder $sub) use ($value) {
                $sub->where('name', 'like', '%'.$value.'%')
                    ->orWhere('code', 'like', '%'.$value.'%');
            }),
            'tier' => $query->where('tier', $value),
            'actifs' => $query->where('is_active', true),
            default => $query,
        };
    }

    /** @param  ProviderBadge  $model */
    public function toRow(Model $model): array
    {
        return [
            'id' => $model->getKey(),
            'name' => $model->name,
            'tier' => $model->tier,
            'criterion_type' => $model->criterion_type,
            'threshold' => $model->threshold,
            'is_active' => (bool) $model->is_active,
        ];
    }

    /** @param  ProviderBadge  $model */
    public function toDetail(Model $model): array
    {
        return $this->toRow($model) + [
            'code' => $model->code,
            'description' => $model->description,
            'icon' => $model->icon,
        ];
    }
}
