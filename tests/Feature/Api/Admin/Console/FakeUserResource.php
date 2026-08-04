<?php

namespace Tests\Feature\Api\Admin\Console;

use App\Admin\Console\Action;
use App\Admin\Console\AdminResource;
use App\Admin\Console\Column;
use App\Admin\Console\DefaultsResourceWrites;
use App\Admin\Console\Field;
use App\Admin\Console\Filter;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Un descripteur d'essai, adossé à `users` — une table qui existe partout.
 *
 * Il sert à éprouver le MOTEUR, pas un domaine : il porte volontairement une colonne de chaque
 * forme, un filtre de chaque type, une action ordinaire et une destructive, et un formulaire.
 * Éprouver le moteur sur un vrai descripteur mêlerait ses défauts à ceux du domaine.
 *
 * @implements AdminResource<User>
 */
class FakeUserResource implements AdminResource
{
    // Les réponses neutres aux deux questions posées avant écriture.
    use DefaultsResourceWrites;

    /** Trace des actions exécutées, pour que les tests vérifient la délégation. */
    public static array $executed = [];

    public function key(): string
    {
        return 'fake-users';
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
            Column::make('created_at', 'Inscrit le', Column::TYPE_DATE),
            Column::make('is_active', 'Actif', Column::TYPE_BOOL),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::search('q', 'Rechercher'),
            Filter::select('role', 'Rôle', [
                ['value' => 'client', 'label' => 'Client'],
                ['value' => 'admin', 'label' => 'Administrateur'],
            ]),
            Filter::bool('actif', 'Actif seulement'),
        ];
    }

    public function sorts(): array
    {
        return ['id', 'name', 'created_at'];
    }

    public function actions(): array
    {
        return [
            Action::make('ping', 'Ping', function (Model $model) {
                self::$executed[] = ['ping', $model->getKey()];

                return ['ok' => true, 'message' => 'pong'];
            }),
            Action::make('suspend', 'Suspendre', function (Model $model) {
                self::$executed[] = ['suspend', $model->getKey()];

                return ['ok' => true];
            })->destructive('Ce compte ne pourra plus se connecter.'),
        ];
    }

    public function formFields(): array
    {
        return [
            Field::make('name', 'Nom')->rules(['required', 'string', 'max:255']),
            Field::make('email', 'Email', Field::TYPE_EMAIL)->rules(['required', 'email']),
            // `users.password` est NOT NULL : un formulaire qui l'omet fait échouer l'insertion.
            // C'est la règle générale des descripteurs — voir AdminResource::formFields().
            Field::make('password', 'Mot de passe')->rules(['required', 'string', 'min:8']),
        ];
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function applyFilter(Builder $query, string $key, mixed $value): Builder
    {
        return match ($key) {
            'q' => $query->where('name', 'like', '%'.$value.'%'),
            'role' => $query->where('role', $value),
            'actif' => $query->where('is_active', (bool) $value),
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
            'created_at' => $model->created_at?->toIso8601String(),
            'is_active' => (bool) $model->is_active,
        ];
    }

    /** @param  User  $model */
    public function toDetail(Model $model): array
    {
        return $this->toRow($model) + ['role' => $model->role];
    }
}
