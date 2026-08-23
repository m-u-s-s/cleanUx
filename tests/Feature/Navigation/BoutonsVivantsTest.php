<?php

namespace Tests\Feature\Navigation;

use Tests\TestCase;

/** UN BOUTON QUI APPELLE UNE MÉTHODE ABSENTE NE SE VOIT QU'AU CLIC. */
class BoutonsVivantsTest extends TestCase
{
    /**
     * PLUS AUCUN CAS CONNU — et cette liste doit le rester.
     *
     * @var list<string>
     */
    private const CONNUS = [];

    public function test_aucun_bouton_n_appelle_une_methode_absente(): void
    {
        $morts = [];
        $verifiees = 0;

        foreach ($this->composantsAvecVue() as [$classe, $vue, $chemin]) {
            $verifiees++;
            $methodes = get_class_methods($classe);
            $html = (string) file_get_contents($vue);

            preg_match_all(
                '/wire:(?:click|submit|change)(?:\.[a-z.]+)?="\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*[("]?/',
                $html,
                $appels
            );

            foreach (array_unique($appels[1]) as $methode) {
                if (in_array($methode, ['null', 'true', 'false'], true)) {
                    continue;
                }

                if (in_array($methode, $methodes, true)) {
                    continue;
                }

                $cle = $chemin.'::'.$methode;

                if (! in_array($cle, self::CONNUS, true)) {
                    $morts[] = $cle;
                }
            }
        }

        // Sans plancher, un motif cassé ne trouverait plus rien et le test passerait au vert
        // en ne mesurant plus rien du tout.
        $this->assertGreaterThan(100, $verifiees, 'Le contrôle ne lit plus les vues : motif à revoir');

        $this->assertSame([], $morts, sprintf(
            "%d bouton(s) appellent une méthode que leur composant n'a pas — 500 au clic :\n%s",
            count($morts),
            implode("\n", $morts)
        ));
    }

    /** @return list<array{0: class-string, 1: string, 2: string}> */
    private function composantsAvecVue(): array
    {
        $trouves = [];
        $base = app_path('Livewire');

        $iterateur = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base));

        foreach ($iterateur as $fichier) {
            if (! $fichier->isFile() || ! str_ends_with($fichier->getFilename(), '.php')) {
                continue;
            }

            $code = (string) file_get_contents($fichier->getPathname());

            if (! preg_match("/view\(\s*'([a-z0-9_.\-]+)'/", $code, $m)) {
                continue;
            }

            $chemin = 'resources/views/'.str_replace('.', '/', $m[1]).'.blade.php';
            $vue = base_path($chemin);

            if (! file_exists($vue)) {
                continue;
            }

            if (! preg_match('/namespace\s+([^;]+);/', $code, $ns)
                || ! preg_match('/class\s+([A-Za-z0-9_]+)/', $code, $cl)) {
                continue;
            }

            $classe = trim($ns[1]).chr(92).$cl[1];

            if (class_exists($classe)) {
                $trouves[] = [$classe, $vue, $chemin];
            }
        }

        return $trouves;
    }
}
