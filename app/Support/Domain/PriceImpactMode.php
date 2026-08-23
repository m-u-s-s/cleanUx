<?php

namespace App\Support\Domain;

/** Comment une réponse pèse sur le prix. */
final class PriceImpactMode
{
    public const NONE = 'none';

    /** Montant fixe ajouté dès que la question est répondue. */
    public const ADD = 'add';

    /** Coefficient appliqué au sous-total. */
    public const MULTIPLY = 'multiply';

    /** Valeur numérique de la réponse × coefficient, en centimes. */
    public const PER_UNIT = 'per_unit';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::NONE, self::ADD, self::MULTIPLY, self::PER_UNIT];
    }
}
