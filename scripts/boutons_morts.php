<?php

use Illuminate\Contracts\Console\Kernel;

/**
 * LES BOUTONS QUI APPELLENT UNE MÉTHODE QUI N'EXISTE PAS.
 *
 * `wire:click="maMethode"` sur une vue dont le composant n'expose pas `maMethode` ne casse
 * rien au chargement : la page s'affiche, le bouton paraît normal. Il rend 500 au CLIC —
 * « Unable to call component method » — et seul un humain qui clique le découvre.
 *
 * On rapproche donc chaque vue Livewire de son composant, et on vérifie que ce que la vue
 * appelle existe bel et bien.
 */
$racine = __DIR__.'/..';
require $racine.'/vendor/autoload.php';
$app = require $racine.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

/** Le composant Livewire qui rend cette vue, s'il est identifiable. */
function composantDe(string $vue, string $racine): ?string
{
    $prefixe = $racine.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR;
    $relatif = str_replace($prefixe, '', $vue);
    $relatif = str_replace(DIRECTORY_SEPARATOR, '/', $relatif);
    $relatif = str_replace('.blade.php', '', $relatif);

    if (! str_starts_with($relatif, 'livewire/')) {
        return null;
    }

    $nom = str_replace('livewire/', '', $relatif);
    $morceaux = array_map(
        fn (string $m) => str_replace(' ', '', ucwords(str_replace('-', ' ', $m))),
        explode('/', $nom)
    );

    return 'App'.chr(92).'Livewire'.chr(92).implode(chr(92), $morceaux);
}

$vues = [];
$iterateur = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($racine.'/resources/views/livewire'));
foreach ($iterateur as $fichier) {
    if ($fichier->isFile() && str_ends_with($fichier->getFilename(), '.blade.php')) {
        $vues[] = $fichier->getPathname();
    }
}

$morts = [];
$verifiees = 0;

foreach ($vues as $vue) {
    $classe = composantDe($vue, $racine);

    if ($classe === null || ! class_exists($classe)) {
        continue;
    }

    $verifiees++;
    $code = (string) file_get_contents($vue);

    // `wire:click="methode(...)"` — on ne garde que le nom, sans les arguments.
    preg_match_all('/wire:(?:click|submit|change|keydown[^=]*)(?:\.[a-z.]+)?="\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*\(?/', $code, $m);

    foreach (array_unique($m[1]) as $methode) {
        // Les mots réservés de Livewire ne sont pas des méthodes du composant.
        if (in_array($methode, ['$refresh', '$set', '$toggle', '$dispatch', '$parent', 'null', 'true', 'false'], true)) {
            continue;
        }

        if (! method_exists($classe, $methode)) {
            $morts[] = str_replace($racine.DIRECTORY_SEPARATOR, '', $vue).'  →  '.$methode.'()  absente de  '.class_basename($classe);
        }
    }
}

echo 'vues rapprochées de leur composant : '.$verifiees.PHP_EOL;
echo 'appels vers une méthode ABSENTE    : '.count($morts).PHP_EOL;
foreach ($morts as $x) {
    echo '   '.$x.PHP_EOL;
}
