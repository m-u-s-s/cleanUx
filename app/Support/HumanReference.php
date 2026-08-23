<?php

namespace App\Support;

/** Une référence destinée à être DICTÉE au téléphone. */
class HumanReference
{
    /** L'alphabet sans les caractères qui se confondent à l'oreille ou à l'œil. */
    public const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public static function make(int $length): string
    {
        $max = strlen(self::ALPHABET) - 1;
        $out = '';

        for ($i = 0; $i < $length; $i++) {
            // `random_int` et non `rand` : une référence devinable laisse énumérer les commandes
            // des autres clients.
            $out .= self::ALPHABET[random_int(0, $max)];
        }

        return $out;
    }

    /** Une référence préfixée, prête à être communiquée. */
    public static function prefixed(string $prefix, int $length): string
    {
        return $prefix.self::make($length);
    }
}
