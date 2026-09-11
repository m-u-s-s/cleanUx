<?php

namespace App\Support\Vitrine;

/**
 * Le film du parcours d'une mission, côté serveur.
 *
 * Une seule responsabilité : donner aux gabarits l'URL VERSIONNÉE des assets du film. Les 700
 * photogrammes et le poster gardent leurs noms de fichier d'un tournage à l'autre ; sans jeton
 * dans l'URL, le navigateur d'un visiteur qui a déjà vu le film lui reservirait l'ancien depuis
 * son cache — et aucun rechargement de page n'y changerait quoi que ce soit.
 *
 * Mesuré le 2026-09-11 : le film refait en plein jour est resté nocturne après deux
 * rechargements, parce que rien ne distinguait les nouvelles images des anciennes.
 */
final class FilmDuParcours
{
    private const RACINE = 'images/journey-film';

    /**
     * Le jeton de version du film, tel que le script de fabrication l'a écrit.
     *
     * RIEN N'EST MÉMORISÉ ICI, VOLONTAIREMENT : un cache statique servirait une version périmée
     * après une refabrication, dans le processus qui l'a lue avant. Le manifeste fait 200 octets.
     */
    public static function version(): string
    {
        $chemin = public_path(self::RACINE.'/frames.json');

        if (! is_file($chemin)) {
            return '0';
        }

        $manifeste = json_decode((string) file_get_contents($chemin), true);

        if (! is_array($manifeste) || ! isset($manifeste['version'])) {
            return '0';
        }

        return (string) $manifeste['version'];
    }

    /** L'URL versionnée d'un asset du film — `frames.json`, `jour/poster.jpg`… */
    public static function asset(string $fichier): string
    {
        return asset(self::RACINE.'/'.$fichier).'?v='.rawurlencode(self::version());
    }

    /**
     * Les deux variantes du film, et ce qu'elles montrent.
     *
     * DEUX TOURNAGES, PAS DEUX ETALONNAGES : le mode clair montre la mission de jour, le mode
     * sombre la mission de nuit. Le serveur ne connaît pas le thème du visiteur — les deux
     * posters sont donc rendus, et le CSS montre le bon.
     *
     * @var array<string, string> variante => classe CSS qui décide de sa visibilité
     */
    public const VARIANTES = [
        'jour' => 'cx-film__poster--clair',
        'nuit' => 'cx-film__poster--sombre',
    ];

    /** L'URL versionnée d'un poster de variante. */
    public static function poster(string $variante, string $extension): string
    {
        return self::asset($variante.'/poster.'.$extension);
    }
}
