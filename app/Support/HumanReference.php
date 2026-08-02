<?php

namespace App\Support;

/**
 * Une référence destinée à être DICTÉE au téléphone.
 *
 * Sans I, O, 0 ni 1 : ces quatre caractères sont la première cause d'erreur de saisie quand un
 * client lit sa référence au support, et la confusion se produit dans les deux sens.
 *
 * Cette classe existe parce que `Str::random()` NE PREND PAS d'alphabet — elle n'accepte qu'une
 * longueur. Le second argument qu'on croyait lui passer était silencieusement ignoré, et les
 * références contenaient donc exactement les caractères qu'on prétendait exclure. Le défaut ne se
 * voyait pas : les références restaient uniques et fonctionnelles, seulement pénibles à dicter.
 */
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
