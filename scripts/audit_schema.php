<?php

/**
 * AUDIT DU SCHÉMA — l'inventaire sur lequel s'appuie le travail d'optimisation.
 *
 * Il répond à quatre questions, et à AUCUNE par le nom des choses :
 *
 *   1. Quelle table chaque modèle utilise vraiment ? — `getTable()`, jamais une déduction sur le
 *      nom du fichier. Eloquent déduit `academy_courses` de `AcademyCourse` : chercher la chaîne
 *      « academy_courses » dans le code ne la trouve nulle part et la déclare morte à tort. C'est
 *      exactement l'erreur qu'une première version de cet audit a commise.
 *   2. Quelles colonnes de jointure (`*_id`) n'ont pas d'index de tête ? Ce sont elles qui
 *      transforment chaque jointure en balayage complet dès que la table grossit.
 *   3. Quelles colonnes de jointure n'ont aucune clé étrangère ? Rien ne garantit alors qu'elles
 *      pointent vers une ligne qui existe.
 *   4. Quelles tables n'ont ni modèle, ni mention littérale dans le code applicatif ?
 *
 * Lecture seule. Il n'écrit que son rapport.
 *
 * Usage : php scripts/audit_schema.php [chemin/de/sortie.json]
 */
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/*
 * Les `use` PRÉCÈDENT le code exécutable, et ce n'est pas une question de style.
 *
 * Pint range les imports en tête du bloc d'instructions et raccourcit les noms complets. Quand du
 * code exécutable ouvre le fichier, il place donc les `use` APRÈS lui — et un `use` placé après son
 * usage ne s'applique pas : `Kernel::class` valait alors la chaîne « Kernel », que le conteneur ne
 * sait pas résoudre. Le script tournait, puis a cessé de tourner sans qu'une ligne de sa logique
 * ait changé.
 */
require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$sortie = $argv[1] ?? __DIR__.'/../graphify-out/audit-schema.json';

/* ── 1. table → modèles, demandé à Eloquent ────────────────────────────────────────────────── */

$parTable = [];
$racine = realpath(__DIR__.'/../app').DIRECTORY_SEPARATOR;
$fichiers = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(realpath(__DIR__.'/../app/Models')));

foreach ($fichiers as $f) {
    if ($f->isDir() || $f->getExtension() !== 'php') {
        continue;
    }

    $relatif = substr($f->getRealPath(), strlen($racine), -4);
    $classe = 'App\\'.str_replace(DIRECTORY_SEPARATOR, '\\', $relatif);

    if (! class_exists($classe)) {
        continue;
    }

    try {
        $reflexion = new ReflectionClass($classe);

        if ($reflexion->isAbstract() || ! $reflexion->isSubclassOf(Model::class)) {
            continue;
        }

        $parTable[(new $classe)->getTable()][] = $classe;
    } catch (Throwable $e) {
        // Un modèle qu'on ne peut pas instancier ne prouve rien : on l'écarte du compte plutôt que
        // de conclure que sa table est morte.
    }
}

/* ── 2. le schéma réel ─────────────────────────────────────────────────────────────────────── */

$base = DB::getDatabaseName();

$tables = collect(DB::select(
    'SELECT TABLE_NAME n, TABLE_ROWS r, ROUND((DATA_LENGTH+INDEX_LENGTH)/1024) kb
       FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?',
    [$base]
));

$colonnesId = DB::select(
    "SELECT TABLE_NAME t, COLUMN_NAME c, DATA_TYPE d, IS_NULLABLE nul
       FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = ? AND COLUMN_NAME LIKE '%\_id' AND COLUMN_NAME <> 'id'",
    [$base]
);

$avecFk = collect(DB::select(
    'SELECT TABLE_NAME t, COLUMN_NAME c, REFERENCED_TABLE_NAME rt
       FROM information_schema.KEY_COLUMN_USAGE
      WHERE TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
    [$base]
))->keyBy(fn ($r) => $r->t.'.'.$r->c);

// SEQ_IN_INDEX = 1 : la colonne est EN TÊTE d'un index. Une colonne en seconde position d'un index
// composite n'est pas utilisable seule — la compter serait se mentir.
$enTeteDIndex = collect(DB::select(
    'SELECT TABLE_NAME t, COLUMN_NAME c
       FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = ? AND SEQ_IN_INDEX = 1',
    [$base]
))->keyBy(fn ($r) => $r->t.'.'.$r->c);

/*
 * L'EXCEPTION POLYMORPHE, ET POURQUOI ELLE COMPTE.
 *
 * Une relation polymorphe se filtre TOUJOURS sur le couple : `where(type)->where(id)`. Le bon
 * index est donc `(type, id)`, où l'identifiant occupe la SECONDE position. La règle « en tête
 * d'un index » ci-dessus les signale alors toutes comme non indexées — à tort.
 *
 * Mesuré : les 21 colonnes polymorphes de ce schéma portent déjà leur composite, Laravel les
 * créant lui-même pour `notifications` et `personal_access_tokens`. Sans cette exception, l'audit
 * réclamait 21 index simples redondants, qui n'auraient rien accéléré et auraient ralenti chaque
 * écriture.
 */
$couplePolymorphe = collect(DB::select(
    "SELECT s1.TABLE_NAME t, s2.COLUMN_NAME c
       FROM information_schema.STATISTICS s1
       JOIN information_schema.STATISTICS s2
         ON s1.TABLE_SCHEMA = s2.TABLE_SCHEMA
        AND s1.TABLE_NAME  = s2.TABLE_NAME
        AND s1.INDEX_NAME  = s2.INDEX_NAME
      WHERE s1.TABLE_SCHEMA = ?
        AND s1.SEQ_IN_INDEX = 1
        AND s2.SEQ_IN_INDEX = 2
        AND s1.COLUMN_NAME = CONCAT(SUBSTRING(s2.COLUMN_NAME, 1, CHAR_LENGTH(s2.COLUMN_NAME) - 3), '_type')",
    [$base]
))->keyBy(fn ($r) => $r->t.'.'.$r->c);

/* ── 3. les manques ────────────────────────────────────────────────────────────────────────── */

$sansIndex = [];
$sansFk = [];

foreach ($colonnesId as $col) {
    $cle = $col->t.'.'.$col->c;
    $lignes = (int) ($tables->firstWhere('n', $col->t)->r ?? 0);

    if (! isset($enTeteDIndex[$cle]) && ! isset($couplePolymorphe[$cle])) {
        $sansIndex[] = ['table' => $col->t, 'colonne' => $col->c, 'type' => $col->d, 'lignes' => $lignes];
    }

    if (! isset($avecFk[$cle])) {
        $sansFk[] = ['table' => $col->t, 'colonne' => $col->c, 'type' => $col->d, 'nullable' => $col->nul];
    }
}

/* ── 4. tables sans modèle ET sans mention littérale ───────────────────────────────────────── */

$codeApplicatif = [];
foreach (['app', 'routes', 'config', 'resources/views'] as $dossier) {
    $chemin = realpath(__DIR__.'/../'.$dossier);
    if (! $chemin) {
        continue;
    }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($chemin)) as $f) {
        if (! $f->isDir() && in_array($f->getExtension(), ['php'], true)) {
            $codeApplicatif[] = file_get_contents($f->getRealPath());
        }
    }
}
$toutLeCode = implode("\n", $codeApplicatif);

$orphelines = [];
foreach ($tables as $t) {
    if (isset($parTable[$t->n])) {
        continue;
    }
    if (str_contains($toutLeCode, $t->n)) {
        $orphelines[] = ['table' => $t->n, 'lignes' => (int) $t->r, 'kb' => (int) $t->kb, 'cite' => true];

        continue;
    }
    $orphelines[] = ['table' => $t->n, 'lignes' => (int) $t->r, 'kb' => (int) $t->kb, 'cite' => false];
}

$rapport = [
    'base' => $base,
    'tables_total' => $tables->count(),
    'tables_avec_modele' => count($parTable),
    'colonnes_id_total' => count($colonnesId),
    'sans_index_total' => count($sansIndex),
    'sans_fk_total' => count($sansFk),
    'sans_modele_ni_mention' => count(array_filter($orphelines, fn ($o) => ! $o['cite'])),
    'sans_index' => $sansIndex,
    'sans_fk' => $sansFk,
    'orphelines' => $orphelines,
];

file_put_contents($sortie, json_encode($rapport, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

printf("base                      : %s\n", $base);
printf("tables                    : %d (dont %d adossées à un modèle)\n", $tables->count(), count($parTable));
printf("colonnes de jointure      : %d\n", count($colonnesId));
printf("  sans index de tête      : %d\n", count($sansIndex));
printf("  sans clé étrangère      : %d\n", count($sansFk));
printf("tables sans modèle NI mention : %d\n", $rapport['sans_modele_ni_mention']);
printf("rapport                   : %s\n", realpath($sortie) ?: $sortie);
