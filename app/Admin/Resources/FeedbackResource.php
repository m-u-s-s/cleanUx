<?php

namespace App\Admin\Resources;

use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\Feedback;

/**
 * Les retours laisses après une intervention.
 *
 * @extends EloquentResource<Feedback>
 */
class FeedbackResource extends EloquentResource
{
    public function key(): string
    {
        return 'feedbacks';
    }

    protected function model(): string
    {
        return Feedback::class;
    }

    protected function columnSpec(): array
    {
        return [
            'rating' => ['Note', Column::TYPE_NUMBER],
            'status' => ['Statut', Column::TYPE_BADGE],
            'comment' => ['Commentaire'],
            'is_public' => ['Public', Column::TYPE_BOOL],
            'created_at' => ['Laissé le', Column::TYPE_DATE],
        ];
    }

    protected function searchable(): array
    {
        return ['comment', 'commentaire'];
    }

    protected function searchLabel(): string
    {
        return 'Commentaire';
    }

    protected function detailSpec(): array
    {
        return [
            'reports_count' => 'Signalements',
            'hidden_reason' => 'Motif de masquage',
        ];
    }
}
