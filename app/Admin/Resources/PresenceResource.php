<?php

namespace App\Admin\Resources;

use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\ProviderPresence;

/**
 * La présence des prestataires sur le terrain.
 *
 * LECTURE SEULE. Le statut de présence est écrit par le battement de cœur de l’application
 * prestataire ; le forcer depuis ici ferait apparaître disponible quelqu’un dont le téléphone
 * ne répond plus, et le dispatch lui enverrait des missions.
 *
 * @extends EloquentResource<ProviderPresence>
 */
class PresenceResource extends EloquentResource
{
    public function key(): string
    {
        return 'presence';
    }

    protected function model(): string
    {
        return ProviderPresence::class;
    }

    protected function columnSpec(): array
    {
        return [
            'status' => ['Statut', Column::TYPE_BADGE],
            'heartbeat_at' => ['Dernier battement', Column::TYPE_DATETIME],
            'online_minutes_today' => ['Minutes aujourd’hui', Column::TYPE_NUMBER],
            'available_radius_km' => ['Rayon (km)', Column::TYPE_NUMBER],
            'last_status_change_at' => ['Changement de statut', Column::TYPE_DATETIME],
        ];
    }

    protected function searchable(): array
    {
        return [];
    }

    protected function searchLabel(): string
    {
        return 'Rechercher';
    }

    protected function selectFilters(): array
    {
        return [
            'status' => ['Statut', 'status', [
                ['value' => 'online', 'label' => 'En ligne'],
                ['value' => 'busy', 'label' => 'Occupé'],
                ['value' => 'paused', 'label' => 'En pause'],
                ['value' => 'offline', 'label' => 'Hors ligne'],
            ]],
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'last_online_at' => 'Dernière connexion',
            'online_minutes_week' => 'Minutes cette semaine',
        ];
    }
}
