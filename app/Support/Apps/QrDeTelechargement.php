<?php

namespace App\Support\Apps;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Cache;

/**
 * Le QR d'une application, rendu en SVG depuis son lien.
 *
 * Rien n'est téléversé et rien n'est stocké : changer le lien change le QR. Un QR déposé comme
 * fichier aurait été la deuxième source de vérité, et il aurait continué à pointer vers l'ancien
 * magasin longtemps après que l'admin ait corrigé le lien.
 */
final class QrDeTelechargement
{
    /** Le rendu est déterministe : on le garde une heure plutôt que de le recalculer à chaque vue. */
    private const TTL_SECONDES = 3600;

    /** Le SVG d'un lien, sans en-tête XML — il est inséré tel quel dans la page. */
    public static function svg(string $lien, int $taille = 220): ?string
    {
        if (! LiensApplications::estPubliable($lien)) {
            return null;
        }

        return Cache::remember(
            'apps:qr:'.$taille.':'.sha1($lien),
            self::TTL_SECONDES,
            static fn (): string => self::rendre($lien, $taille),
        );
    }

    /** Le QR d'une application, ou `null` si elle n'a aucun lien exploitable. */
    public static function pour(string $application, int $taille = 220): ?string
    {
        $lien = LiensApplications::lienPourQr($application);

        return $lien === null ? null : self::svg($lien, $taille);
    }

    private static function rendre(string $lien, int $taille): string
    {
        $writer = new Writer(new ImageRenderer(
            new RendererStyle($taille, 1),
            new SvgImageBackEnd,
        ));

        $svg = $writer->writeString($lien);

        // `writeString` préfixe une déclaration XML : elle est illégale au milieu d'un document
        // HTML et fait basculer certains navigateurs en mode dégradé.
        return (string) preg_replace('/^<\?xml[^>]*\?>\s*/', '', $svg);
    }
}
