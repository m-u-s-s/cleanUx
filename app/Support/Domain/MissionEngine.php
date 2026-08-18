<?php

namespace App\Support\Domain;

use App\Models\Booking;
use App\Models\Mission;

/**
 * QUEL MOTEUR EXÉCUTE CETTE MISSION — une seule réponse, et trois réponses possibles.
 *
 * Les trois parcours existaient déjà, chacun avec son propre discriminant, lus à des endroits
 * différents : `Booking::estUneCourse()` d'un côté, `HourlyRateResolver::seFactureALHeure()` de
 * l'autre, et « tout le reste » nulle part. Rien ne les rendait EXCLUSIFS : un métier horaire
 * portant une dépose était les deux à la fois, et deux services en tiraient deux conclusions
 * opposées sur la même mission — un chauffeur qui se voit réclamer une checklist de ménage.
 *
 * AUCUNE COLONNE NOUVELLE. `TradeRouteRules` interdit le drapeau booléen tenu à la main, et il a
 * raison : il finit par contredire sa source. Les deux discriminants existent déjà, et ils sont
 * déjà FIGÉS sur la réservation au moment de l'achat :
 *
 *   `dropoff_lat` / `dropoff_lng`  le point d'arrivée, écrit par le moteur de commande
 *   `purchased_minutes`            le temps acheté ; `null` dit « pas vendu au temps »
 *
 * C'est ce gel qui compte. `trades.hourly_billing` est lu EN DIRECT sur le métier : un
 * administrateur qui décoche la case changerait la nature d'une mission en cours d'exécution.
 *
 * L'ORDRE EST UNE PRIORITÉ STRICTE, et c'est lui qui rend les trois exclusifs. Le véhicule
 * d'abord : une course vendue au temps reste une course — on ne demande pas six chiffres à
 * quelqu'un au volant.
 *
 * CE QUI N'EST PAS ICI. Cette classe dit quel PARCOURS et quelle PAGE.
 * `HourlyRateResolver::seFactureALHeure()` dit si le dépassement est FACTURABLE. Deux questions
 * distinctes — `HourlyMissionClock` pose les deux à dessein — et les fondre reproduirait
 * exactement le défaut qu'on ferme ici.
 */
final class MissionEngine
{
    /** D'un point à un autre : ni code, ni checklist ; la trace GPS fait preuve. */
    public const VEHICULE = 'vehicule';

    /** Vendue au temps : compteur, prolongation, dépassement. */
    public const HORAIRE = 'horaire';

    /** Tout le reste : codes de début et de fin, checklist, nouveau devis. */
    public const DOMICILE = 'domicile';

    /**
     * Les trois moteurs, dans l'ordre de priorité du résolveur.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::VEHICULE, self::HORAIRE, self::DOMICILE];
    }

    /**
     * LE MOTEUR D'UNE RÉSERVATION.
     *
     * `DOMICILE` est le repli, et c'est le choix PROTECTEUR : c'est le parcours qui exige les codes
     * et la checklist. Une réservation illisible ne doit pas se retrouver sur celui qui n'en
     * demande aucun — le repli d'un doute ne doit jamais retirer une garantie.
     */
    public static function pourReservation(?Booking $booking): string
    {
        if ($booking === null) {
            return self::DOMICILE;
        }

        // LES DEUX COORDONNÉES, comme `estUneCourse()` : une seule ne trace aucun itinéraire et ne
        // donne aucun lieu où confronter la position à la clôture. Une demi-dépose n'est pas une
        // course, c'est une donnée incomplète.
        if ($booking->dropoff_lat !== null && $booking->dropoff_lng !== null) {
            return self::VEHICULE;
        }

        // Zéro n'est pas « vendu au temps » : c'est une colonne remplie par erreur. Le compteur
        // afficherait une échéance immédiate et un dépassement dès la première seconde.
        if ((int) ($booking->purchased_minutes ?? 0) > 0) {
            return self::HORAIRE;
        }

        return self::DOMICILE;
    }

    /**
     * LE MOTEUR D'UNE MISSION — délégué à sa réservation, jamais décidé ici.
     *
     * Une mission n'est qu'un dossier d'exécution : ce qui a été VENDU vit sur la réservation. Y
     * répondre autrement ferait deux sources pour une même question, et elles divergeraient au
     * premier chemin de création qui oublierait d'en recopier une.
     */
    public static function pourMission(?Mission $mission): string
    {
        return self::pourReservation($mission?->booking);
    }

    /** Le devis se révise là où le prix vient d'une estimation : ni au temps, ni au trajet. */
    public static function accepteLeNouveauDevis(string $moteur): bool
    {
        return $moteur === self::DOMICILE;
    }

    /** Il n'y a rien à cocher sur un trajet. */
    public static function accepteLaToDoList(string $moteur): bool
    {
        return $moteur !== self::VEHICULE;
    }

    /** Les six chiffres attestent d'une rencontre devant une porte ; une course n'en a pas. */
    public static function accepteLesCodes(string $moteur): bool
    {
        return $moteur !== self::VEHICULE;
    }

    /** Un supplément s'ajoute à une prestation ; le prix d'une course est fixé par le trajet. */
    public static function accepteLeSupplement(string $moteur): bool
    {
        return $moteur !== self::VEHICULE;
    }
}
