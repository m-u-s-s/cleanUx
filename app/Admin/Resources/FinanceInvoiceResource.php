<?php

namespace App\Admin\Resources;

use App\Admin\Console\Action;
use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\FinanceInvoice;
use App\Services\Finance\FinanceDocumentService;

/**
 * Les factures, dont la facturation mensuelle B2B.
 *
 * @extends EloquentResource<FinanceInvoice>
 */
class FinanceInvoiceResource extends EloquentResource
{
    public function key(): string
    {
        return 'b2b-invoices';
    }

    protected function model(): string
    {
        return FinanceInvoice::class;
    }

    protected function columnSpec(): array
    {
        return [
            'invoice_number' => ['Numéro'],
            'status' => ['Statut', Column::TYPE_BADGE],
            'total_amount' => ['Total', Column::TYPE_MONEY],
            'balance_due' => ['Reste dû', Column::TYPE_MONEY],
            'issued_at' => ['Émise le', Column::TYPE_DATE],
        ];
    }

    protected function searchable(): array
    {
        return ['invoice_number'];
    }

    protected function searchLabel(): string
    {
        return 'Numéro de facture';
    }

    protected function selectFilters(): array
    {
        return [
            'status' => ['Statut', 'status', [
                ['value' => 'draft', 'label' => 'Brouillon'],
                ['value' => 'issued', 'label' => 'Émise'],
                ['value' => 'paid', 'label' => 'Payée'],
                ['value' => 'overdue', 'label' => 'En retard'],
                ['value' => 'cancelled', 'label' => 'Annulée'],
            ]],
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'due_at' => 'Échéance',
            'paid_amount' => 'Payé',
            'tax_amount' => 'TVA',
            'currency' => 'Devise',
        ];
    }

    /**
     * LES DEUX GESTES DU WEB, PORTÉS TELS QUELS.
     *
     * Ils passent par `FinanceDocumentService`, exactement comme l'écran web : le solde et le
     * statut se recalculent seuls. Un montant PARTIEL reste réservé au web — une action de console
     * ne reçoit que le modèle, pas de saisie.
     */
    public function actions(): array
    {
        return [
            Action::make('record_payment', 'Marquer payée', function (FinanceInvoice $model) {
                $solde = (float) $model->balance_due;

                if ($solde <= 0) {
                    return ['ok' => false, 'message' => 'Cette facture est déjà soldée.'];
                }

                app(FinanceDocumentService::class)->recordPayment($model, $solde, ['method' => 'manual']);

                return ['ok' => true, 'message' => 'Paiement enregistré.'];
            })->destructive('Le solde entier sera enregistré comme payé. Pour un montant partiel, employez l’écran web.'),

            Action::make('send_reminder', 'Relancer', function (FinanceInvoice $model) {
                if ((float) $model->balance_due <= 0) {
                    return ['ok' => false, 'message' => 'Rien à relancer : la facture est soldée.'];
                }

                $relance = app(FinanceDocumentService::class)->sendReminder($model);

                return $relance->status === 'sent'
                    ? ['ok' => true, 'message' => 'Relance envoyée.']
                    : ['ok' => false, 'message' => 'Relance enregistrée, envoi impossible.'];
            }),
        ];
    }
}
