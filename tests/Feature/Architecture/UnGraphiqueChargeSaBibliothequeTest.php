<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;

/**
 * UN GRAPHIQUE QUI N'A PAS SA BIBLIOTHEQUE NE DESSINE RIEN, ET NE LE DIT PAS.
 *
 * `resources/js/apexcharts.js` definit les fonctions globales `dessinerActivite` et
 * `dessinerRepartition`. Une vue qui les appelle sans pousser le paquet leve une exception
 * JavaScript dans la console — la page repond 200, la section reste vide, et ni PHPStan ni
 * la suite ne voient rien. Mesure du 2026-08-29 : deux vues etaient dans ce cas.
 */
class UnGraphiqueChargeSaBibliothequeTest extends TestCase
{
    /** Les globales que `resources/js/apexcharts.js` installe sur `window`. */
    private const FONCTIONS = ['dessinerActivite', 'dessinerRepartition'];

    private const PAQUET = 'resources/js/apexcharts.js';

    /** @return list<string> */
    private function vues(): array
    {
        $vues = [];
        $iterateur = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));

        /** @var \SplFileInfo $fichier */
        foreach ($iterateur as $fichier) {
            if ($fichier->isFile() && str_ends_with($fichier->getFilename(), '.blade.php')) {
                $vues[] = $fichier->getPathname();
            }
        }

        sort($vues);

        return $vues;
    }

    public function test_toute_vue_qui_dessine_pousse_son_paquet(): void
    {
        $muettes = [];

        foreach ($this->vues() as $chemin) {
            $code = (string) file_get_contents($chemin);

            foreach (self::FONCTIONS as $fonction) {
                // L'APPEL, PAS LA MENTION : un commentaire qui explique la fonction n'a
                // besoin d'aucune bibliotheque.
                if (preg_match('/'.$fonction.'\s*\(/', $code) !== 1) {
                    continue;
                }

                if (! str_contains($code, self::PAQUET)) {
                    $muettes[] = $this->relatif($chemin).' appelle '.$fonction.'() sans pousser '.self::PAQUET;
                }

                break;
            }
        }

        $this->assertSame([], $muettes, sprintf(
            "%d vue(s) dessinent sans leur bibliothèque :\n  %s",
            count($muettes),
            implode("\n  ", $muettes),
        ));
    }

    /**
     * TEMOIN — la mesure voit bien les vues qui dessinent.
     *
     * Sans lui, zero vue muette pourrait vouloir dire « aucune vue ne dessine », et le test
     * passerait au vert en ne mesurant rien.
     */
    public function test_temoin_des_vues_dessinent_bel_et_bien(): void
    {
        $dessinantes = 0;

        foreach ($this->vues() as $chemin) {
            $code = (string) file_get_contents($chemin);

            foreach (self::FONCTIONS as $fonction) {
                if (preg_match('/'.$fonction.'\s*\(/', $code) === 1) {
                    $dessinantes++;

                    break;
                }
            }
        }

        $this->assertGreaterThanOrEqual(4, $dessinantes, 'Le balayage ne trouve presque aucun graphique : il ne mesure plus rien.');
    }

    private function relatif(string $chemin): string
    {
        return str_replace([base_path().DIRECTORY_SEPARATOR, '\\'], ['', '/'], $chemin);
    }
}
