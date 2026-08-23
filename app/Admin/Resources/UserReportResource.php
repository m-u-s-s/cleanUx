<?php

namespace App\Admin\Resources;

use App\Admin\Console\Action;
use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Admin\Console\Field;
use App\Models\UserReport;
use App\Services\Safety\UserSafetyService;

/**
 * Les signalements entre utilisateurs.
 *
 * @extends EloquentResource<UserReport>
 */
class UserReportResource extends EloquentResource
{
    public function key(): string
    {
        return 'safety';
    }

    protected function model(): string
    {
        return UserReport::class;
    }

    protected function columnSpec(): array
    {
        return [
            'code' => ['Référence'],
            'category' => ['Catégorie', Column::TYPE_BADGE],
            'status' => ['Statut', Column::TYPE_BADGE],
            'description' => ['Description'],
            'created_at' => ['Signalé le', Column::TYPE_DATE],
        ];
    }

    protected function searchable(): array
    {
        return ['code', 'description'];
    }

    protected function searchLabel(): string
    {
        return 'Référence ou description';
    }

    protected function selectFilters(): array
    {
        return [
            'status' => ['Statut', 'status', [
                ['value' => 'pending', 'label' => 'En attente'],
                ['value' => 'reviewed', 'label' => 'Traité'],
                ['value' => 'dismissed', 'label' => 'Écarté'],
            ]],
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'admin_notes' => 'Notes internes',
            'reviewed_at' => 'Traité le',
        ];
    }

    public function actions(): array
    {
        return [
            // Clore un signalement.
            Action::make('resolve', 'Clore le signalement', function (UserReport $report, array $valeurs) {
                app(UserSafetyService::class)->resolveReport(
                    $report,
                    request()->user(),
                    (string) $valeurs['resolution'],
                    $valeurs['notes'] ?? null,
                );

                return ['ok' => true];
            })->requires([
                Field::select('resolution', 'Résolution', [
                    ['value' => 'resolved', 'label' => 'Résolu'],
                    ['value' => 'dismissed', 'label' => 'Sans suite'],
                    ['value' => 'escalated', 'label' => 'Escaladé'],
                ])->rules(['required', 'in:resolved,dismissed,escalated']),
                Field::make('notes', 'Notes', Field::TYPE_TEXTAREA)->rules(['nullable', 'string', 'max:2000']),
            ]),
        ];
    }
}
