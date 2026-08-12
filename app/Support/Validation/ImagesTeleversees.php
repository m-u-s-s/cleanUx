<?php

namespace App\Support\Validation;

/**
 * Liste unique des formats d'image acceptés au téléversement.
 *
 * POURQUOI UN SEUL ENDROIT
 *
 * Les photos de profil des trois parcours (avatar client mobile, photo d'onboarding prestataire
 * mobile, même photo côté web) atterrissent toutes sur le disque `public` : un dossier servi tel
 * quel par le serveur web, sur le domaine de l'application. Ce qui est accepté ici s'ouvrira donc
 * dans NOTRE origine, avec la session de qui regarde — le client, ou l'administrateur qui instruit
 * le dossier du prestataire.
 *
 * Quand la règle est recopiée à chaque point d'entrée, elle diverge, et c'est toujours la copie la
 * plus permissive qui décide : un attaquant n'utilise pas le formulaire qu'on a durci, il utilise
 * l'autre. Le durcissement d'août 2026 en est l'illustration exacte — les deux contrôleurs d'API
 * sont passés à une liste explicite pendant que le wizard web, qui écrit sur le MÊME disque,
 * restait sur `image`. La liste vit donc ici, et nulle part ailleurs.
 *
 * CE QUI EST DEDANS, ET POURQUOI
 *
 * Le SVG est exclu, et c'est le seul retrait volontaire : ce n'est pas une image matricielle mais
 * un document XML, qui porte `<script>` et `onload=` sans rien perdre de sa validité. Servi avec
 * `Content-Type: image/svg+xml`, il s'exécute.
 *
 * gif et bmp restent acceptés. Ce ne sont pas des vecteurs de script — ce sont des formats
 * matriciels que produisent encore des téléphones anciens et de vieux scanners, exactement le parc
 * des prestataires qu'on veut inscrire. Les refuser n'aurait fermé aucune faille et aurait cassé
 * des téléversements légitimes ; c'est ce qui s'était produit côté mobile.
 *
 * La liste vaut donc `image` de Laravel, moins `svg`. La différence n'est pas dans son contenu
 * d'aujourd'hui mais dans le fait qu'elle nous appartient : `validateImage()` vit dans `vendor/`,
 * n'ajoute `svg` que sur le paramètre explicite `allow_svg`, et rien chez nous ne l'y retient. Une
 * constante ne bouge que si quelqu'un la modifie sciemment, et une seule ligne est à relire.
 *
 * COMMENT LA RÈGLE CONTRÔLE
 *
 * `mimes:` compare `guessExtension()`, dérivée du type détecté par `finfo` SUR LE CONTENU, pas de
 * l'extension annoncée par le client. Renommer la charge en `.png` ne la fait donc pas passer.
 */
final class ImagesTeleversees
{
    /**
     * Formats matriciels acceptés partout où une image finit sur un disque servi par le web.
     *
     * @var list<string>
     */
    public const EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];

    /**
     * Jeu de règles complet pour un champ image.
     *
     * Renvoyer la règle entière plutôt que la seule liste évite qu'un appelant garde `image` à
     * côté, ou oublie `file` — auquel cas `mimes:` ne s'appliquerait pas à une valeur non fichier.
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
