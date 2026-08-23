<?php

namespace App\Admin\Resources;

use App\Admin\Console\Action;
use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Admin\Console\Field;
use App\Models\ProviderOnboardingDocument;
use App\Services\Onboarding\ProviderOnboardingService;

/**
 * Les pièces déposées par les prestataires pendant leur inscription.
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

    public function actions(): array
    {
        return [
            Action::make('approve', 'Approuver le document', function (ProviderOnboardingDocument $document) {
                app(ProviderOnboardingService::class)->reviewDocument($document, request()->user(), true);

                return ['ok' => true];
            }),

            // LE MOTIF EST OBLIGATOIRE, et long d'au moins cinq caractères comme sur le web.
            Action::make('reject', 'Refuser le document', function (ProviderOnboardingDocument $document, array $valeurs) {
                app(ProviderOnboardingService::class)->reviewDocument(
                    $document,
                    request()->user(),
                    false,
                    (string) $valeurs['reason'],
                );

                return ['ok' => true];
            })->requires([
                Field::make('reason', 'Motif du refus', Field::TYPE_TEXTAREA)
                    ->rules(['required', 'string', 'min:5', 'max:500']),
            ]),
        ];
    }
}
