<?php

namespace App\Admin\Resources;

use App\Admin\Console\Action;
use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Admin\Console\Field;
use App\Models\ContractSignature;
use App\Services\ContractsV2\ContractService;

/**
 * Les signatures de contrat. POURQUOI CE DESCRIPTEUR EXISTE À CÔTÉ DE CELUI DES DOCUMENTS.
 *
 * @extends EloquentResource<ContractSignature>
 */
class ContractSignatureResource extends EloquentResource
{
    public function key(): string
    {
        return 'contract-signatures';
    }

    protected function model(): string
    {
        return ContractSignature::class;
    }

    protected function columnSpec(): array
    {
        return [
            'signer_name' => ['Signataire'],
            'terms_version' => ['Version des CGU'],
            'signed_at' => ['Signé le', Column::TYPE_DATE],
            'is_invalidated' => ['Invalidée', Column::TYPE_BOOL],
            'country_code' => ['Pays'],
        ];
    }

    protected function searchable(): array
    {
        return ['signer_name', 'terms_version'];
    }

    protected function searchLabel(): string
    {
        return 'Signataire ou version';
    }

    protected function selectFilters(): array
    {
        return [
            'is_invalidated' => ['État', 'is_invalidated', [
                ['value' => '0', 'label' => 'Valide'],
                ['value' => '1', 'label' => 'Invalidée'],
            ]],
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'invalidation_reason' => 'Motif d’invalidation',
            'invalidated_at' => 'Invalidée le',
            'expires_at' => 'Expire le',
        ];
    }

    public function actions(): array
    {
        return [
            Action::make('invalidate', 'Invalider la signature', function (ContractSignature $signature, array $valeurs) {
                app(ContractService::class)->invalidateSignature(
                    $signature,
                    request()->user(),
                    (string) $valeurs['reason'],
                );

                return ['ok' => true];
            })->requires([
                // Le motif est obligatoire, et c'est une exigence de fond plutôt que d'interface : une signature invalidée sans raison ne se défend pas devant un litige.
                Field::make('reason', 'Motif de l’invalidation', Field::TYPE_TEXTAREA)
                    ->rules(['required', 'string', 'min:5', 'max:1000']),
            ])->destructive('La signature sera invalidée. Elle reste conservée avec son motif.'),
        ];
    }
}
