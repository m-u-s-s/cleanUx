<?php

namespace Database\Seeders\Support;

/**
 * UN ENCODEUR PNG EN PHP PUR, pour que le semis de démonstration ait de vraies images.
 *
 * NI GD NI IMAGICK SUR CE POSTE — mesuré, pas supposé. Sans image, le catalogue de location
 * n'affiche que son emoji de repli : on ne voit ni la vignette, ni la grille, ni la rotation. Or
 * ce semis existe précisément pour VOIR à quoi le module ressemble.
 *
 * PNG se laisse écrire à la main : une signature, trois blocs, et chaque bloc porte son CRC. Le
 * seul travail réel est la compression des lignes, et `zlib` est là.
 *
 * CE N'EST PAS UN OUTIL D'IMAGE. Il vit dans `database/seeders` parce qu'il ne sert qu'au semis de
 * démonstration : le produire dans `app/` inviterait à s'en servir pour de vrai, alors qu'il ne
 * sait dessiner que des aplats.
 */
final class PngDeDemonstration
{
    /**
     * Une photo de catalogue : dégradé de fond, silhouette de véhicule, sol.
     *
     * @param  float  $teinte  0 à 1 — donne à chaque voiture sa couleur, pour qu'une grille de
     *                         huit vignettes ne soit pas huit fois la même image.
     */
    public static function photo(int $largeur, int $hauteur, float $teinte, float $angle = 0.0): string
    {
        $pixels = [];

        [$fondR, $fondV, $fondB] = self::versRvb($teinte, 0.35, 0.92);
        [$carR, $carV, $carB] = self::versRvb($teinte, 0.72, 0.62);

        $solY = (int) ($hauteur * 0.78);

        /*
         * LA LARGEUR DE LA CAISSE SUIT LE COSINUS DE L'ANGLE.
         *
         * C'est ce qui fait qu'une séquence de vingt-quatre images se lit comme un objet qui
         * TOURNE plutôt que comme un diaporama : de face la voiture est étroite, de profil elle est
         * large. Un simple marqueur qui se déplace ne donnerait pas cette impression.
         */
        $profil = abs(cos($angle));
        $demiLargeur = (int) ($largeur * (0.13 + 0.24 * $profil));
        $centreX = intdiv($largeur, 2);
        $toitY = (int) ($hauteur * 0.42);
        $capotY = (int) ($hauteur * 0.56);

        for ($y = 0; $y < $hauteur; $y++) {
            $ligne = '';

            for ($x = 0; $x < $largeur; $x++) {
                if ($y > $solY) {
                    // Le sol, plus sombre : sans lui la voiture flotte et la scène ne se lit pas.
                    $ligne .= chr((int) ($fondR * 0.55)).chr((int) ($fondV * 0.55)).chr((int) ($fondB * 0.55));

                    continue;
                }

                $dansCaisse = $y >= $capotY && $y <= $solY
                    && abs($x - $centreX) <= $demiLargeur;

                // Le pavillon est plus étroit et plus court que la caisse : c'est ce décrochement
                // qui fait lire « voiture » plutôt que « rectangle ».
                $dansToit = $y >= $toitY && $y < $capotY
                    && abs($x - $centreX) <= (int) ($demiLargeur * 0.62);

                if ($dansCaisse || $dansToit) {
                    $ligne .= chr($carR).chr($carV).chr($carB);

                    continue;
                }

                // Dégradé vertical du fond, du clair au sombre.
                $facteur = 0.75 + 0.25 * (1 - $y / max(1, $hauteur));
                $ligne .= chr((int) ($fondR * $facteur)).chr((int) ($fondV * $facteur)).chr((int) ($fondB * $facteur));
            }

            // Chaque ligne PNG commence par son octet de filtre. `0` = aucun filtre : la
            // compression est un peu moins bonne, le code beaucoup plus court.
            $pixels[] = "\x00".$ligne;
        }

        return self::encoder($largeur, $hauteur, implode('', $pixels));
    }

    /**
     * Assemble un PNG 24 bits sans palette.
     *
     * Trois blocs suffisent : `IHDR` décrit l'image, `IDAT` porte les lignes compressées, `IEND`
     * ferme. Chacun porte sa longueur, son nom et son CRC — un CRC faux donne un fichier que le
     * navigateur refuse en silence.
     */
    private static function encoder(int $largeur, int $hauteur, string $lignes): string
    {
        $entete = pack('NN', $largeur, $hauteur)."\x08\x02\x00\x00\x00";

        return "\x89PNG\r\n\x1a\n"
            .self::bloc('IHDR', $entete)
            .self::bloc('IDAT', (string) gzcompress($lignes, 6))
            .self::bloc('IEND', '');
    }

    private static function bloc(string $nom, string $contenu): string
    {
        return pack('N', strlen($contenu)).$nom.$contenu.pack('N', crc32($nom.$contenu));
    }

    /**
     * Teinte, saturation, valeur vers rouge-vert-bleu.
     *
     * @return array{0: int, 1: int, 2: int}
     */
    private static function versRvb(float $t, float $s, float $v): array
    {
        $i = (int) floor($t * 6);
        $f = $t * 6 - $i;
        $p = $v * (1 - $s);
        $q = $v * (1 - $f * $s);
        $u = $v * (1 - (1 - $f) * $s);

        [$r, $vert, $b] = match ($i % 6) {
            0 => [$v, $u, $p],
            1 => [$q, $v, $p],
            2 => [$p, $v, $u],
            3 => [$p, $q, $v],
            4 => [$u, $p, $v],
            default => [$v, $p, $q],
        };

        return [(int) ($r * 255), (int) ($vert * 255), (int) ($b * 255)];
    }
}
