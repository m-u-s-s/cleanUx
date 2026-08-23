<?php

namespace App\Support\Domain;

/** La machine à états du service immédiat, telle que le CLIENT la voit. */
final class AsapStatus
{
    /** On cherche : le rayon s'élargit, les prestataires sont prévenus. */
    public const SEARCHING = 'searching';

    /** Quelqu'un a pris la course. La fenêtre d'annulation gratuite démarre ici. */
    public const ACCEPTED = 'accepted';

    public const EN_ROUTE = 'en_route';

    public const ARRIVED = 'arrived';

    public const IN_PROGRESS = 'in_progress';

    public const COMPLETED = 'completed';

    public const CANCELLED = 'cancelled';

    /** Personne n'a répondu dans le délai. Jamais un cul-de-sac : trois suites sont proposées. */
    public const EXPIRED = 'expired';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::SEARCHING, self::ACCEPTED, self::EN_ROUTE, self::ARRIVED,
            self::IN_PROGRESS, self::COMPLETED, self::CANCELLED, self::EXPIRED,
        ];
    }

    /**
     * Les transitions permises.
     *
     * @return array<string, list<string>>
     */
    public static function transitions(): array
    {
        return [
            self::SEARCHING => [self::ACCEPTED, self::CANCELLED, self::EXPIRED],
            self::ACCEPTED => [self::EN_ROUTE, self::CANCELLED],
            self::EN_ROUTE => [self::ARRIVED, self::CANCELLED],
            self::ARRIVED => [self::IN_PROGRESS, self::CANCELLED],
            // Une intervention commencée ne s'annule plus : on la termine, et le litige se règle
            // après. Laisser annuler ici priverait le prestataire d'un travail déjà fourni.
            self::IN_PROGRESS => [self::COMPLETED],
            self::COMPLETED => [],
            self::CANCELLED => [],
            self::EXPIRED => [self::SEARCHING],
        ];
    }

    public static function canMove(string $from, string $to): bool
    {
        return in_array($to, self::transitions()[$from] ?? [], true);
    }

    /** La demande est-elle encore vivante ? */
    public static function isOpen(string $status): bool
    {
        return ! in_array($status, [self::COMPLETED, self::CANCELLED, self::EXPIRED], true);
    }

    /** Libellés destinés au client — verbes actifs, pas de jargon d'état. */
    public static function label(string $status): string
    {
        return match ($status) {
            self::SEARCHING => 'Recherche d’un professionnel',
            self::ACCEPTED => 'Un professionnel a accepté',
            self::EN_ROUTE => 'En route vers chez vous',
            self::ARRIVED => 'Arrivé sur place',
            self::IN_PROGRESS => 'Intervention en cours',
            self::COMPLETED => 'Intervention terminée',
            self::CANCELLED => 'Demande annulée',
            self::EXPIRED => 'Aucun professionnel disponible',
            default => $status,
        };
    }
}
