<?php

namespace App\Services\Commission;

use App\Models\Booking;
use App\Models\CommissionRule;
use App\Models\Trade;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * LE CONSEILLER — il lit les chiffres et pose les bonnes questions.
 *
 * PAS D'INTELLIGENCE ARTIFICIELLE, ET C'EST VOULU. Une recommandation sur l'argent doit être
 * EXPLICABLE : « ce métier annule quatre fois plus que la moyenne, à 25 % de commission » se
 * vérifie et se conteste. Un avis qu'on ne peut pas vérifier ne se conteste pas — il s'obéit ou
 * il s'ignore, et les deux sont mauvais.
 *
 * Chaque conseil porte donc son CHIFFRE, sa RÈGLE de déclenchement et son GESTE. Aucun ne
 * s'applique tout seul.
 */
class ConseillerDeCommission
{
    /** En dessous, un chiffre ne veut rien dire : deux missions ne font pas une tendance. */
    private const VOLUME_MINIMAL = 5;

    private const FENETRE_JOURS = 30;

    /**
     * CE QUE CHAQUE MÉTIER RAPPORTE VRAIMENT.
     *
     * @return Collection<int, array{trade_id: int, metier: string, volume: int,
     *     commission_cents: int, volume_affaires_cents: int, taux_effectif: float,
     *     taux_regle: float, annulees: int, part_annulee: float, sans_prestataire: int,
     *     part_sans_prestataire: float}>
     */
    public function parMetier(int $jours = self::FENETRE_JOURS): Collection
    {
        $depuis = now()->subDays($jours);

        /** @var Collection<int, object> $lignes */
        $lignes = Booking::query()
            ->whereNotNull('trade_id')
            ->where('created_at', '>=', $depuis)
            ->groupBy('trade_id')
            ->select([
                'trade_id',
                DB::raw('count(*) as volume'),
                DB::raw('sum(coalesce(platform_fee_cents, 0)) as commission_cents'),
                DB::raw('sum(coalesce(provider_payout_cents, 0)) as reverse_cents'),
                DB::raw('sum(case when cancelled_at is not null then 1 else 0 end) as annulees'),
                DB::raw('sum(case when assigned_provider_user_id is null then 1 else 0 end) as sans_prestataire'),
            ])
            ->get();

        $metiers = Trade::query()->whereIn('id', $lignes->pluck('trade_id'))->get()->keyBy('id');
        $resolveur = app(ResolveurDeCommission::class);

        return $lignes->map(function (object $l) use ($metiers, $resolveur): array {
            $volume = (int) $l->volume;
            $encaisse = (int) $l->commission_cents + (int) $l->reverse_cents;

            return [
                'trade_id' => (int) $l->trade_id,
                'metier' => $metiers[$l->trade_id]->name ?? 'Métier #'.$l->trade_id,
                'volume' => $volume,
                'commission_cents' => (int) $l->commission_cents,
                'volume_affaires_cents' => $encaisse,
                // LE TAUX RÉELLEMENT ENCAISSÉ, pas celui qu'on croit appliquer : le plancher de
                // 2 € et les taux figés d'anciennes missions font diverger les deux.
                'taux_effectif' => $encaisse > 0 ? round((int) $l->commission_cents / $encaisse * 100, 2) : 0.0,
                'taux_regle' => $resolveur->pour(ContexteDeCommission::prestation((int) $l->trade_id))->pourcentage(),
                'annulees' => (int) $l->annulees,
                'part_annulee' => $volume > 0 ? round((int) $l->annulees / $volume * 100, 1) : 0.0,
                'sans_prestataire' => (int) $l->sans_prestataire,
                'part_sans_prestataire' => $volume > 0 ? round((int) $l->sans_prestataire / $volume * 100, 1) : 0.0,
            ];
        })->sortByDesc('commission_cents')->values();
    }

    /**
     * LES CONSEILS — chacun avec son chiffre, sa règle et son geste.
     *
     * @return list<array{ton: string, titre: string, constat: string, geste: string, trade_id: int|null}>
     */
    public function conseils(int $jours = self::FENETRE_JOURS): array
    {
        $metiers = $this->parMetier($jours);
        $conseils = [];

        if ($metiers->isEmpty()) {
            return [[
                'ton' => 'neutre',
                'titre' => 'Pas encore assez de missions pour conseiller quoi que ce soit',
                'constat' => 'Aucune réservation sur les '.$jours.' derniers jours : tout conseil serait une devinette.',
                'geste' => 'Revenez quand le premier métier aura passé '.self::VOLUME_MINIMAL.' missions.',
                'trade_id' => null,
            ]];
        }

        $partAnnuleeMoyenne = $metiers->avg('part_annulee') ?: 0.0;

        foreach ($metiers as $m) {
            if ($m['volume'] < self::VOLUME_MINIMAL) {
                continue;
            }

            // ── LA DEMANDE EXISTE, L'OFFRE MANQUE ───────────────────────────
            if ($m['part_sans_prestataire'] >= 30.0) {
                $conseils[] = [
                    'ton' => 'danger',
                    'titre' => $m['metier'].' : la demande arrive, personne ne la prend',
                    'constat' => $m['part_sans_prestataire'].' % des missions restent sans prestataire ('
                        .$m['sans_prestataire'].' sur '.$m['volume'].'). Le taux réglé est de '.$m['taux_regle'].' %.',
                    'geste' => 'Baisser la commission augmente ce que le prestataire touche, donc l’attrait du métier. '
                        .'Mais si le problème est le NOMBRE de prestataires, baisser ne recrute personne : '
                        .'c’est le moment de mettre de la publicité sur le recrutement, pas sur la demande.',
                    'trade_id' => $m['trade_id'],
                ];
            }

            // ── ON ANNULE BEAUCOUP PLUS QU'AILLEURS ─────────────────────────
            if ($partAnnuleeMoyenne > 0 && $m['part_annulee'] >= $partAnnuleeMoyenne * 2 && $m['part_annulee'] >= 15.0) {
                $conseils[] = [
                    'ton' => 'attention',
                    'titre' => $m['metier'].' : deux fois plus d’annulations que la moyenne',
                    'constat' => $m['part_annulee'].' % d’annulations, contre '
                        .round($partAnnuleeMoyenne, 1).' % en moyenne sur la plateforme.',
                    'geste' => 'La commission n’est probablement PAS la cause : regardez d’abord le délai '
                        .'d’affectation et le prix affiché. Augmenter le taux ici ferait fuir ceux qui restent.',
                    'trade_id' => $m['trade_id'],
                ];
            }

            // ── LE MÉTIER QUI PORTE LA PLATEFORME ───────────────────────────
            if ($m['volume'] >= self::VOLUME_MINIMAL * 4 && $m['part_sans_prestataire'] <= 10.0) {
                $conseils[] = [
                    'ton' => 'bien',
                    'titre' => $m['metier'].' : de la demande, et des prestataires pour la prendre',
                    'constat' => $m['volume'].' missions, seulement '.$m['part_sans_prestataire'].' % sans prestataire, '
                        .'à '.$m['taux_regle'].' % de commission.',
                    'geste' => 'C’est le seul cas où augmenter la commission se défend : l’offre suit la demande. '
                        .'Montez d’un point ou deux et regardez la part sans prestataire la semaine suivante — '
                        .'si elle grimpe, revenez en arrière.',
                    'trade_id' => $m['trade_id'],
                ];
            }

            // ── LE TAUX ENCAISSÉ NE RESSEMBLE PAS AU TAUX RÉGLÉ ─────────────
            if ($m['taux_effectif'] > 0 && abs($m['taux_effectif'] - $m['taux_regle']) >= 5.0) {
                $conseils[] = [
                    'ton' => 'attention',
                    'titre' => $m['metier'].' : vous encaissez '.$m['taux_effectif'].' %, vous avez réglé '.$m['taux_regle'].' %',
                    'constat' => 'L’écart vient des missions conclues AVANT le réglage — leur taux est figé — '
                        .'ou du plancher de commission sur les petits montants.',
                    'geste' => 'Rien à corriger si le réglage est récent. Si l’écart dure, le plancher mord '
                        .'trop souvent : c’est lui qu’il faut revoir, pas le pourcentage.',
                    'trade_id' => $m['trade_id'],
                ];
            }
        }

        // ── UNE RÈGLE QUI NE SERT JAMAIS ────────────────────────────────────
        foreach ($this->reglesMasquees() as $masquee) {
            $conseils[] = $masquee;
        }

        return $conseils;
    }

    /**
     * LES RÈGLES QU'UNE AUTRE COUVRE ENTIÈREMENT.
     *
     * Le piège classique d'un tel système : poser un taux qui ne s'appliquera jamais parce qu'une
     * règle plus précise le recouvre. Sans cette alerte, on croit avoir baissé un prix.
     *
     * @return list<array{ton: string, titre: string, constat: string, geste: string, trade_id: int|null}>
     */
    public function reglesMasquees(): array
    {
        $regles = CommissionRule::query()->actives()->get();
        $alertes = [];

        foreach ($regles as $regle) {
            if ($regle->trade_id !== null || $regle->service_zone_id !== null || $regle->min_duration_days !== null) {
                continue;
            }

            $plusPrecises = $regles->filter(
                fn (CommissionRule $autre): bool => $autre->id !== $regle->id
                    && $autre->module === $regle->module
                    && $autre->precision() > $regle->precision()
            );

            if ($plusPrecises->count() >= 3) {
                $alertes[] = [
                    'ton' => 'neutre',
                    'titre' => 'La règle « '.$regle->label.' » ne s’applique presque jamais',
                    'constat' => $plusPrecises->count().' règles plus précises la recouvrent sur le même module.',
                    'geste' => 'Ce n’est pas un défaut : c’est votre filet de sécurité. Vérifiez seulement '
                        .'qu’elle porte bien le taux que vous voulez pour les cas non prévus.',
                    'trade_id' => null,
                ];
            }
        }

        return $alertes;
    }
}
