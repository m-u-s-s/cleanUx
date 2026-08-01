<?php

namespace App\Support\Domain;

/** Unité de facturation d'un métier. `QUOTE_ONLY` interdit toute estimation automatique. */
final class PricingUnit
{
    public const FIXED = 'fixed';

    public const PER_HOUR = 'per_hour';

    public const PER_M2 = 'per_m2';

    public const PER_UNIT = 'per_unit';

    /** Devis obligatoire : aucun prix n'est annoncé tant qu'un professionnel n'a pas chiffré. */
    public const QUOTE_ONLY = 'quote_only';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::FIXED, self::PER_HOUR, self::PER_M2, self::PER_UNIT, self::QUOTE_ONLY];
    }
}
