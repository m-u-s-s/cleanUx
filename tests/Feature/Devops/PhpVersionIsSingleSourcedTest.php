<?php

namespace Tests\Feature\Devops;

use Tests\TestCase;

/** Une SEULE version de PHP, de la CI jusqu'à la production. POURQUOI CE FICHIER EXISTE. */
class PhpVersionIsSingleSourcedTest extends TestCase
{
    /** La version de référence, celle sur laquelle tourne le serveur. */
    private const REFERENCE = '8.5';

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

        // Le garde-fou de la MESURE : un flux renommé, ou une syntaxe changée, ferait passer ce test en ne trouvant plus rien à comparer.
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

            // On ne lit que la borne BASSE d'une contrainte `>=` : c'est la seule qui puisse rendre un paquet impossible à installer sur la version de référence.
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
