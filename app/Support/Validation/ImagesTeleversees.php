<?php

namespace App\Support\Validation;

/** Liste unique des formats d'image acceptés au téléversement. */
final class ImagesTeleversees
{
    /**
     * Formats matriciels acceptés partout où une image finit sur un disque servi par le web.
     *
     * @var list<string>
     */
    public const EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'heic', 'heif'];

    /**
     * Jeu de règles complet pour un champ image.
     *
     * @param  int  $tailleMaxKo  Plafond en kilo-octets, propre à chaque parcours.
     * @return list<string>
     */
    public static function regles(int $tailleMaxKo, bool $obligatoire = true): array
    {
        return [
            $obligatoire ? 'required' : 'nullable',
            'file',
            'mimes:'.implode(',', self::EXTENSIONS),
            'max:'.$tailleMaxKo,
        ];
    }
}
