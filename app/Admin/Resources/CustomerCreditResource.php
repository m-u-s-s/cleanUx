<?php

namespace App\Admin\Resources;

use App\Admin\Console\Action;
use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Admin\Console\Field;
use App\Models\CustomerCredit;
use App\Support\ActivityLogger;

/**
 * Les avoirs et crédits clients.
 *
 * ATTENTION, PIÈGE CONNU DE CE PROJET : le modèle et la table de cette entité ont divergé par le
 * passé. Les colonnes servies ici sont celles du SCHÉMA, vérifiées — et le test de schéma le
 * refera à chaque exécution.
 *
 * Aucune création depuis la console : un avoir naît d’un remboursement ou d’un geste commercial,
 * tous deux journalisés par leur module.
 *
 * @extends EloquentResource<CustomerCredit>
 */
class CustomerCreditResource extends EloquentResource
{
    public function key(): string
    {
        return 'credits';
    }

    protected function model(): string
    {
        return CustomerCredit::class;
    }

    protected function columnSpec(): array
    {
        return [
            'type' => ['Type', Column::TYPE_BADGE],
            'amount' => ['Montant', Column::TYPE_MONEY],
            'remaining_amount' => ['Restant', Column::TYPE_MONEY],
            'status' => ['Statut', Column::TYPE_BADGE],
            'expires_at' => ['Expire le', Column::TYPE_DATE],
        ];
    }

    protected function searchable(): array
    {
        return ['reason', 'notes'];
    }

    protected function searchLabel(): string
    {
        return 'Motif ou notes';
    }

    protected function selectFilters(): array
    {
        return [
            'status' => ['Statut', 'status', [
                ['value' => 'active', 'label' => 'Actif'],
                ['value' => 'used', 'label' => 'Utilisé'],
                ['value' => 'expired', 'label' => 'Expiré'],
            ]],
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'reason' => 'Motif',
            'notes' => 'Notes',
        ];
    }

    public function actions(): array
    {
        return [
            /*
             * Le REFUS est repris du web : seul un crédit actif s'annule. Annuler un crédit déjà
             * consommé remettrait son solde à zéro sans rien rendre au client — une perte qu'il
             * découvrirait à sa prochaine réservation.
             */
            Action::make('cancel', 'Annuler le crédit', function (CustomerCredit $credit, array $valeurs) {
                if ($credit->status !== 'active') {
                    return ['ok' => false, 'message' => 'Seul un crédit actif peut être annulé.'];
                }

                $credit->forceFill([
                    'status' => 'cancelled',
                    'remaining_amount' => 0,
                    'notes' => (string) ($valeurs['reason'] ?? ''),
                ])->save();

                ActivityLogger::log('customer_credit.cancelled', $credit, [
                    'admin_user_id' => request()->user()?->id,
                ]);

                return ['ok' => true];
            })->requires([
                Field::make('reason', 'Motif de l’annulation', Field::TYPE_TEXTAREA)
                    ->rules(['required', 'string', 'min:5', 'max:500']),
            ])->destructive('Le crédit sera annulé et son solde perdu.'),
        ];
    }
}
