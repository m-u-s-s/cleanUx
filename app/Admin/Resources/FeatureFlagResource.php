<?php

namespace App\Admin\Resources;

use App\Admin\Console\Action;
use App\Admin\Console\AdminResource;
use App\Admin\Console\Column;
use App\Admin\Console\Field;
use App\Admin\Console\Filter;
use App\Models\FeatureFlagOverride;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Les dérogations de feature flags.
 *
 * CE QUE CETTE TABLE EST : une liste de DÉROGATIONS, pas le catalogue des drapeaux. Un drapeau
 * absent d'ici suit sa valeur par défaut ; une ligne ici la force. Confondre les deux ferait
 * croire qu'un drapeau non listé est désactivé, alors qu'il est simplement non dérogé.
 *
 * CHAQUE BASCULE EXIGE UN MOTIF, et c'est le champ le plus important du formulaire. Un drapeau
 * forcé sans raison écrite se retrouve six mois plus tard sans que personne n'ose y toucher —
 * la dérogation devient permanente par peur, pas par décision.
 *
 * L'AUTEUR DE LA DERNIÈRE BASCULE EST ENREGISTRÉ. Une dérogation qui change le comportement de
 * la plateforme sans laisser de nom n'est pas rattrapable.
 *
 * @implements AdminResource<FeatureFlagOverride>
 */
class FeatureFlagResource implements AdminResource
{
    public function key(): string
    {
        return 'feature-flags';
    }

    /** @return Builder<FeatureFlagOverride> */
    public function query(): Builder
    {
        return FeatureFlagOverride::query();
    }

    public function defaultSort(): string
    {
        return 'id';
    }

    public function columns(): array
    {
        return [
            Column::make('flag_key', 'Drapeau'),
            Column::make('is_enabled', 'Forcé à', Column::TYPE_BOOL),
            Column::make('reason', 'Motif'),
            Column::make('updated_at', 'Modifié le', Column::TYPE_DATETIME),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::search('q', 'Clé du drapeau'),
            Filter::bool('actives', 'Dérogations activantes seulement'),
        ];
    }

    public function sorts(): array
    {
        return ['id', 'flag_key', 'updated_at'];
    }

    public function actions(): array
    {
        return [
            Action::make('enable', 'Forcer à activé', function (FeatureFlagOverride $model) {
                $model->forceFill($this->trace(true))->save();

                return ['ok' => true];
            })->destructive('Le comportement de la plateforme change immédiatement pour tout le monde.'),

            Action::make('disable', 'Forcer à désactivé', function (FeatureFlagOverride $model) {
                $model->forceFill($this->trace(false))->save();

                return ['ok' => true];
            })->destructive('Le comportement de la plateforme change immédiatement pour tout le monde.'),
        ];
    }

    /**
     * La bascule et sa signature, indissociables.
     *
     * @return array<string, mixed>
     */
    private function trace(bool $enabled): array
    {
        $admin = Auth::user();

        return [
            'is_enabled' => $enabled,
            'updated_by_user_id' => $admin instanceof User ? $admin->id : null,
        ];
    }

    public function formFields(): array
    {
        return [
            Field::make('flag_key', 'Clé du drapeau')->rules(['required', 'string', 'max:128']),
            Field::make('is_enabled', 'Forcer à activé', Field::TYPE_BOOL)->rules(['boolean']),
            // Obligatoire, et long : « test » n'explique rien à qui relit dans six mois.
            Field::make('reason', 'Motif de la dérogation', Field::TYPE_TEXTAREA)
                ->rules(['required', 'string', 'min:10', 'max:1000']),
        ];
    }

    /**
     * @param  Builder<FeatureFlagOverride>  $query
     * @return Builder<FeatureFlagOverride>
     */
    public function applyFilter(Builder $query, string $key, mixed $value): Builder
    {
        return match ($key) {
            'q' => $query->where('flag_key', 'like', '%'.$value.'%'),
            'actives' => $query->where('is_enabled', true),
            default => $query,
        };
    }

    /** @param  FeatureFlagOverride  $model */
    public function toRow(Model $model): array
    {
        return [
            'id' => $model->getKey(),
            'flag_key' => $model->flag_key,
            'is_enabled' => (bool) $model->is_enabled,
            'reason' => $model->reason,
            'updated_at' => $model->updated_at?->toIso8601String(),
        ];
    }

    /** @param  FeatureFlagOverride  $model */
    public function toDetail(Model $model): array
    {
        return $this->toRow($model) + [
            'updated_by_user_id' => $model->updated_by_user_id,
            'override_config' => $model->override_config,
        ];
    }
}
