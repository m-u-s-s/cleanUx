<?php

use Illuminate\Contracts\Console\Kernel;

/**
 * Chaque garde `class_exists(X::class)` d'un fichier de routes désigne-t-elle une classe
 * qui existe vraiment ?
 *
 * Ces gardes rendent une route SILENCIEUSEMENT absente quand la classe est renommée :
 * pas d'erreur, pas de test rouge, juste un écran qui n'existe plus.
 */
$racine = __DIR__.'/..';
require $racine.'/vendor/autoload.php';
$app = require $racine.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$total = 0;
$manquantes = [];

foreach (glob($racine.'/routes/*.php') as $fichier) {
    $code = (string) file_get_contents($fichier);

    // `use A\B\C;` ET `use A\B\C as Alias;` : sans le second, un alias passe pour
    // une classe absente — le contrôle crierait au loup sur du code parfaitement sain.
    preg_match_all('/^use\s+([A-Za-z0-9_'.chr(92).chr(92).']+)(?:\s+as\s+([A-Za-z0-9_]+))?;/m', $code, $u, PREG_SET_ORDER);
    $map = [];
    foreach ($u as $ligne) {
        $fqcn = $ligne[1];
        $alias = $ligne[2] ?? null;
        if ($alias) {
            $map[$alias] = $fqcn;

            continue;
        }
        $pos = strrpos($fqcn, chr(92));
        $map[$pos === false ? $fqcn : substr($fqcn, $pos + 1)] = $fqcn;
    }

    preg_match_all('/class_exists\(([A-Za-z0-9_'.chr(92).chr(92).']+)::class\)/', $code, $m);
    foreach ($m[1] as $court) {
        $total++;
        $fqcn = $map[$court] ?? $court;
        if (! class_exists($fqcn)) {
            $manquantes[] = basename($fichier).'  →  '.$fqcn;
        }
    }
}

echo 'gardes class_exists : '.$total.PHP_EOL;
echo 'classes ABSENTES    : '.count($manquantes).PHP_EOL;
foreach ($manquantes as $x) {
    echo '   '.$x.PHP_EOL;
}
