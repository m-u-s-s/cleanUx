<?php

namespace App\Services\Enterprise;

use App\Models\FinanceInvoice;
use App\Models\User;
use App\Support\Finance\ClientFinanceDocumentScope;
use Illuminate\Support\Carbon;

/**
 * L'EXPORT COMPTABLE EN LIBRE-SERVICE D'UNE ENTREPRISE CLIENTE (E11).
 *
 * CE QUI MANQUAIT N'EST PAS LE MOTEUR. `AccountingV2` sait produire du FEC, du Sage, du QuickBooks —
 * pour la PLATEFORME. Une entreprise cliente qui veut donner ses factures à son comptable n'a que
 * les téléchargements un par un : douze PDF par mois, à ressaisir à la main.
 *
 * ON NE RÉUTILISE PAS `ExportManager`, ET C'EST DÉLIBÉRÉ. Il exporte le grand livre de la
 * plateforme — toutes sociétés confondues, sans notion d'organisation. L'ouvrir à un client
 * exposerait la comptabilité de ses concurrents. Ce service part des FACTURES de cette société-là,
 * et d'elles seules.
 *
 * LA PORTÉE PASSE PAR `ClientFinanceDocumentScope`, la même qui garde l'écran de facturation. Deux
 * lectures divergentes de « quelles factures sont les miennes » finiraient par diverger — et c'est
 * toujours celle qu'on n'a pas testée qui fuit.
 *
 * POINT-VIRGULE ET NON VIRGULE : les tableurs francophones ouvrent le CSV avec le séparateur
 * régional, et une virgule empilerait tout dans la première colonne. Ce fichier est destiné à être
 * ouvert par un humain avant d'être versé à un logiciel comptable.
 */
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
     * TABULATION COMME SÉPARATEUR, parce que la norme FEC l'impose : produire un « FEC » en
     * point-virgule donnerait un fichier refusé au dépôt, après que le client aura cru l'avoir.
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
     * @return \Illuminate\Support\Collection<int, FinanceInvoice>
     */
    protected function facturesDe(User $acteur, Carbon $debut, Carbon $fin): \Illuminate\Support\Collection
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
