<?php

namespace Tests\Feature\Views;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Une vue Blade doit produire du PHP VALIDE.
 *
 * `Blade::compileString()` ne suffit pas : il transforme la vue en PHP sans jamais verifier que
 * ce PHP se parse. Deux vues sont passees cassees a travers ce controle — une substitution avait
 * avale une plage `{{ min }}–{{ max }} EUR` en entier — et la suite complete l'a decouvert
 * 28 erreurs plus loin, dans des tests qui n'avaient rien a voir.
 *
 * Ici on compile PUIS on parse.
 */
class ChaqueVueProduitDuPhpValideTest extends TestCase
{
    /** @return list<string> */
    private function vues(): array
    {
        $iterateur = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));
        $vues = [];

        foreach ($iterateur as $fichier) {
            if ($fichier->isFile() && str_ends_with($fichier->getFilename(), '.blade.php')) {
                $vues[] = $fichier->getPathname();
            }
        }

        sort($vues);

        return $vues;
    }

    /**
     * TEMOIN — le controle sait reconnaitre du PHP invalide.
     * Sans lui, un `php -l` qui repondrait toujours « pas d'erreur » ferait passer le garde.
     */
    public function test_temoin_le_controle_repere_du_php_invalide(): void
    {
        $this->assertTrue($this->sePasse('<?php $x = 1;'), 'Du PHP valide est refuse.');
        $this->assertFalse($this->sePasse('<?php $x = ,;'), 'Du PHP invalide passe pour bon.');
        $this->assertGreaterThan(400, count($this->vues()), 'Le balayage ne voit presque aucune vue.');
    }

    public function test_chaque_vue_produit_du_php_qui_se_parse(): void
    {
        $cassees = [];
        $racine = str_replace(chr(92), '/', resource_path('views'));

        foreach ($this->vues() as $chemin) {
            $php = Blade::compileString((string) file_get_contents($chemin));

            if ($this->sePasse($php)) {
                continue;
            }

            $cassees[] = ltrim(str_replace($racine, '', str_replace(chr(92), '/', $chemin)), '/');
        }

        $this->assertSame([], $cassees, 'Ces vues compilent, mais le PHP produit ne se parse pas.');
    }

    /**
     * Demande a l'analyseur de PHP s'il parse ce code.
     *
     * `TOKEN_PARSE` fait lever `ParseError` sur du code invalide — sans ecrire de fichier ni
     * lancer de processus. La version a `php -l` prenait CINQ MINUTES sur 596 vues.
     */
    private function sePasse(string $php): bool
    {
        try {
            token_get_all($php, TOKEN_PARSE);

            return true;
        } catch (\ParseError $e) {
            return false;
        }
    }
}
