<?php

namespace App\Support\Domain;

use App\Models\Booking;
use App\Models\Mission;

/** QUEL MOTEUR EXÉCUTE CETTE MISSION — une seule réponse, et trois réponses possibles. */
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

    /** LE MOTEUR D'UNE RÉSERVATION. */
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

    /** LE MOTEUR D'UNE MISSION — délégué à sa réservation, jamais décidé ici. */
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
