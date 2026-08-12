<?php

namespace App\Services\Provider;

use App\Models\ProviderWalletTransaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * L'ASSISTANT FISCAL D'UN INDÉPENDANT (E18).
 *
 * LE MOMENT OÙ ÇA FAIT MAL. Un indépendant reçoit sa déclaration en avril et doit retrouver ce qu'il
 * a encaissé l'année précédente, plateforme par plateforme. Les données sont là — le registre de
 * portefeuille est immuable et daté — et il n'existe aucun moyen de les sortir autrement qu'en
 * additionnant des virements à la main.
 *
 * L'ESTIMATION DE CHARGES EST UNE ESTIMATION, ET LE MOT COMPTE. Les taux dépendent du statut, du
 * pays, du chiffre d'affaires, d'exonérations de début d'activité. Annoncer un montant à payer sans
 * le dire ferait provisionner faux — dans un sens ou dans l'autre, et le mauvais sens se découvre
 * au moment de payer. Le résultat porte donc `is_estimate` et le taux employé, pour que l'écran
 * puisse le dire plutôt que d'afficher un chiffre qui ressemble à un avis d'imposition.
 *
 * ON COMPTE LES ENCAISSEMENTS NETS DE REPRISES. Un remboursement au client reprend une part au
 * prestataire : l'ignorer gonflerait le revenu déclaré d'un argent qu'il n'a jamais gardé.
 */
class TaxSummaryService
{
    /**
     * Le taux de charges retenu à défaut, en pourcentage.
     *
     * DÉLIBÉRÉMENT PRUDENT — surestimer fait provisionner un peu trop, ce qui se corrige en
     * dépensant ; sous-estimer fait découvrir un trou au moment de payer, ce qui ne se corrige pas.
     */
    public const TAUX_DE_CHARGES_DEFAUT = 22.0;

    /**
     * Le résumé d'une année.
     *
     * @return array<string, mixed>
     */
    public function pourLAnnee(User $prestataire, int $annee): array
    {
        $debut = Carbon::create($annee, 1, 1)->startOfDay();
        $fin = Carbon::create($annee, 12, 31)->endOfDay();

        $lignes = ProviderWalletTransaction::query()
            ->where('provider_user_id', $prestataire->id)
            ->whereBetween('created_at', [$debut, $fin])
            ->get(['type', 'direction', 'amount', 'created_at']);

        $revenus = $this->sommeDe($lignes, [
            ProviderWalletTransaction::TYPE_EARNING,
            ProviderWalletTransaction::TYPE_TIP,
            ProviderWalletTransaction::TYPE_BONUS,
        ]);

        /*
         * LES REPRISES SE DÉDUISENT. Un remboursement au client reprend une part au prestataire :
         * l'ignorer gonflerait le revenu déclaré d'un argent qu'il n'a jamais gardé.
         */
        $reprises = $this->sommeDe($lignes, [ProviderWalletTransaction::TYPE_REFUND_CLAWBACK]);

        $net = max(0, $revenus - $reprises);
        $taux = $this->tauxDeCharges($prestataire);

        return [
            'year' => $annee,
            'gross_cents' => $revenus,
            'clawback_cents' => $reprises,
            'net_cents' => $net,
            'estimated_charges_cents' => (int) round($net * $taux / 100),
            'charges_rate_percent' => $taux,
            /*
             * `is_estimate` VOYAGE AVEC LE CHIFFRE. Les taux dépendent du statut, du pays, du
             * chiffre d'affaires : annoncer un montant sans le dire ferait provisionner faux, et
             * le mauvais sens se découvre au moment de payer.
             */
            'is_estimate' => true,
            'by_month' => $this->parMois($lignes, $annee),
        ];
    }

    /**
     * L'export CSV d'une année — le fichier qu'on donne à son comptable.
     *
     * POINT-VIRGULE : les tableurs francophones ouvrent le CSV avec le séparateur régional, et une
     * virgule empilerait tout dans la première colonne.
     *
     * @return array{filename: string, content: string}
     */
    public function csv(User $prestataire, int $annee): array
    {
        $resume = $this->pourLAnnee($prestataire, $annee);

        $lignes = ['mois;encaissements;reprises;net'];

        foreach ($resume['by_month'] as $mois) {
            $lignes[] = implode(';', [
                $mois['month'],
                number_format($mois['gross_cents'] / 100, 2, ',', ''),
                number_format($mois['clawback_cents'] / 100, 2, ',', ''),
                number_format($mois['net_cents'] / 100, 2, ',', ''),
            ]);
        }

        $lignes[] = '';
        $lignes[] = 'TOTAL;'.number_format($resume['gross_cents'] / 100, 2, ',', '')
            .';'.number_format($resume['clawback_cents'] / 100, 2, ',', '')
            .';'.number_format($resume['net_cents'] / 100, 2, ',', '');
        // La ligne d'estimation porte son avertissement DANS le fichier : un CSV se transmet, et
        // se lit sans l'écran qui l'accompagnait.
        $lignes[] = 'CHARGES ESTIMEES ('.$resume['charges_rate_percent'].' %) — estimation, a verifier avec votre comptable;;;'
            .number_format($resume['estimated_charges_cents'] / 100, 2, ',', '');

        return [
            'filename' => sprintf('revenus-%d.csv', $annee),
            'content' => implode("\n", $lignes)."\n",
        ];
    }

    /**
     * @param  Collection<int, ProviderWalletTransaction>  $lignes
     * @return list<array<string, mixed>>
     */
    protected function parMois(Collection $lignes, int $annee): array
    {
        $mois = [];

        for ($i = 1; $i <= 12; $i++) {
            $duMois = $lignes->filter(
                fn (ProviderWalletTransaction $ligne) => (int) $ligne->created_at?->month === $i,
            );

            $revenus = $this->sommeDe($duMois, [
                ProviderWalletTransaction::TYPE_EARNING,
                ProviderWalletTransaction::TYPE_TIP,
                ProviderWalletTransaction::TYPE_BONUS,
            ]);
            $reprises = $this->sommeDe($duMois, [ProviderWalletTransaction::TYPE_REFUND_CLAWBACK]);

            // TOUS LES MOIS SONT RENDUS, même vides : un tableau à trous laisse croire qu'il manque
            // des données plutôt qu'il ne s'est rien passé.
            $mois[] = [
                'month' => sprintf('%d-%02d', $annee, $i),
                'gross_cents' => $revenus,
                'clawback_cents' => $reprises,
                'net_cents' => max(0, $revenus - $reprises),
            ];
        }

        return $mois;
    }

    /**
     * @param  Collection<int, ProviderWalletTransaction>  $lignes
     * @param  list<string>  $types
     */
    protected function sommeDe(Collection $lignes, array $types): int
    {
        return (int) round($lignes
            ->filter(fn (ProviderWalletTransaction $ligne) => in_array($ligne->type, $types, true))
            ->sum(fn (ProviderWalletTransaction $ligne) => abs((float) $ligne->amount)) * 100);
    }

    /**
     * Le taux retenu — celui que le prestataire a déclaré, à défaut le défaut prudent.
     *
     * Il vit dans les métadonnées du profil : la plateforme n'a aucun moyen de connaître le statut
     * fiscal de quelqu'un, et prétendre le déduire produirait un chiffre faux avec l'air d'être sûr.
     */
    protected function tauxDeCharges(User $prestataire): float
    {
        $declare = data_get($prestataire->providerProfile?->metadata, 'tax.charges_rate_percent');

        return is_numeric($declare) && (float) $declare > 0
            ? round((float) $declare, 2)
            : self::TAUX_DE_CHARGES_DEFAUT;
    }
}
