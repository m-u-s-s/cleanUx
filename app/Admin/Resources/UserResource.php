<?php

namespace App\Admin\Resources;

use App\Admin\Console\Action;
use App\Admin\Console\AdminResource;
use App\Admin\Console\Column;
use App\Admin\Console\Field;
use App\Admin\Console\Filter;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Les comptes de la plateforme.
 *
 * CE QUE CE DESCRIPTEUR REFUSE DÉLIBÉRÉMENT DE FAIRE :
 *
 * - **Il ne mint aucun super-administrateur.** Le champ de rôle n'offre que `user` et `admin`.
 *   Un compte capable de tout faire ne se crée pas depuis un téléphone, entre deux portes.
 * - **Il ne touche pas aux rôles métier** (client, prestataire). Ceux-là dépendent de profils et
 *   d'organisations liés ; les changer par une écriture de colonne laisserait un compte dont le
 *   rôle affiché et les données ne s'accordent plus.
 *
 * Le mot de passe passe par le cast `hashed` du modèle : il n'est jamais stocké en clair, et
 * n'est jamais servi en lecture — `toRow()` et `toDetail()` ne le nomment pas.
 *
 * @implements AdminResource<User>
 */
class UserResource implements AdminResource
{
    public function key(): string
    {
        return 'users';
    }

    /** @return Builder<User> */
    public function query(): Builder
    {
        return User::query();
    }

    public function defaultSort(): string
    {
        return 'id';
    }

    public function columns(): array
    {
        return [
            Column::make('name', 'Nom'),
            Column::make('email', 'Email'),
            Column::make('platform_role', 'Rôle', Column::TYPE_BADGE),
            Column::make('created_at', 'Inscrit le', Column::TYPE_DATE),
            Column::make('is_active', 'Actif', Column::TYPE_BOOL),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::search('q', 'Nom, email ou TVA'),
            Filter::select('platform_role', 'Rôle', [
                ['value' => 'user', 'label' => 'Utilisateur'],
                ['value' => 'admin', 'label' => 'Administrateur'],
                ['value' => 'super_admin', 'label' => 'Super-administrateur'],
            ]),
            Filter::bool('actifs', 'Comptes actifs seulement'),
        ];
    }

    public function sorts(): array
    {
        return ['id', 'name', 'email', 'created_at'];
    }

    public function actions(): array
    {
        return [
            Action::make('suspend', 'Suspendre', function (Model $model) {
                $model->forceFill(['is_active' => false])->save();

                return ['ok' => true];
            })->destructive('Ce compte ne pourra plus se connecter tant qu’il n’est pas réactivé.'),

            Action::make('reactivate', 'Réactiver', function (Model $model) {
                $model->forceFill(['is_active' => true])->save();

                return ['ok' => true];
            }),
        ];
    }

    public function formFields(): array
    {
        return [
            Field::make('name', 'Nom')->rules(['required', 'string', 'max:255']),
            Field::make('email', 'Email', Field::TYPE_EMAIL)
                ->rules(['required', 'email', 'max:255']),
            Field::make('phone', 'Téléphone', Field::TYPE_PHONE)
                ->rules(['nullable', 'string', 'max:32']),
            // Obligatoire : `users.password` est NOT NULL. En édition, le moteur ne valide que les
            // champs REÇUS — ne pas l'envoyer laisse donc le mot de passe inchangé.
            Field::make('password', 'Mot de passe')->rules(['required', 'string', 'min:8']),
            Field::select('platform_role', 'Rôle', [
                ['value' => 'user', 'label' => 'Utilisateur'],
                ['value' => 'admin', 'label' => 'Administrateur'],
            ])->rules(['required', 'in:user,admin']),
            Field::select('locale', 'Langue', [
                ['value' => 'fr_BE', 'label' => 'Français'],
                ['value' => 'nl_BE', 'label' => 'Néerlandais'],
                ['value' => 'en', 'label' => 'Anglais'],
            ])->rules(['nullable', 'string', 'max:10']),
        ];
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function applyFilter(Builder $query, string $key, mixed $value): Builder
    {
        return match ($key) {
            'q' => $query->where(function (Builder $sub) use ($value) {
                $sub->where('name', 'like', '%'.$value.'%')
                    ->orWhere('email', 'like', '%'.$value.'%')
                    ->orWhere('tva_number', 'like', '%'.$value.'%');
            }),
            'platform_role' => $query->where('platform_role', $value),
            'actifs' => $query->where('is_active', true),
            default => $query,
        };
    }

    /** @param  User  $model */
    public function toRow(Model $model): array
    {
        return [
            'id' => $model->getKey(),
            'name' => $model->name,
            'email' => $model->email,
            'platform_role' => $model->platform_role,
            'created_at' => $model->created_at?->toIso8601String(),
            'is_active' => (bool) $model->is_active,
        ];
    }

    /** @param  User  $model */
    public function toDetail(Model $model): array
    {
        return $this->toRow($model) + [
            'phone' => $model->phone,
            'locale' => $model->locale,
            'email_verified_at' => $model->email_verified_at?->toIso8601String(),
        ];
    }
}
