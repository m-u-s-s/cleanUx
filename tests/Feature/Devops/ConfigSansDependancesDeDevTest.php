<?php

namespace Tests\Feature\Devops;

use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * AUCUN FICHIER DE `config/` NE DOIT EXIGER UNE DÉPENDANCE DE DÉVELOPPEMENT.
 *
 * Laravel charge TOUS les fichiers de `config/` au démarrage. Un fichier qui évalue une classe
 * venue de `require-dev` fait donc échouer l'application partout où l'installation est faite avec
 * `--no-dev` : c'est-à-dire en production, et nulle part ailleurs.
 *
 * CE QUE ÇA A COÛTÉ. `config/scribe.php` évaluait `AuthIn::BEARER->value`, une classe de
 * `knuckleswtf/scribe`. `composer install --no-dev` cassait sur `package:discover`, donc le
 * déploiement échouait à sa quatrième étape — bien avant la connexion SSH et les secrets. Le défaut
 * est resté invisible des mois : la CI était rouge pour une raison sans rapport, le job `Deploy`
 * était donc toujours `skipped`, et personne n'a jamais vu ce mur. Il n'est apparu qu'à la première
 * CI verte.
 *
 * COMMENT CETTE SONDE MESURE — et deux tentatives ratées avant elle, qui valent d'être dites :
 *
 *  1. Intercepter l'autochargement AVANT composer ne marche pas : `autoload_real` s'inscrit
 *     lui-même en tête (`register(true)`) et passe devant tout intercepteur inscrit plus tôt.
 *  2. L'inscrire APRÈS ne marche pas davantage : le garde légitime utilise `class_exists()`, qui
 *     DÉCLENCHE l'autochargement. La sonde criait alors sur un fichier parfaitement corrigé.
 *
 * D'où la forme retenue : exécuter chaque fichier dans un processus SANS aucun autochargeur. Le
 * fichier corrigé rend un tableau vide, le fichier fautif meurt sur sa classe. Les autres
 * configurations meurent aussi — sur des classes de PRODUCTION, parfaitement légitimes — et c'est
 * le filtre par espace de noms qui les écarte : on ne signale QUE ce qui manquerait vraiment.
 */
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

    /**
     * TÉMOIN — la sonde voit bien le défaut qu'elle cherche.
     *
     * Sans lui, l'assertion précédente passerait au vert sur une sonde devenue aveugle : un motif
     * qui ne correspond plus, un filtre trop large, et « aucun fautif » ne voudrait plus rien dire.
     */
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

    /**
     * SECOND TÉMOIN — la sonde ne crie PAS sur une classe de production.
     *
     * C'est l'erreur exacte de la première version : elle signalait `Laravel\Sanctum\Sanctum` et
     * `Illuminate\Support\Str` comme des dépendances manquantes, sur huit fichiers parfaitement
     * sains. Une sonde qui crie partout ne se lit plus.
     */
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
     * Lus dans `installed.json` plutôt que devinés : c'est composer qui sait quel paquet est déclaré
     * en développement, et quels préfixes il enregistre.
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

        /*
         * ON RETIRE LES ESPACES DE NOMS QUE L'APPLICATION DÉCLARE ELLE-MÊME.
         *
         * `laravel/pint` enregistre le préfixe `App\` — son propre code l'utilise. Sans ce retrait,
         * la sonde prenait TOUTE classe applicative pour une dépendance de développement et
         * signalait `App\Models\…` et `App\Http\Middleware\…` comme introuvables en production.
         *
         * La règle est de principe et pas un rustinage : un espace de noms fourni par
         * l'autochargement de production N'EST PAS de développement, quel que soit le paquet de dev
         * qui le revendique aussi.
         */
        $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);
        $siens = array_keys($composer['autoload']['psr-4'] ?? []);

        return array_values(array_diff(array_unique($prefixes), $siens));
    }

    /**
     * Exécute un fichier dans un processus neuf, sans autochargeur, et rend la classe de
     * développement qui a manqué — ou `null`.
     *
     * @param  list<string>  $prefixes
     */
    private function classeDeDevManquante(string $fichier, array $prefixes): ?string
    {
        /*
         * Les aides du framework sont bouchonnées : elles viennent d'une dépendance de PRODUCTION,
         * leur absence ici est un artefact du processus nu et non le défaut qu'on traque.
         */
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
