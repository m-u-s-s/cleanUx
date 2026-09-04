<?php

namespace App\Services\Cerveau;

use App\Models\RiskEvaluation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * LES COMPORTEMENTS QUI MÉRITENT UN REGARD — pas un verdict.
 *
 * « Ce client a annulé huit des neuf dernières missions après affectation » est un FAIT. En
 * conclure qu'il fraude est une DÉCISION, et elle appartient à un humain : un compte suspendu à
 * tort, c'est un client perdu, un litige, et souvent un avis public.
 *
 * Le seul geste proposé est donc la MISE EN REVUE — jamais la suspension, jamais le remboursement.
 *
 * CHAQUE SIGNAL EST UNE SOUSTRACTION QU'ON PEUT REFAIRE À LA MAIN. C'est la condition pour qu'un
 * accusé puisse se défendre, et pour que la plateforme puisse justifier sa décision.
 */
class AnalyseDeLaFraude
{
    private const FENETRE_JOURS = 30;

    /** En dessous, un motif n'existe pas : deux annulations ne sont pas un comportement. */
    private const MISSIONS_MINIMALES = 5;

    /** @return list<Recommandation> */
    public function recommandations(int $jours = self::FENETRE_JOURS): array
    {
        return [
            ...$this->clientsQuiAnnulentTrop($jours),
            ...$this->prestatairesQuiSeDesistentTrop($jours),
            ...$this->parrainagesEnChaine($jours),
            ...$this->codesPromoConcentres($jours),
            ...$this->evaluationsDeRisqueSansSuite(),
        ];
    }

    /**
     * LE CLIENT QUI ANNULE APRÈS AFFECTATION.
     *
     * C'est le motif coûteux : le prestataire s'est déplacé ou a bloqué son créneau. Annuler
     * AVANT affectation ne coûte à personne, et ne dit rien.
     *
     * @return list<Recommandation>
     */
    private function clientsQuiAnnulentTrop(int $jours): array
    {
        $depuis = now()->subDays($jours);

        // AGREGAT, PAS MODELE : les colonnes calculees n'existent sur aucun Eloquent.
        $lignes = DB::table('bookings')
            ->where('created_at', '>=', $depuis)
            ->whereNotNull('client_id')
            ->groupBy('client_id')
            ->select([
                'client_id',
                DB::raw('count(*) as total'),
                DB::raw('sum(case when cancelled_at is not null and assigned_provider_user_id is not null then 1 else 0 end) as annulees_apres_affectation'),
            ])
            ->havingRaw('count(*) >= ?', [self::MISSIONS_MINIMALES])
            ->get();

        $recommandations = [];

        foreach ($lignes as $ligne) {
            $part = (int) $ligne->total > 0
                ? round((int) $ligne->annulees_apres_affectation / (int) $ligne->total * 100, 1)
                : 0.0;

            if ($part < 60.0) {
                continue;
            }

            $client = User::find($ligne->client_id);

            if ($client === null) {
                continue;
            }

            $recommandations[] = new Recommandation(
                domaine: 'fraude',
                ton: Recommandation::TON_ATTENTION,
                titre: $client->name.' annule presque toujours après affectation',
                constat: $ligne->annulees_apres_affectation.' annulations sur '.$ligne->total.' missions ('
                    .$part.' %), toutes après qu’un prestataire ait été désigné.',
                geste: 'Ce n’est pas forcément de la fraude : un client dont les créneaux ne conviennent '
                    .'jamais annule pour la même raison qu’un fraudeur. Regardez le DÉLAI entre '
                    .'l’affectation et l’annulation — quelques secondes évoquent un test de la plateforme, '
                    .'quelques heures évoquent un empêchement réel.',
                gesteApplicable: RegistreDesGestes::METTRE_EN_REVUE,
                arguments: [
                    'user_id' => $client->id,
                    'motif' => $part.' % d’annulations après affectation sur '.$ligne->total.' missions.',
                ],
            );
        }

        return $recommandations;
    }

    /**
     * LE PRESTATAIRE QUI ACCEPTE PUIS SE DÉSISTE.
     *
     * Accepter réserve la mission et écarte les autres candidats : se désister ensuite coûte au
     * client une attente qu'il n'aurait pas eue si personne n'avait accepté.
     *
     * @return list<Recommandation>
     */
    private function prestatairesQuiSeDesistentTrop(int $jours): array
    {
        $depuis = now()->subDays($jours);

        $lignes = DB::table('bookings')
            ->where('created_at', '>=', $depuis)
            ->whereNotNull('assigned_provider_user_id')
            ->groupBy('assigned_provider_user_id')
            ->select([
                'assigned_provider_user_id',
                DB::raw('count(*) as total'),
                DB::raw('sum(case when cancelled_at is not null then 1 else 0 end) as abandonnees'),
            ])
            ->havingRaw('count(*) >= ?', [self::MISSIONS_MINIMALES])
            ->get();

        $recommandations = [];

        foreach ($lignes as $ligne) {
            $part = (int) $ligne->total > 0
                ? round((int) $ligne->abandonnees / (int) $ligne->total * 100, 1)
                : 0.0;

            if ($part < 40.0) {
                continue;
            }

            $prestataire = User::find($ligne->assigned_provider_user_id);

            if ($prestataire === null) {
                continue;
            }

            $recommandations[] = new Recommandation(
                domaine: 'fraude',
                ton: Recommandation::TON_ATTENTION,
                titre: $prestataire->name.' accepte puis abandonne '.$part.' % de ses missions',
                constat: $ligne->abandonnees.' abandons sur '.$ligne->total.' missions acceptées.',
                geste: 'Accepter écarte les autres candidats : chaque abandon coûte au client une attente '
                    .'qu’il n’aurait pas eue. Avant de sanctionner, vérifiez s’il accepte des missions trop '
                    .'loin de lui — le problème serait alors le rayon qu’il a réglé, pas sa bonne foi.',
                gesteApplicable: RegistreDesGestes::METTRE_EN_REVUE,
                arguments: [
                    'user_id' => $prestataire->id,
                    'motif' => $part.' % d’abandons après acceptation sur '.$ligne->total.' missions.',
                ],
            );
        }

        return $recommandations;
    }

    /**
     * UN PARRAIN, BEAUCOUP DE FILLEULS, AUCUNE MISSION.
     *
     * Le motif classique de l'abus de parrainage : des comptes créés pour toucher la prime.
     *
     * @return list<Recommandation>
     */
    private function parrainagesEnChaine(int $jours): array
    {
        if (! Schema::hasTable('referrals')) {
            return [];
        }

        $depuis = now()->subDays($jours);

        $lignes = DB::table('referrals')
            ->where('created_at', '>=', $depuis)
            ->groupBy('referrer_user_id')
            ->select([
                'referrer_user_id',
                DB::raw('count(*) as filleuls'),
                DB::raw('sum(case when qualifying_booking_id is not null then 1 else 0 end) as aboutis'),
            ])
            ->havingRaw('count(*) >= ?', [10])
            ->get();

        $recommandations = [];

        foreach ($lignes as $ligne) {
            if ((int) $ligne->aboutis > 1) {
                continue;
            }

            $parrain = User::find($ligne->referrer_user_id);

            if ($parrain === null) {
                continue;
            }

            $recommandations[] = new Recommandation(
                domaine: 'fraude',
                ton: Recommandation::TON_DANGER,
                titre: $parrain->name.' a parrainé '.$ligne->filleuls.' comptes, aucun n’a réservé',
                constat: $ligne->filleuls.' filleuls sur '.$jours.' jours, '.$ligne->aboutis.' mission(s) '
                    .'à l’arrivée.',
                geste: 'Beaucoup d’inscriptions sans aucune mission est le motif classique des comptes '
                    .'créés pour la prime. Comparez les adresses e-mail et les moyens de paiement des '
                    .'filleuls : s’ils se ressemblent, la question est tranchée. S’ils diffèrent tous, il '
                    .'peut s’agir d’un vrai relais d’influence — et il vaut mieux le garder.',
                gesteApplicable: RegistreDesGestes::METTRE_EN_REVUE,
                arguments: [
                    'user_id' => $parrain->id,
                    'motif' => $ligne->filleuls.' filleuls, '.$ligne->aboutis.' mission(s).',
                ],
            );
        }

        return $recommandations;
    }

    /**
     * UN CODE PROMO UTILISÉ EN BOUCLE PAR LES MÊMES PERSONNES.
     *
     * @return list<Recommandation>
     */
    private function codesPromoConcentres(int $jours): array
    {
        if (! Schema::hasTable('promo_code_redemptions')) {
            return [];
        }

        $depuis = now()->subDays($jours);

        $lignes = DB::table('promo_code_redemptions')
            ->where('created_at', '>=', $depuis)
            ->groupBy('promo_code_id')
            ->select([
                'promo_code_id',
                DB::raw('count(*) as utilisations'),
                DB::raw('count(distinct user_id) as personnes'),
            ])
            ->havingRaw('count(*) >= ?', [20])
            ->get();

        $recommandations = [];

        foreach ($lignes as $ligne) {
            $personnes = max(1, (int) $ligne->personnes);
            $parPersonne = round((int) $ligne->utilisations / $personnes, 1);

            if ($parPersonne < 4.0) {
                continue;
            }

            $recommandations[] = new Recommandation(
                domaine: 'fraude',
                ton: Recommandation::TON_ATTENTION,
                titre: 'Un code promo concentré sur très peu de personnes',
                constat: $ligne->utilisations.' utilisations pour seulement '.$personnes.' personne(s), '
                    .'soit '.$parPersonne.' par personne.',
                geste: 'Un code destiné à faire venir de NOUVEAUX clients qui sert quatre fois à la même '
                    .'personne ne fait plus venir personne : il fait une remise permanente. Vérifiez si le '
                    .'code porte une limite par compte — c’est souvent elle qui manque.',
                gesteApplicable: RegistreDesGestes::SUSPENDRE_CODE_PROMO,
                arguments: ['id' => $ligne->promo_code_id],
            );
        }

        return $recommandations;
    }

    /**
     * LES ÉVALUATIONS DE RISQUE QUE PERSONNE N'A OUVERTES.
     *
     * Le moteur de risque tourne déjà. Une pile qui grossit sans que personne l'ouvre est pire
     * qu'une absence de moteur : elle donne le sentiment d'être protégé.
     *
     * @return list<Recommandation>
     */
    private function evaluationsDeRisqueSansSuite(): array
    {
        if (! Schema::hasTable('risk_evaluations')) {
            return [];
        }

        $enAttente = RiskEvaluation::query()
            ->where('created_at', '>=', now()->subDays(self::FENETRE_JOURS))
            ->whereIn('decision', ['review', 'challenge'])
            ->count();

        if ($enAttente < 5) {
            return [];
        }

        return [new Recommandation(
            domaine: 'fraude',
            ton: Recommandation::TON_DANGER,
            titre: $enAttente.' évaluations de risque attendent un examen',
            constat: 'Le moteur les a marquées « à revoir » sur les '.self::FENETRE_JOURS.' derniers jours.',
            geste: 'Une pile qui grossit sans que personne l’ouvre est PIRE qu’une absence de moteur : '
                .'elle donne le sentiment d’être protégé. Soit quelqu’un les traite, soit il faut relever '
                .'le seuil pour que le moteur n’en produise que ce qu’on peut lire.',
        )];
    }
}
