<?php

namespace App\Admin\Resources;

use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\ProviderOnboardingDocument;

/**
 * Les pièces déposées par les prestataires pendant leur inscription.
 *
 * La VALIDATION passe par le module d’onboarding, qui débloque les étapes suivantes du parcours.
 * Poser `status = ’approved’` ici laisserait le dossier bloqué à l’étape précédente, sans que
 * le prestataire comprenne pourquoi.
 *
 * @extends EloquentResource<ProviderOnboardingDocument>
 */
class OnboardingDocumentResource extends EloquentResource
{
    public function key(): string
    {
        return 'onboarding-documents';
    }

    protected function model(): string
    {
        return ProviderOnboardingDocument::class;
    }

    protected function columnSpec(): array
    {
        return [
            'document_type' => ['Type', Column::TYPE_BADGE],
            'status' => ['Statut', Column::TYPE_BADGE],
            'file_name' => ['Fichier'],
            'expires_at' => ['Expire le', Column::TYPE_DATE],
            'created_at' => ['Déposé le', Column::TYPE_DATE],
        ];
    }

    protected function searchable(): array
    {
        return ['file_name', 'document_type'];
    }

    protected function searchLabel(): string
    {
        return 'Fichier ou type';
    }

    protected function selectFilters(): array
    {
        return [
            'status' => ['Statut', 'status', [
                ['value' => 'pending', 'label' => 'En attente'],
                ['value' => 'approved', 'label' => 'Validé'],
                ['value' => 'rejected', 'label' => 'Refusé'],
            ]],
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'rejection_reason' => 'Motif de refus',
            'reviewed_at' => 'Revu le',
            'mime_type' => 'Type de fichier',
        ];
    }
}
