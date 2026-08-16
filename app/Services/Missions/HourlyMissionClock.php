<?php

namespace App\Services\Missions;

use App\Models\Booking;
use App\Models\Mission;
use App\Services\OrderEngine\HourlyRateResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

/**
 * L'HORLOGE D'UNE MISSION VENDUE AU TEMPS — et la seule autorité sur ce qu'elle coûte.
 *
 * LE MOBILE NE CALCULE RIEN D'AUTRE QUE DES SECONDES. Il reçoit d'ici la date de début, l'échéance
 * et le montant ; il fait défiler l'affichage entre deux rafraîchissements. Lui laisser calculer
 * l'échéance ou le dépassement produirait deux vérités — celle de l'horloge du téléphone, réglable
 * par son porteur, et celle du serveur. Sur de l'argent, une seule doit exister.
 *
 * LA RÈGLE, EN CHIFFRES. Ménage à 45 €/h, 3 h achetées, mission immédiate (déjà majorée ×1,30) :
 *
 *   à la commande            3 h × 45 € × 1,30 ................ 175,50 €
 *   le client prolonge à 4 h  4 h × 45 € × 1,30 ................ 234,00 €
 *   le prestataire fait 4 h sans prolongation :
 *       les 3 h prévues ................................... 175,50 €
 *       l'heure dépassée  45 € × 1,30 × 1,30 ............... 76,05 €
 *                                                          = 251,55 €
 *
 * LE ×1,30 DU DÉPASSEMENT S'EMPILE, il ne remplace pas. S'il remplaçait la majoration du mode
 * immédiat, les deux colonnes du bas donneraient le même total : la pénalité ne pénaliserait rien
 * et personne n'aurait de raison de prolonger. C'est arithmétiquement ce qui la rend dissuasive.
 *
 * LA FRANCHISE EST UNE FRANCHISE, au sens de l'assurance : les quinze premières minutes de
 * dépassement sont à la charge de la plateforme, on ne facture QUE ce qui les dépasse. Sans elle,
 * cinq minutes de rangement coûteraient une pénalité — et le prestataire prendrait l'habitude de
 * clôturer avant d'avoir fini, ce qui est bien pire.
 */
class HourlyMissionClock
{
    public function __construct(
        private readonly HourlyRateResolver $rates,
    ) {}

    /**
     * L'état de l'horloge, tel que les deux surfaces le lisent.
     *
     * @return array<string, mixed>
     */
    public function etat(Mission $mission, ?Carbon $maintenant = null): array
    {
        $maintenant ??= now();
        $booking = $mission->booking;
        $achetees = $this->minutesAchetees($booking);

        /*
         * UN SEUL DISCRIMINANT GOUVERNE, et c'est celui qui décide de l'argent.
         *
         * `purchased_minutes` dit ce que le client a acheté ; `hourly_billing` dit si ce métier se
         * facture au temps. Allumer le compteur sur le premier seul produirait un chronomètre qui
         * défile, affiche « dépassement » et menace d'un montant nul — parce que le tarif effectif,
         * lui, se refuse quand le métier n'est plus horaire. On ne montre pas une échéance dont on
         * ne saurait pas tirer une facture.
         */
        if ($booking === null || $achetees === null || $mission->actual_start_at === null) {
            return ['applies' => false];
        }

        if (! $this->rates->seFactureALHeure($booking)) {
            return ['applies' => false];
        }

        $debut = $mission->actual_start_at;
        $echeance = $debut->copy()->addMinutes($achetees);
        $franchise = $this->franchiseEnMinutes();

        $ecoulees = max(0, (int) round(abs($debut->diffInSeconds($maintenant)) / 60));
        $depassement = max(0, $ecoulees - $achetees);

        $facturables = $this->minutesFacturables($depassement, $achetees);
        $tarif = $this->rates->tarifEffectifDeLaReservation($booking);

        return [
            'applies' => true,
            /*
             * L'HEURE DU SERVEUR AU MOMENT DE LA RÉPONSE — l'ancre qui rend le compteur honnête.
             *
             * Sans elle, le téléphone n'a que `started_at` et sa propre horloge : un appareil réglé
             * une heure en avance afficherait une heure de dépassement dès la première minute, et
             * un appareil en retard masquerait un dépassement réel. Avec elle, l'application mesure
             * son écart à la réception et le corrige — le compteur reste juste sur un appareil mal
             * réglé, et se recale à chaque rafraîchissement.
             */
            'server_now' => $maintenant->toIso8601String(),
            'started_at' => $debut->toIso8601String(),
            'purchased_minutes' => $achetees,
            /*
             * L'ÉCHÉANCE ET LE DÉBUT DE FACTURATION SONT DEUX DATES DISTINCTES, et le client doit
             * voir les deux : la première dit « votre temps est écoulé », la seconde « à partir
             * d'ici, ça coûte ». Les confondre ferait croire que la pénalité tombe à la seconde.
             */
            'deadline_at' => $echeance->toIso8601String(),
            'billable_from_at' => $echeance->copy()->addMinutes($franchise)->toIso8601String(),
            'grace_minutes' => $franchise,
            'elapsed_minutes' => $ecoulees,
            // Peut être NÉGATIF : c'est ce qui distingue « il reste 20 min » de « on déborde de 20 ».
            'remaining_minutes' => $achetees - $ecoulees,
            'overrun_minutes' => $depassement,
            'billable_overtime_minutes' => $facturables,
            'cap_minutes' => $this->plafondEnMinutes($achetees),
            'capped' => $depassement > $this->plafondEnMinutes($achetees) + $franchise,
            'overtime_multiplier' => $this->multiplicateur(),
            'effective_hourly_rate_cents' => $tarif,
            'overtime_amount_cents' => $this->montantDuDepassement($booking, $facturables),
        ];
    }

    /**
     * Ce que le dépassement coûte à cet instant, en centimes.
     *
     * Le tarif de base est le tarif EFFECTIF de la réservation — celui qui contient déjà la
     * majoration du mode immédiat, celle de la zone et les options multiplicatives. Le ×1,30 vient
     * par-dessus.
     */
    public function montantDuDepassement(Booking $booking, int $minutesFacturables): int
    {
        if ($minutesFacturables <= 0) {
            return 0;
        }

        $tarif = $this->rates->tarifEffectifDeLaReservation($booking);

        if ($tarif === null || $tarif <= 0) {
            return 0;
        }

        return (int) round($tarif * ($minutesFacturables / 60) * $this->multiplicateur());
    }

    /**
     * Les minutes réellement facturables : franchise déduite, arrondies au quart d'heure ENTAMÉ,
     * puis plafonnées.
     *
     * L'ordre compte. Plafonner avant d'arrondir laisserait l'arrondi repasser au-dessus du
     * plafond — un dépassement de trois heures pile finirait facturé trois heures et un quart.
     */
    public function minutesFacturables(int $depassement, int $achetees): int
    {
        $reste = $depassement - $this->franchiseEnMinutes();

        if ($reste <= 0) {
            return 0;
        }

        $pas = max(1, (int) Config::get('order_engine.overtime_billing_increment_minutes', 15));
        $arrondies = (int) (ceil($reste / $pas) * $pas);

        return min($arrondies, $this->plafondEnMinutes($achetees));
    }

    /**
     * LE PLAFOND BORNE L'ABUS.
     *
     * Sans lui, un compteur laissé tourner facture ce qu'il veut : le client a acheté trois heures
     * et découvre huit heures sur son relevé, sans avoir rien accepté. Trois heures achetées, trois
     * heures de dépassement au maximum.
     */
    public function plafondEnMinutes(int $achetees): int
    {
        $ratio = (float) Config::get('order_engine.overtime_cap_ratio', 1.0);

        return (int) round($achetees * max(0.0, $ratio));
    }

    /**
     * Le temps acheté sur cette réservation, ou `null` si elle n'est pas vendue au temps.
     */
    public function minutesAchetees(?Booking $booking): ?int
    {
        if ($booking === null) {
            return null;
        }

        $minutes = $booking->purchased_minutes;

        return $minutes !== null && (int) $minutes > 0 ? (int) $minutes : null;
    }

    private function franchiseEnMinutes(): int
    {
        return max(0, (int) Config::get('order_engine.overtime_grace_minutes', 15));
    }

    private function multiplicateur(): float
    {
        return (float) Config::get('order_engine.overtime_multiplier', 1.30);
    }
}
