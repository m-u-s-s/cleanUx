<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;

/**
 * LE SERVEUR DE DÉVELOPPEMENT DOIT DÉMARRER SANS OPCACHE.
 *
 * Sous le SAPI `cli-server`, l'opcache de PHP 8.5.5 résout `new \RecursiveIteratorIterator`
 * vers l'interface `RecursiveIterator` : `LoadConfiguration` meurt, et le message affiché
 * accuse les façades — la pièce innocente. Trois fichiers doivent rester d'accord.
 *
 * ON NE PEUT PAS MESURER LE VRAI SERVEUR ICI : le démarrer depuis la suite prendrait des
 * secondes et dépendrait d'un port libre. On vérifie la chaîne qui le configure.
 */
class LeServeurDeDeveloppementCoupeLOpcacheTest extends TestCase
{
    public function test_composer_serve_pose_le_dossier_ini_qui_coupe_l_opcache(): void
    {
        $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);

        $this->assertArrayHasKey('serve', $composer['scripts'] ?? [],
            'Sans `composer serve`, on relance `php artisan serve` à la main et la panne revient.');

        preg_match('#[\w/.-]+\.php#', (string) $composer['scripts']['serve'], $m);

        $lanceur = base_path($m[0] ?? 'introuvable');

        $this->assertFileExists($lanceur, 'Le script `serve` désigne un lanceur absent.');

        $source = (string) file_get_contents($lanceur);

        // `php -d` reste sur le processus parent, `ini_set` laisse les greffons de compilation.
        $this->assertSame(1, preg_match("#PHP_INI_SCAN_DIR.*?'([\w-]+)'#", $source, $d),
            'Seul PHP_INI_SCAN_DIR atteint le processus enfant que ServeCommand lance.');

        $dossier = dirname($lanceur).DIRECTORY_SEPARATOR.$d[1];

        $this->assertDirectoryExists($dossier);

        $coupures = [];

        foreach (glob($dossier.DIRECTORY_SEPARATOR.'*.ini') ?: [] as $ini) {
            $valeurs = parse_ini_file($ini) ?: [];

            if (array_key_exists('opcache.enable', $valeurs) && ! (bool) $valeurs['opcache.enable']) {
                $coupures[] = basename($ini);
            }
        }

        $this->assertNotSame([], $coupures,
            'Aucun .ini de '.$dossier.' ne coupe l’opcache : `composer serve` ne protège plus rien.');
    }
}
