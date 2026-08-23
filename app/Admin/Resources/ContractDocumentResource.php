<?php

namespace App\Admin\Resources;

use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\ContractDocument;

/**
 * Les contrats générés et leur état de signature.
 *
 * @extends EloquentResource<ContractDocument>
 */
class ContractDocumentResource extends EloquentResource
{
    public function key(): string
    {
        return 'contracts';
    }

    protected function model(): string
    {
        return ContractDocument::class;
    }

    protected function columnSpec(): array
    {
        return [
            'code' => ['Référence'],
            'status' => ['Statut', Column::TYPE_BADGE],
            'generated_at' => ['Généré le', Column::TYPE_DATETIME],
            'expires_at' => ['Expire le', Column::TYPE_DATE],
            'created_at' => ['Créé le', Column::TYPE_DATE],
        ];
    }

    protected function searchable(): array
    {
        return ['code'];
    }

    protected function searchLabel(): string
    {
        return 'Référence';
    }

    protected function selectFilters(): array
    {
        return [
            'status' => ['Statut', 'status', [
                ['value' => 'draft', 'label' => 'Brouillon'],
                ['value' => 'sent', 'label' => 'Envoyé'],
                ['value' => 'signed', 'label' => 'Signé'],
                ['value' => 'expired', 'label' => 'Expiré'],
            ]],
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'pdf_path' => 'Fichier PDF',
        ];
    }
}
