<?php

namespace App\Admin\Resources;

use App\Admin\Console\Action;
use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Admin\Console\Field;
use App\Models\Sanctum\PersonalAccessTokenV2;
use App\Services\ApiTokensV2\ApiTokenManager;

/**
 * Les jetons d'API et leur cycle de vie.
 *
 * POURQUOI CE DESCRIPTEUR EXISTE À CÔTÉ DE CELUI DES USAGES. La page web des jetons montre deux
 * choses : l'USAGE — qui a appelé quoi, à quelle fréquence — et les JETONS eux-mêmes. Les trois
 * gestes de la page portent tous sur le jeton : suspendre, réactiver, révoquer. Le descripteur des
 * usages ne pouvait rien en faire, et un jeton compromis restait actif jusqu'au retour au bureau.
 *
 * C'EST UN ÉCRAN D'URGENCE. On apprend qu'un jeton a fuité par un message, souvent en déplacement ;
 * c'est exactement là qu'il faut pouvoir le révoquer. Le laisser au seul poste de travail, c'est
 * accepter un délai qui se compte en heures sur un secret déjà public.
 *
 * SUSPENDRE N'EST PAS RÉVOQUER. Une suspension se lève ; une révocation est définitive et casse
 * l'intégration qui s'en sert. Les deux gestes sont donc séparés, et seul le second est annoncé
 * comme destructif.
 *
 * @extends EloquentResource<PersonalAccessTokenV2>
 */
class ApiTokenV2Resource extends EloquentResource
{
    public function key(): string
    {
        return 'api-tokens-list';
    }

    protected function model(): string
    {
        return PersonalAccessTokenV2::class;
    }

    protected function columnSpec(): array
    {
        return [
            'display_name' => ['Nom'],
            'owner_role' => ['Rôle'],
            'rate_limit_per_minute' => ['Limite / min', Column::TYPE_NUMBER],
            'last_used_at' => ['Dernier usage', Column::TYPE_DATE],
            'expires_at' => ['Expire le', Column::TYPE_DATE],
        ];
    }

    protected function searchable(): array
    {
        return ['name', 'display_name'];
    }

    protected function searchLabel(): string
    {
        return 'Nom du jeton';
    }

    protected function detailSpec(): array
    {
        return [
            'description' => 'Description',
            'tokenable_type' => 'Porteur',
        ];
    }

    public function actions(): array
    {
        return [
            Action::make('suspend', 'Suspendre le jeton', function (PersonalAccessTokenV2 $token, array $valeurs) {
                app(ApiTokenManager::class)->suspend($token, (string) $valeurs['reason']);

                return ['ok' => true];
            })->requires([
                /*
                 * Le motif est obligatoire : une suspension sans raison sera levée par le premier
                 * qui la verra, faute de savoir pourquoi elle est là — et le jeton compromis
                 * reprendra du service.
                 */
                Field::make('reason', 'Motif de la suspension', Field::TYPE_TEXTAREA)
                    ->rules(['required', 'string', 'min:5', 'max:500']),
            ]),

            Action::make('unsuspend', 'Réactiver le jeton', function (PersonalAccessTokenV2 $token) {
                app(ApiTokenManager::class)->unsuspend($token);

                return ['ok' => true];
            }),

            Action::make('revoke', 'Révoquer définitivement', function (PersonalAccessTokenV2 $token) {
                app(ApiTokenManager::class)->revoke($token);

                return ['ok' => true];
            })->destructive('Le jeton sera révoqué définitivement. L’intégration qui l’utilise cessera de fonctionner.'),
        ];
    }
}
