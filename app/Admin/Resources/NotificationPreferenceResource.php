<?php

namespace App\Admin\Resources;

use App\Admin\Console\Action;
use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\NotificationPreference;
use App\Services\NotificationPreferences\NotificationPreferenceService;
use Illuminate\Support\Facades\Auth;

/**
 * La matrice canal × catégorie des préférences de notification.
 *
 * @extends EloquentResource<NotificationPreference>
 */
class NotificationPreferenceResource extends EloquentResource
{
    public function key(): string
    {
        return 'notification-preferences';
    }

    protected function model(): string
    {
        return NotificationPreference::class;
    }

    protected function columnSpec(): array
    {
        return [
            'channel' => ['Canal', Column::TYPE_BADGE],
            'category' => ['Catégorie', Column::TYPE_BADGE],
            'is_allowed' => ['Autorisé', Column::TYPE_BOOL],
            'version' => ['Version', Column::TYPE_NUMBER],
            'last_changed_at' => ['Modifié le', Column::TYPE_DATETIME],
        ];
    }

    protected function searchable(): array
    {
        return ['channel', 'category'];
    }

    protected function searchLabel(): string
    {
        return 'Canal ou catégorie';
    }

    public function actions(): array
    {
        return [
            // LE MEME GESTE QUE LE WEB, ET PAR LE MEME SERVICE : il refuse deja de couper une
            // categorie obligatoire et ecrit le journal RGPD versionne. La source est `admin`
            // et l'acteur est nomme — une correction faite POUR quelqu'un n'est pas son choix.
            Action::make('basculer', 'Couper ou rouvrir', function (NotificationPreference $preference) {
                if (! $preference->user) {
                    return ['ok' => false, 'message' => 'Préférence orpheline : son porteur n’existe plus.'];
                }

                $avant = (bool) $preference->is_allowed;

                $apres = app(NotificationPreferenceService::class)->setPreference(
                    user: $preference->user,
                    channel: (string) $preference->channel,
                    category: (string) $preference->category,
                    isAllowed: ! $avant,
                    source: NotificationPreference::SOURCE_ADMIN,
                    request: request(),
                    actor: Auth::user(),
                );

                if ((bool) $apres->is_allowed === $avant) {
                    return ['ok' => false, 'message' => 'Catégorie obligatoire : elle ne peut pas être coupée.'];
                }

                return ['ok' => true];
            }),
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'source' => 'Origine du choix',
        ];
    }
}
