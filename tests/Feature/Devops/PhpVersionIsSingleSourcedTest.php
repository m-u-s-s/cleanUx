<?php

namespace Tests\Feature\Devops;

use Tests\TestCase;

/**
 * Une SEULE version de PHP, de la CI jusqu'à la production.
 *
 * POURQUOI CE FICHIER EXISTE. Le 2026-08-04, le déploiement a échoué sur douze paquets Symfony
 * exigeant `php >= 8.4.1` : les flux de déploiement épinglaient 8.3 pendant que la CI testait en
 * 8.4. « Test ≠ prod », comme l'avait écrit l'audit du 2026-06-24 sans que rien ne l'empêche de
 * recommencer.
 *
 * LE DÉFAUT ÉTAIT MASQUÉ. `deploy` ne s'exécute que si la CI réussit ; la CI étant rouge depuis dix
 * poussées, ce flux n'avait pas tourné une seule fois. Réparer la CI l'a révélé — un bug caché
 * derrière un autre bug.
 *
 * CE QUE CE TEST NE PEUT PAS FAIRE. Il ne connaît pas la version installée sur le serveur : les
 * unités Supervisor/systemd ne sont versionnées qu'en `.example`, et le provisionnement est manuel.
 * Il garantit que les flux sont d'accord ENTRE EUX et avec ce que le verrou exige — ce qui aurait
 * suffi à attraper la panne.
 */
class PhpVersionIsSingleSourcedTest extends TestCase
{
    /** La version de référence, celle sur laquelle tourne le serveur. */
    private const REFERENCE = '8.4';

    /** @return list<string> */
    private function fichiersDeFlux(): array
    {
        $trouves = glob(base_path('.github/workflows/*.yml'));

        return $trouves === false ? [] : $trouves;
    }

    public function test_tous_les_flux_declarent_la_meme_version(): void
    {
        $divergents = [];
        $declarations = 0;

        foreach ($this->fichiersDeFlux() as $chemin) {
            $contenu = (string) file_get_contents($chemin);

            preg_match_all("/php-version:\s*'([^']+)'/", $contenu, $trouvees);

            foreach ($trouvees[1] as $version) {
                $declarations++;

                if ($version !== self::REFERENCE) {
                    $divergents[] = sprintf('%s déclare PHP %s', basename($chemin), $version);
                }
            }
        }

        $this->assertSame([], $divergents, sprintf(
            "Des flux visent une autre version que PHP %s :\n%s",
            self::REFERENCE,
            implode("\n", $divergents),
        ));

        /*
         * Le garde-fou de la MESURE : un flux renommé, ou une syntaxe changée, ferait passer ce
         * test en ne trouvant plus rien à comparer.
         */
        $this->assertGreaterThanOrEqual(4, $declarations, 'La mesure ne trouve presque aucune déclaration : elle a cessé de mesurer.');
    }

    public function test_la_version_de_reference_satisfait_le_verrou(): void
    {
        /** @var array{packages?: list<array{name: string, require?: array<string, string>}>} $verrou */
        $verrou = json_decode((string) file_get_contents(base_path('composer.lock')), true);

        $trop_recents = [];

        foreach ($verrou['packages'] ?? [] as $paquet) {
            $exige = $paquet['require']['php'] ?? null;

            if (! is_string($exige)) {
                continue;
            }

            /*
             * On ne lit que la borne BASSE d'une contrainte `>=` : c'est la seule qui puisse rendre
             * un paquet impossible à installer sur la version de référence. Le reste de la syntaxe
             * Composer n'a pas à être réimplémenté ici.
             */
            if (! preg_match('/>=\s*(\d+)\.(\d+)/', $exige, $bornes)) {
                continue;
            }

            $minimum = (float) ($bornes[1].'.'.$bornes[2]);

            if ($minimum > (float) self::REFERENCE) {
                $trop_recents[] = sprintf('%s exige php %s', $paquet['name'], $exige);
            }
        }

        $this->assertSame([], $trop_recents, sprintf(
            "Des paquets verrouillés ne s’installeront pas sur PHP %s — le déploiement échouera :\n%s",
            self::REFERENCE,
            implode("\n", $trop_recents),
        ));
    }
}
