<?php

namespace App\Support\Domain;

/** Les types de question que le moteur sait rendre et tarifer. */
final class QuestionType
{
    public const SINGLE_CHOICE = 'single_choice';

    public const MULTI_CHOICE = 'multi_choice';

    public const BOOLEAN = 'boolean';

    public const COUNTER = 'counter';

    public const NUMBER = 'number';

    /** Mètres carrés, avec assistant longueur x largeur. */
    public const SURFACE = 'surface';

    public const RANGE = 'range';

    public const DATE = 'date';

    public const TIME = 'time';

    public const TEXT = 'text';

    public const TEXTAREA = 'textarea';

    public const PHOTO = 'photo';

    public const ADDRESS = 'address';

    /** Un POINT sur la carte, pas une ligne de texte. */
    public const LOCATION = 'location';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::SINGLE_CHOICE, self::MULTI_CHOICE, self::BOOLEAN, self::COUNTER,
            self::NUMBER, self::SURFACE, self::RANGE, self::DATE, self::TIME,
            self::TEXT, self::TEXTAREA, self::PHOTO, self::ADDRESS, self::LOCATION,
        ];
    }

    /** Types dont la reponse est un nombre exploitable par un tarif a l'unite. */
    public static function numeric(): array
    {
        return [self::COUNTER, self::NUMBER, self::SURFACE, self::RANGE];
    }

    /** Types dont la reponse designe une ou plusieurs options. */
    public static function optionBased(): array
    {
        return [self::SINGLE_CHOICE, self::MULTI_CHOICE, self::BOOLEAN];
    }
}
