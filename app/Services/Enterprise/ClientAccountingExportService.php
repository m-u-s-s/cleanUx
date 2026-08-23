<?php

namespace App\Services\Enterprise;

use App\Models\FinanceInvoice;
use App\Models\User;
use App\Support\Finance\ClientFinanceDocumentScope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** L'EXPORT COMPTABLE EN LIBRE-SERVICE D'UNE ENTREPRISE CLIENTE (E11). */
class ClientAccountingExportService
{
    /**
     * L'export CSV des factures d'une période.
     *
     * @return array{filename: string, content: string, rows: int}
     */
    public function csv(User $acteur, Carbon $debut, Carbon $fin): array
    {
        $factures = $this->facturesDe($acteur, $debut, $fin);

        $lignes = ['numero;date_emission;echeance;statut;ht;tva;ttc;devise;reference_intervention'];

        foreach ($factures as $facture) {
            $lignes[] = implode(';', [
                $this->propre((string) $facture->invoice_number),
                $facture->issued_at?->toDateString() ?? '',
                $facture->due_at?->toDateString() ?? '',
                $this->propre((string) $facture->status),
                $this->montant($facture->subtotal),
                $this->montant($facture->tax_amount),
                $this->montant($facture->total_amount),
                $this->propre((string) ($facture->currency ?? 'EUR')),
                $this->propre((string) ($facture->rendezVous->booking_reference ?? '')),
            ]);
        }

        return [
            'filename' => sprintf('factures-%s-%s.csv', $debut->toDateString(), $fin->toDateString()),
            'content' => implode("\n", $lignes)."\n",
            'rows' => $factures->count(),
        ];
    }

    /**
     * L'export FEC — le format que réclame un expert-comptable français ou belge.
     *
     * @return array{filename: string, content: string, rows: int}
     */
    public function fec(User $acteur, Carbon $debut, Carbon $fin): array
    {
        $factures = $this->facturesDe($acteur, $debut, $fin);

        $entetes = [
            'JournalCode', 'JournalLib', 'EcritureNum', 'EcritureDate', 'CompteNum', 'CompteLib',
            'PieceRef', 'PieceDate', 'EcritureLib', 'Debit', 'Credit', 'Montantdevise', 'Idevise',
        ];

        $lignes = [implode("\t", $entetes)];

        foreach ($factures as $index => $facture) {
            $date = $facture->issued_at?->format('Ymd') ?? $debut->format('Ymd');
            $numero = str_pad((string) ($index + 1), 6, '0', STR_PAD_LEFT);

            $lignes[] = implode("\t", [
                'VE',
                'Ventes',
                $numero,
                $date,
                // 401 : fournisseurs. C'est bien la position du CLIENT dans sa propre comptabilité
                // — la plateforme lui vend un service.
                '401000',
                'Fournisseurs',
                $this->propre((string) $facture->invoice_number),
                $date,
                'Prestation '.$this->propre((string) ($facture->rendezVous->booking_reference ?? $facture->invoice_number)),
                '0,00',
                $this->montant($facture->total_amount),
                $this->montant($facture->total_amount),
                (string) ($facture->currency ?? 'EUR'),
            ]);
        }

        return [
            'filename' => sprintf('FEC-%s-%s.txt', $debut->format('Ymd'), $fin->format('Ymd')),
            'content' => implode("\n", $lignes)."\n",
            'rows' => $factures->count(),
        ];
    }

    /**
     * Les factures de CETTE société, et d'elles seules.
     *
     * @return Collection<int, FinanceInvoice>
     */
    protected function facturesDe(User $acteur, Carbon $debut, Carbon $fin): Collection
    {
        return ClientFinanceDocumentScope::apply(FinanceInvoice::query(), $acteur)
            ->whereBetween('issued_at', [$debut->startOfDay(), $fin->endOfDay()])
            ->with('rendezVous:id,booking_reference')
            ->orderBy('issued_at')
            ->get();
    }

    /** Le séparateur ne doit jamais survivre dans une valeur : il couperait la ligne. */
    protected function propre(string $valeur): string
    {
        return str_replace([';', "\t", "\n", "\r"], ' ', $valeur);
    }

    /** La virgule décimale, attendue par les tableurs et les logiciels comptables francophones. */
    protected function montant(mixed $valeur): string
    {
        return number_format((float) $valeur, 2, ',', '');
    }
}
