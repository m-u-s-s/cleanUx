<?php

namespace App\Admin\Resources;

use App\Admin\Console\Action;
use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\AuditEvent;
use App\Services\Audit\AuditService;

/**
 * Le journal d’audit.
 *
 * LECTURE SEULE, sans exception. Un journal d’audit modifiable depuis une console
 * d’administration ne vaut rien : c’est précisément contre les actes d’administration qu’il
 * existe. L'épinglage et l’export restent sur la page web, qui les journalise a leur tour.
 *
 * @extends EloquentResource<AuditEvent>
 */
class AuditEventResource extends EloquentResource
{
    public function key(): string
    {
        return 'audit';
    }

    protected function model(): string
    {
        return AuditEvent::class;
    }

    protected function columnSpec(): array
    {
        return [
            'event_type' => ['Événement'],
            'domain' => ['Domaine', Column::TYPE_BADGE],
            'severity' => ['Gravité', Column::TYPE_BADGE],
            'actor_label' => ['Acteur'],
            'occurred_at' => ['Survenu le', Column::TYPE_DATETIME],
        ];
    }

    protected function searchable(): array
    {
        return ['event_type', 'actor_label', 'subject_label'];
    }

    protected function searchLabel(): string
    {
        return 'Événement, acteur ou sujet';
    }

    protected function selectFilters(): array
    {
        return [
            'severity' => ['Gravité', 'severity', [
                ['value' => 'info', 'label' => 'Information'],
                ['value' => 'warning', 'label' => 'Avertissement'],
                ['value' => 'critical', 'label' => 'Critique'],
            ]],
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'subject_label' => 'Sujet',
            'route_name' => 'Route',
            'is_pinned' => 'Épinglé',
        ];
    }

    public function actions(): array
    {
        return [
            /*
             * Épingler un évènement le SOUSTRAIT à la purge de rétention. C'est le geste qu'on
             * fait quand une trace devient une pièce : un litige, un contrôle. Le perdre au
             * prochain nettoyage serait irréparable, et c'est pourquoi il vit ici.
             */
            Action::make('toggle-pin', 'Épingler / désépingler', function (AuditEvent $event) {
                $event->is_pinned
                    ? app(AuditService::class)->unpin($event)
                    : app(AuditService::class)->pin($event);

                return ['is_pinned' => (bool) $event->fresh()->is_pinned];
            }),
        ];
    }
}
