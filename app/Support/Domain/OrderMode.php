<?php

namespace App\Support\Domain;

/**
 * Les trois façons de commander.
 *
 * Le mode n'est pas cosmétique : il change les questions posées, la structure du prix et le
 * parcours qui suit. Il est donc choisi tôt et visiblement, et seuls les modes que le métier
 * autorise (`trades.allows_*`) sont proposés.
 */
final class OrderMode
{
    /** Rendez-vous planifié — le parcours de référence, et le défaut. */
    public const SCHEDULED = 'scheduled';

    /** Service immédiat : pas de calendrier, on cherche un prestataire qui prend la route. */
    public const ASAP = 'asap';

    /** Multi-services : plusieurs métiers, une seule commande, un seul paiement. */
    public const BUNDLE = 'bundle';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::SCHEDULED, self::ASAP, self::BUNDLE];
    }

    /** Colonne de `trades` qui autorise ce mode. */
    public static function tradeFlag(string $mode): string
    {
        return match ($mode) {
            self::ASAP => 'allows_asap',
            self::BUNDLE => 'allows_bundle',
            default => 'allows_scheduled',
        };
    }
}
