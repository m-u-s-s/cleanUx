<?php

namespace App\Support\Domain;

/** Operateurs de la logique conditionnelle entre questions. */
final class ConditionOperator
{
    public const EQUALS = 'equals';

    public const NOT_EQUALS = 'not_equals';

    public const IN = 'in';

    public const GT = 'gt';

    public const LT = 'lt';

    /** Vrai des que la question dont on depend a recu une reponse, quelle qu'elle soit. */
    public const IS_ANSWERED = 'is_answered';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::EQUALS, self::NOT_EQUALS, self::IN, self::GT, self::LT, self::IS_ANSWERED];
    }
}
