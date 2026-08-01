<?php

namespace App\Support\Domain;

/** Ce que fait une condition remplie. */
final class ConditionAction
{
    /** La question n'apparait que si la condition est vraie. */
    public const SHOW = 'show';

    /** La question disparait si la condition est vraie. */
    public const HIDE = 'hide';

    /** La question reste visible mais devient obligatoire. */
    public const REQUIRE = 'require';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::SHOW, self::HIDE, self::REQUIRE];
    }
}
