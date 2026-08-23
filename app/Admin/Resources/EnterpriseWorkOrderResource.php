<?php

namespace App\Admin\Resources;

use App\Admin\Console\Action;
use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Admin\Console\Field;
use App\Models\EnterpriseWorkOrder;
use App\Models\WorkOrderApproval;
use App\Services\Contracts\WorkOrderContractService;

/**
 * Les ordres de travail des comptes entreprise. POURQUOI CE DESCRIPTEUR EXISTE.
 *
 * @extends EloquentResource<EnterpriseWorkOrder>
 */
class EnterpriseWorkOrderResource extends EloquentResource
{
    public function key(): string
    {
        return 'b2b-work-orders';
    }

    protected function model(): string
    {
        return EnterpriseWorkOrder::class;
    }

    protected function columnSpec(): array
    {
        return [
            'reference' => ['Référence'],
            'title' => ['Intitulé'],
            'status' => ['Statut', Column::TYPE_BADGE],
            'approval_status' => ['Approbation', Column::TYPE_BADGE],
            'priority' => ['Priorité'],
            'requested_date' => ['Demandé pour', Column::TYPE_DATE],
        ];
    }

    protected function searchable(): array
    {
        return ['reference', 'title'];
    }

    protected function searchLabel(): string
    {
        return 'Référence ou intitulé';
    }

    protected function selectFilters(): array
    {
        return [
            'approval_status' => ['Approbation', 'approval_status', [
                ['value' => 'pending', 'label' => 'En attente'],
                ['value' => 'approved', 'label' => 'Approuvé'],
                ['value' => 'rejected', 'label' => 'Refusé'],
            ]],
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'work_type' => 'Type d’intervention',
            'purchase_order_number' => 'Bon de commande',
            'cost_center' => 'Centre de coût',
            'budget_amount' => 'Budget',
            'instructions' => 'Consignes',
        ];
    }

    public function actions(): array
    {
        return [
            Action::make('approve', 'Approuver l’ordre', function (EnterpriseWorkOrder $ordre) {
                // Le contrat a le dernier mot.
                try {
                    app(WorkOrderContractService::class)->assertApprovable($ordre);
                } catch (\Throwable $e) {
                    return ['ok' => false, 'message' => $e->getMessage()];
                }

                $ordre->forceFill(['approval_status' => 'approved', 'status' => 'scheduled'])->save();

                WorkOrderApproval::create([
                    'enterprise_work_order_id' => $ordre->id,
                    'approver_user_id' => request()->user()?->id,
                    'decision' => 'approved',
                ]);

                return ['ok' => true];
            }),

            Action::make('reject', 'Refuser l’ordre', function (EnterpriseWorkOrder $ordre, array $valeurs) {
                $ordre->forceFill(['approval_status' => 'rejected', 'status' => 'blocked'])->save();

                WorkOrderApproval::create([
                    'enterprise_work_order_id' => $ordre->id,
                    'approver_user_id' => request()->user()?->id,
                    'decision' => 'rejected',
                    'comment' => $valeurs['reason'],
                ]);

                return ['ok' => true];
            })->requires([
                // Un ordre refusé sans motif oblige le client entreprise à rappeler pour savoir
                // quoi corriger — et il rappellera.
                Field::make('reason', 'Motif du refus', Field::TYPE_TEXTAREA)
                    ->rules(['required', 'string', 'min:5', 'max:1000']),
            ])->destructive('L’ordre de travail sera refusé et bloqué.'),
        ];
    }
}
