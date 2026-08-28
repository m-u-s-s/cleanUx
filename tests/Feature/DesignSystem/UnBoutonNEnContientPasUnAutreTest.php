<?php

namespace Tests\Feature\DesignSystem;

use Tests\TestCase;

/**
 * Un `<button>` dans un `<button>` est du HTML invalide : le navigateur RESSORT l'imbrique et
 * coupe le parent. Sur /admin/countries, la derniere carte pays et son bouton se retrouvaient
 * hors du conteneur de page, colles au bas de l'ecran.
 */
class UnBoutonNEnContientPasUnAutreTest extends TestCase
{
    /** @return array<int, string> Les vues ou la profondeur de `<button>` depasse un. */
    private function vuesFautives(): array
    {
        $fautives = [];
        $base = resource_path('views');
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base));

        foreach ($it as $fichier) {
            if (! $fichier->isFile() || ! str_ends_with($fichier->getFilename(), '.blade.php')) {
                continue;
            }

            $rel = str_replace(DIRECTORY_SEPARATOR, '/', substr($fichier->getPathname(), strlen($base) + 1));

            // `scribe/` est regeneree par une commande : son contenu n'est pas ecrit ici.
            if (str_starts_with($rel, 'scribe/')) {
                continue;
            }

            if ($this->profondeurMaximale((string) file_get_contents($fichier->getPathname())) > 1) {
                $fautives[] = $rel;
            }
        }

        sort($fautives);

        return $fautives;
    }

    private function profondeurMaximale(string $source): int
    {
        // Les commentaires Blade peuvent citer du balisage : ils ne rendent rien.
        $source = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $source);

        preg_match_all('/<button\b|<\/button>/i', $source, $trouves);

        $profondeur = 0;
        $maximum = 0;

        foreach ($trouves[0] as $balise) {
            $profondeur += str_starts_with(strtolower($balise), '</') ? -1 : 1;
            $profondeur = max(0, $profondeur);
            $maximum = max($maximum, $profondeur);
        }

        return $maximum;
    }

    public function test_aucune_vue_n_imbrique_deux_boutons(): void
    {
        $this->assertSame([], $this->vuesFautives(),
            'Un bouton en contient un autre : le navigateur les ressortira du conteneur de page.');
    }

    /**
     * TEMOIN. Sans lui, le test ci-dessus resterait vert si le compteur ne voyait plus aucune
     * balise — il mesurerait alors sa propre panne.
     */
    public function test_temoin_le_compteur_reconnait_l_imbrication(): void
    {
        $this->assertSame(2, $this->profondeurMaximale('<button><span><button>x</button></span></button>'));
        $this->assertSame(1, $this->profondeurMaximale('<button>a</button><button>b</button>'));
        $this->assertSame(0, $this->profondeurMaximale('{{-- <button><button> --}}'));
    }
}
