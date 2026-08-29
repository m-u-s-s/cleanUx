<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * UNE VUE LIVEWIRE N'A QU'UNE RACINE, ET LE RESTE DISPARAIT EN SILENCE.
 *
 * Livewire rend et morphose LE PREMIER element racine. Une modale posee a cote — un
 * `@if ($showForm) <div>…</div> @endif` apres le `</div>` principal — n'apparait jamais :
 * le serveur bascule bien le drapeau, la reponse dit `showForm: true`, et l'ecran ne bouge
 * pas. Ni la suite ni PHPStan ne le voient, parce que rien n'echoue.
 *
 * Cette commande sert l'audit ; le test d'architecture du meme nom tient la garde.
 */
class AuditerLesRacinesDeVuesLivewire extends Command
{
    protected $signature = 'qa:racines-livewire {--json : Sortie machine}';

    protected $description = 'Liste les vues Livewire qui déclarent plus d’un élément racine.';

    public function handle(): int
    {
        $fautives = self::vuesAPlusieursRacines();

        if ($this->option('json')) {
            $this->line((string) json_encode($fautives, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $fautives === [] ? self::SUCCESS : self::FAILURE;
        }

        foreach ($fautives as $vue => $racines) {
            $this->line(sprintf('%-72s %d racines', $vue, $racines));
        }

        $this->line('');
        $this->{$fautives === [] ? 'info' : 'error'}(count($fautives).' vue(s) à plusieurs racines');

        return $fautives === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array<string, int> chemin relatif => nombre de racines
     */
    public static function vuesAPlusieursRacines(): array
    {
        $fautives = [];
        $racine = resource_path('views/livewire');

        if (! is_dir($racine)) {
            return [];
        }

        foreach (File::allFiles($racine) as $fichier) {
            if (! str_ends_with($fichier->getFilename(), '.blade.php')) {
                continue;
            }

            $source = (string) file_get_contents($fichier->getPathname());

            // Les fragments inclus par `@include` ne sont pas des vues de composant : ils
            // n'ont pas de racine a eux. On ne juge que ce qu'un composant peut rendre.
            if (self::estUnFragment($fichier->getPathname(), $source)) {
                continue;
            }

            $racines = self::compterLesRacines($source);

            if ($racines > 1) {
                $fautives[self::relatif($fichier->getPathname())] = $racines;
            }
        }

        ksort($fautives);

        return $fautives;
    }

    /**
     * COMPTER LES RACINES SANS ANALYSER TOUT LE HTML.
     *
     * On retire commentaires, directives et scripts, puis on suit la profondeur des balises
     * de premier niveau. Ce qui remonte a zero et repart est une SECONDE racine.
     */
    private static function compterLesRacines(string $source): int
    {
        // Ce qui ne compte pas : commentaires Blade, `@push`/`@endpush`, directives seules.
        $nu = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $source);
        $nu = (string) preg_replace('/@push\(.*?@endpush/s', '', $nu);
        $nu = (string) preg_replace('/<!--.*?-->/s', '', $nu);

        $profondeur = 0;
        $racines = 0;

        // Les balises sans fermeture : elles ne changent pas la profondeur.
        $orphelines = ['br', 'hr', 'img', 'input', 'meta', 'link', 'source', 'track', 'wbr', 'col', 'area', 'base', 'embed', 'param'];

        preg_match_all('/<(\/?)([a-zA-Z][a-zA-Z0-9-]*)([^>]*)>/', $nu, $balises, PREG_SET_ORDER);

        foreach ($balises as $balise) {
            [$entier, $fermante, $nom, $attributs] = $balise;
            $nom = strtolower($nom);

            if (in_array($nom, $orphelines, true) || str_ends_with(trim($attributs), '/')) {
                continue;
            }

            if ($fermante === '/') {
                $profondeur--;

                continue;
            }

            if ($profondeur === 0) {
                $racines++;
            }

            $profondeur++;
        }

        return $racines;
    }

    /** Un fragment se reconnait a ce qu'aucun composant ne le rend directement. */
    private static function estUnFragment(string $chemin, string $source): bool
    {
        $relatif = self::relatif($chemin);

        // Convention du depot : les partiels vivent dans un dossier dedie ou commencent
        // par un underscore.
        if (preg_match('#/(partials|fragments|includes)/#', $relatif) === 1) {
            return true;
        }

        if (str_starts_with(basename($chemin), '_')) {
            return true;
        }

        /*
         * LA REGLE JUSTE : est une VUE DE COMPOSANT celle qu'une classe de `app/Livewire`
         * rend, ou qu'une balise `<livewire:` monte. Tout le reste est un fragment — y
         * compris ceux inclus par un nom CALCULE, que chercher en litteral rate.
         */
        $nomDeVue = str_replace(['resources/views/', '.blade.php', '/'], ['', '', '.'], $relatif);

        static $rendues = null;

        if ($rendues === null) {
            $rendues = '';

            foreach (File::allFiles(app_path('Livewire')) as $f) {
                if ($f->getExtension() === 'php') {
                    $rendues .= (string) file_get_contents($f->getPathname());
                }
            }
        }

        return ! str_contains($rendues, "'".$nomDeVue."'")
            && ! str_contains($rendues, '"'.$nomDeVue.'"');
    }

    private static function relatif(string $chemin): string
    {
        return str_replace([base_path().DIRECTORY_SEPARATOR, '\\'], ['', '/'], $chemin);
    }
}
