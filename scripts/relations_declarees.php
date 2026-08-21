<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/*
 * Les `use` PRÉCÈDENT le code exécutable — voir `audit_schema.php` : Pint range les imports en tête
 * du bloc d'instructions, et un `use` qui atterrit après son usage ne s'applique pas.
 */

/**
 * CE QUE LES MODÈLES DÉCLARENT VRAIMENT COMME RELATION.
 *
 * Pour poser une clé étrangère il faut connaître la table parente. La déduire du nom de la colonne
 * marche pour `commune_id` → `communes` et échoue partout ailleurs :
 *
 *   `rendez_vous_id`   → la table `rendez_vous` N'EXISTE PLUS. La colonne pointe vers `bookings`
 *                        depuis la fusion, et seuls les modèles le disent.
 *   `sender_id`        → `senders` n'existe pas ; c'est `users`.
 *   `preferred_employee_id`, `chosen_user_id`, `actor_user_id` → `users`, sans que le nom le dise.
 *
 * Ce script interroge donc chaque `belongsTo()` déclaré, et rend le couple exact
 * (table, colonne) → table parente. C'est la même leçon que `getTable()` : la vérité est dans le
 * code, jamais dans le nom.
 *
 * Lecture seule. Usage : php scripts/relations_declarees.php
 */
require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$racine = realpath(__DIR__.'/../app').DIRECTORY_SEPARATOR;
$fichiers = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(realpath(__DIR__.'/../app/Models')));

$relations = [];
$echecs = 0;

foreach ($fichiers as $f) {
    if ($f->isDir() || $f->getExtension() !== 'php') {
        continue;
    }

    $classe = 'App\\'.str_replace(DIRECTORY_SEPARATOR, '\\', substr($f->getRealPath(), strlen($racine), -4));

    if (! class_exists($classe)) {
        continue;
    }

    try {
        $reflexion = new ReflectionClass($classe);

        if ($reflexion->isAbstract() || ! $reflexion->isSubclassOf(Model::class)) {
            continue;
        }

        $modele = new $classe;
    } catch (Throwable $e) {
        continue;
    }

    foreach ($reflexion->getMethods(ReflectionMethod::IS_PUBLIC) as $methode) {
        // Une relation est une méthode sans argument, déclarée par la classe elle-même.
        if ($methode->getNumberOfParameters() > 0 || $methode->class !== $classe || $methode->isStatic()) {
            continue;
        }

        try {
            $retour = $methode->invoke($modele);
        } catch (Throwable $e) {
            // Une méthode publique quelconque peut lever : elle n'est simplement pas une relation.
            $echecs++;

            continue;
        }

        if (! $retour instanceof BelongsTo) {
            continue;
        }

        $relations[$modele->getTable().'.'.$retour->getForeignKeyName()] = $retour->getRelated()->getTable();
    }
}

file_put_contents(
    __DIR__.'/../graphify-out/relations-declarees.json',
    json_encode($relations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);

printf("relations belongsTo trouvées : %d\n", count($relations));
printf("méthodes écartées (non relations) : %d\n", $echecs);

/* Ce qui manque encore une contrainte ET dont on connaît désormais le parent. */
$audit = json_decode(file_get_contents(__DIR__.'/../graphify-out/audit-schema.json'), true);
$base = DB::getDatabaseName();
$colonnes = collect(DB::select('SELECT TABLE_NAME t, COLUMN_NAME c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ?', [$base]))
    ->groupBy('t')->map(fn ($g) => $g->pluck('c')->all());

$aPoser = [];
foreach ($audit['sans_fk'] as $x) {
    if ($x['type'] !== 'bigint') {
        continue;
    }

    // Une colonne polymorphe a une jumelle `*_type` : aucune contrainte ne peut l'exprimer.
    if (in_array(substr($x['colonne'], 0, -3).'_type', $colonnes[$x['table']] ?? [], true)) {
        continue;
    }

    $cle = $x['table'].'.'.$x['colonne'];

    if (isset($relations[$cle])) {
        $aPoser[] = ['t' => $x['table'], 'c' => $x['colonne'], 'p' => $relations[$cle], 'nul' => $x['nullable']];
    }
}

file_put_contents(
    __DIR__.'/../graphify-out/fk-par-relation.json',
    json_encode($aPoser, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);

printf("colonnes sans contrainte dont le modèle donne le parent : %d\n", count($aPoser));
