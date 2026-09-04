<?php

namespace App\Services\Cerveau;

use App\Models\Booking;
use App\Models\MarketingCampaign;
use App\Models\PromoCode;
use App\Models\Referral;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CE QUE LE MARKETING COÛTE, ET CE QU'IL RAPPORTE.
 *
 * Un code promo se juge sur UNE question : les clients qu'il a fait venir ont-ils rapporté plus
 * que la remise consentie ? Sans cette comparaison, un code réussi et un code ruineux se
 * ressemblent — les deux affichent beaucoup d'utilisations.
 *
 * PAS D'INTELLIGENCE ARTIFICIELLE. Chaque constat est une soustraction qu'on peut refaire à la
 * main, et c'est ce qui permet de le contester.
 */
class AnalyseDuMarketing
{
    private const FENETRE_JOURS = 30;

    /** En dessous, un chiffre ne dit rien : trois utilisations ne font pas une tendance. */
    private const UTILISATIONS_MINIMALES = 10;

    /** @return list<Recommandation> */
    public function recommandations(int $jours = self::FENETRE_JOURS): array
    {
        return [
            ...$this->surLesCodesPromo($jours),
            ...$this->surLesCampagnes(),
            ...$this->surLeParrainage($jours),
        ];
    }

    /**
     * UN CODE PROMO QUI COÛTE PLUS QU'IL NE RAPPORTE.
     *
     * @return list<Recommandation>
     */
    private function surLesCodesPromo(int $jours): array
    {
        if (! Schema::hasTable('promo_code_redemptions')) {
            return [];
        }

        $depuis = now()->subDays($jours);
        $recommandations = [];

        // AGREGAT, PAS MODELE : `count(*) as utilisations` n'est une propriete d'aucun
        // Eloquent. La requete brute rend une ligne, et c'est bien ce qu'on lit.
        $lignes = DB::table('promo_code_redemptions')
            ->where('created_at', '>=', $depuis)
            ->groupBy('promo_code_id')
            ->select([
                'promo_code_id',
                DB::raw('count(*) as utilisations'),
                DB::raw('sum(coalesce(discount_amount, 0)) as remise'),
            ])
            ->get();

        foreach ($lignes as $ligne) {
            $code = PromoCode::find($ligne->promo_code_id);

            if ($code === null || (int) $ligne->utilisations < self::UTILISATIONS_MINIMALES) {
                continue;
            }

            // CE QUE LA PLATEFORME A ENCAISSÉ SUR LES MISSIONS QUI ONT UTILISÉ CE CODE.
            $commission = (int) Booking::query()
                ->whereIn('id', DB::table('promo_code_redemptions')
                    ->where('promo_code_id', $code->id)
                    ->where('created_at', '>=', $depuis)
                    ->pluck('booking_id')
                    ->filter())
                ->sum('platform_fee_cents');

            $remiseCents = (int) round((float) $ligne->remise * 100);

            if ($remiseCents > $commission) {
                $recommandations[] = new Recommandation(
                    domaine: 'marketing',
                    ton: Recommandation::TON_ATTENTION,
                    titre: 'Le code « '.$code->code.' » coûte plus qu’il ne rapporte',
                    constat: $ligne->utilisations.' utilisations sur '.$jours.' jours : '
                        .number_format($remiseCents / 100, 2, ',', ' ').' de remise consentie, '
                        .number_format($commission / 100, 2, ',', ' ').' de commission encaissée.',
                    geste: 'Ce n’est PAS forcément une erreur : un code d’acquisition se paie sur la '
                        .'deuxième commande, pas la première. La vraie question est de savoir combien de '
                        .'ces clients sont revenus SANS code. S’ils ne reviennent pas, le code achète du '
                        .'volume qui repart.',
                    gesteApplicable: RegistreDesGestes::SUSPENDRE_CODE_PROMO,
                    arguments: ['id' => $code->id],
                );
            }
        }

        return $recommandations;
    }

    /**
     * UNE CAMPAGNE QUI TOURNE DEPUIS TROP LONGTEMPS.
     *
     * @return list<Recommandation>
     */
    private function surLesCampagnes(): array
    {
        $recommandations = [];

        $anciennes = MarketingCampaign::query()
            ->where('status', MarketingCampaign::STATUS_RUNNING)
            ->where('started_at', '<', now()->subDays(90))
            ->get();

        foreach ($anciennes as $campagne) {
            $recommandations[] = new Recommandation(
                domaine: 'marketing',
                ton: Recommandation::TON_NEUTRE,
                titre: 'La campagne « '.$campagne->name.' » tourne depuis plus de trois mois',
                constat: 'Démarrée le '.$campagne->started_at?->format('d/m/Y').', toujours en cours.',
                geste: 'Une campagne qui ne s’arrête jamais finit par écrire aux mêmes personnes trop '
                    .'souvent : c’est la première cause de désabonnement, et un désabonné ne revient pas. '
                    .'Regardez sa liste de destinataires avant de la laisser courir.',
                gesteApplicable: RegistreDesGestes::SUSPENDRE_CAMPAGNE,
                arguments: ['id' => $campagne->id],
            );
        }

        return $recommandations;
    }

    /**
     * LE PARRAINAGE QUI NE PRODUIT RIEN.
     *
     * @return list<Recommandation>
     */
    private function surLeParrainage(int $jours): array
    {
        if (! Schema::hasTable('referrals')) {
            return [];
        }

        $depuis = now()->subDays($jours);

        $invites = Referral::query()->where('created_at', '>=', $depuis)->count();

        if ($invites < self::UTILISATIONS_MINIMALES) {
            return [];
        }

        $qualifies = Referral::query()
            ->where('created_at', '>=', $depuis)
            ->whereNotNull('qualifying_booking_id')
            ->count();

        $part = round($qualifies / $invites * 100, 1);

        if ($part >= 15.0) {
            return [];
        }

        return [new Recommandation(
            domaine: 'marketing',
            ton: Recommandation::TON_ATTENTION,
            titre: 'Le parrainage invite beaucoup et convertit peu',
            constat: $invites.' invitations sur '.$jours.' jours, '.$qualifies.' ont abouti à une mission '
                .'('.$part.' %).',
            geste: 'Deux causes possibles, et elles ne se corrigent pas pareil : soit la récompense est '
                .'trop faible pour motiver le filleul, soit le parcours d’inscription le perd en route. '
                .'Regardez d’abord combien se sont inscrits SANS réserver — si beaucoup, le problème est '
                .'le parcours, pas la récompense.',
        )];
    }
}
