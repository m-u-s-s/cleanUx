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
 * Les ordres de travail des comptes entreprise.
 *
 * POURQUOI CE DESCRIPTEUR EXISTE. La page « Opérations B2B » est un tableau de bord : elle gère les
 * CONTRATS, les ORDRES DE TRAVAIL et les GRILLES TARIFAIRES. Un descripteur ne sert qu'un modèle ;
 * seul celui des grilles existait, et les deux gestes les plus fréquents de la page — approuver ou
 * refuser un ordre de travail — n'avaient nulle part où vivre.
 *
 * L'APPROBATION CONSULTE LE CONTRAT AVANT D'ÉCRIRE. Un ordre de travail peut dépasser le budget
 * mensuel, sortir du catalogue autorisé, ou exiger un bon de commande absent : le service tranche,
 * et son refus est rendu tel quel. Écrire `approval_status` sans lui demander ferait passer des
 * ordres que le contrat interdit — et la facture le découvrirait à la fin du mois.
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
                /*
                 * Le contrat a le dernier mot. Le service lève si l'ordre le viole — budget
                 * dépassé, service hors catalogue, bon de commande manquant — et on rend son
                 * message plutôt qu'un « approuvé » qui serait faux.
                 */
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
