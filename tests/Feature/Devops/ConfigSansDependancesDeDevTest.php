<?php

namespace Tests\Feature\Devops;

use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/** AUCUN FICHIER DE `config/` NE DOIT EXIGER UNE DÉPENDANCE DE DÉVELOPPEMENT. */
class ConfigSansDependancesDeDevTest extends TestCase
{
    public function test_chaque_fichier_de_config_se_charge_sans_paquet_de_dev(): void
    {
        $prefixes = $this->prefixesDesPaquetsDeDev();

        // Garde-fou : sans préfixe à surveiller, la boucle serait un rituel vide.
        $this->assertNotEmpty($prefixes, 'Aucun espace de noms de dev trouvé — la sonde ne mesure rien.');

        $fichiers = glob(base_path('config/*.php')) ?: [];
        $this->assertGreaterThan(10, count($fichiers), 'Le dossier config/ doit être trouvé.');

        $fautifs = [];

        foreach ($fichiers as $fichier) {
            $classe = $this->classeDeDevManquante($fichier, $prefixes);

            if ($classe !== null) {
                $fautifs[] = basename($fichier).' → '.$classe;
            }
        }

        $this->assertSame(
            [],
            $fautifs,
            "Ces fichiers de configuration exigent une classe absente d'une installation --no-dev :\n"
            .implode("\n", $fautifs),
        );
    }

    /** TÉMOIN — la sonde voit bien le défaut qu'elle cherche. */
    public function test_temoin_la_sonde_detecte_une_classe_de_dev(): void
    {
        $prefixes = $this->prefixesDesPaquetsDeDev();

        $fichier = tempnam(sys_get_temp_dir(), 'cfg').'.php';
        file_put_contents(
            $fichier,
            "<?php\nreturn ['x' => \\".$prefixes[0]."ClasseAbsente::VALEUR];\n",
        );

        $trouvee = $this->classeDeDevManquante($fichier, $prefixes);
        @unlink($fichier);

        $this->assertNotNull($trouvee, 'La sonde doit voir une classe issue d’un paquet de développement.');
    }

    /** SECOND TÉMOIN — la sonde ne crie PAS sur une classe de production. */
    public function test_temoin_la_sonde_ignore_une_classe_de_production(): void
    {
        $fichier = tempnam(sys_get_temp_dir(), 'cfg').'.php';
        file_put_contents(
            $fichier,
            "<?php\nreturn ['x' => \\Illuminate\\Support\\Str::class, 'y' => \\Illuminate\\Support\\Carbon::now()];\n",
        );

        $trouvee = $this->classeDeDevManquante($fichier, $this->prefixesDesPaquetsDeDev());
        @unlink($fichier);

        $this->assertNull($trouvee, 'Une classe du framework n’est pas une dépendance de développement.');
    }

    // ─────────────────────────────────────────────────────────────────────

    /**
     * Les espaces de noms que `composer install --no-dev` n'installe PAS.
     *
     * @return list<string>
     */
    private function prefixesDesPaquetsDeDev(): array
    {
        $installes = json_decode(
            (string) file_get_contents(base_path('vendor/composer/installed.json')),
            true,
        );

        $paquets = $installes['packages'] ?? [];
        $nomsDeDev = $installes['dev-package-names'] ?? [];

        $prefixes = [];

        foreach ($paquets as $paquet) {
            if (! in_array($paquet['name'] ?? '', $nomsDeDev, true)) {
                continue;
            }

            foreach (['psr-4', 'psr-0'] as $norme) {
                foreach (array_keys($paquet['autoload'][$norme] ?? []) as $prefixe) {
                    if ($prefixe !== '') {
                        $prefixes[] = $prefixe;
                    }
                }
            }
        }

        // ON RETIRE LES ESPACES DE NOMS QUE L'APPLICATION DÉCLARE ELLE-MÊME.
        $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);
        $siens = array_keys($composer['autoload']['psr-4'] ?? []);

        return array_values(array_diff(array_unique($prefixes), $siens));
    }

    /**
     * Exécute un fichier dans un processus neuf, sans autochargeur, et rend la classe de développement qui a manqué — ou `null`.
     *
     * @param  list<string>  $prefixes
     */
    private function classeDeDevManquante(string $fichier, array $prefixes): ?string
    {
        // Les aides du framework sont bouchonnées : elles viennent d'une dépendance de PRODUCTION, leur absence ici est un artefact du processus nu et non le défaut qu'on traque.
        $script = <<<'PHP'
            function config($k = null, $d = null) { return $d; }
            function env($k = null, $d = null) { return $d; }
            function base_path($p = '') { return __DIR__.'/'.$p; }
            function storage_path($p = '') { return __DIR__.'/'.$p; }
            function app_path($p = '') { return __DIR__.'/'.$p; }
            function public_path($p = '') { return __DIR__.'/'.$p; }
            function database_path($p = '') { return __DIR__.'/'.$p; }
            error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
            require $argv[1];
            echo 'OK';
            PHP;

        $resultat = Process::run([PHP_BINARY, '-r', $script, $fichier]);
        $sortie = $resultat->output().$resultat->errorOutput();

        if (! preg_match('/(?:Class|Interface|Enum|Trait) "?([^"\s]+)"? not found/i', $sortie, $m)) {
            return null;
        }

        $antislash = chr(92);
        $classe = ltrim($m[1], $antislash);

        foreach ($prefixes as $prefixe) {
            if (str_starts_with($classe, ltrim($prefixe, $antislash))) {
                return $classe;
            }
        }

        return null;
    }
}
