<?php

namespace Tests\Feature\Schema;

use App\Models\Booking;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use Tests\TestCase;

/** CHAQUE MODÈLE DOIT CONCORDER AVEC LE SCHÉMA — ET C'EST VÉRIFIABLE. */
class LesModelesConcordentAvecLeSchemaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * LES SEULES EXCEPTIONS, ET LEUR RAISON.
     *
     * @var array<class-string, list<string>>
     */
    private const VIRTUELLES = [
        // `surface` est un pont vers `surface_range`, voir l'accesseur `surface()` du modèle.
        Booking::class => ['surface'],
    ];

    /** @return list<class-string<Model>> */
    private function modeles(): array
    {
        $classes = [];
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path('Models')));

        foreach ($it as $f) {
            if (! $f->isFile() || $f->getExtension() !== 'php') {
                continue;
            }

            $src = (string) file_get_contents($f->getPathname());
            if (! preg_match('/namespace\s+([^;]+);/', $src, $ns) || ! preg_match('/class\s+(\w+)/', $src, $cl)) {
                continue;
            }

            $classe = trim($ns[1]).'\\'.$cl[1];
            if (! class_exists($classe)) {
                continue;
            }

            $refl = new ReflectionClass($classe);
            if ($refl->isAbstract() || ! $refl->isSubclassOf(Model::class)) {
                continue;
            }

            $classes[] = $classe;
        }

        sort($classes);

        return $classes;
    }

    public function test_temoin_le_balayage_trouve_bien_les_modeles(): void
    {
        // TÉMOIN POSITIF.
        $modeles = $this->modeles();

        $this->assertGreaterThan(150, count($modeles), 'Le balayage des modèles ne rend presque rien.');
        $this->assertContains(Booking::class, $modeles);
    }

    public function test_aucun_fillable_ne_designe_une_colonne_absente(): void
    {
        $ecarts = [];

        foreach ($this->modeles() as $classe) {
            /** @var Model $m */
            $m = (new ReflectionClass($classe))->newInstanceWithoutConstructor();
            $table = $m->getTable();

            if (! Schema::hasTable($table)) {
                continue;   // une table absente est le sujet de l'autre test
            }

            $colonnes = Schema::getColumnListing($table);
            $tolerees = self::VIRTUELLES[$classe] ?? [];

            foreach ($m->getFillable() as $champ) {
                if ($champ === '*' || in_array($champ, $colonnes, true) || in_array($champ, $tolerees, true)) {
                    continue;
                }
                if ($m->hasSetMutator($champ) || $m->hasAttributeSetMutator($champ)) {
                    continue;
                }
                $ecarts[] = sprintf('%s::$fillable → `%s` (table `%s`)', class_basename($classe), $champ, $table);
            }
        }

        $this->assertSame([], $ecarts, "Ces champs autorisent une écriture qui n'atteindra aucune colonne.");
    }

    public function test_aucun_cast_ne_designe_une_colonne_absente(): void
    {
        $ecarts = [];

        foreach ($this->modeles() as $classe) {
            /** @var Model $m */
            $m = (new ReflectionClass($classe))->newInstanceWithoutConstructor();
            $table = $m->getTable();

            if (! Schema::hasTable($table)) {
                continue;
            }

            $colonnes = Schema::getColumnListing($table);
            $tolerees = self::VIRTUELLES[$classe] ?? [];

            foreach (array_keys($m->getCasts()) as $champ) {
                if (in_array($champ, $colonnes, true) || in_array($champ, $tolerees, true)) {
                    continue;
                }
                if ($m->hasGetMutator($champ) || $m->hasAttributeGetMutator($champ)
                    || $m->hasSetMutator($champ) || $m->hasAttributeSetMutator($champ)) {
                    continue;
                }
                if (in_array($champ, $m->getAppends(), true)) {
                    continue;
                }
                $ecarts[] = sprintf('%s::$casts → `%s` (table `%s`)', class_basename($classe), $champ, $table);
            }
        }

        $this->assertSame([], $ecarts, 'Ces transtypages portent sur des colonnes qui n’existent pas.');
    }

    /** CHAQUE MODÈLE DOIT AVOIR SA TABLE. */
    public function test_chaque_modele_a_bien_sa_table(): void
    {
        $absentes = [];

        foreach ($this->modeles() as $classe) {
            /** @var Model $m */
            $m = (new ReflectionClass($classe))->newInstanceWithoutConstructor();

            if (! Schema::hasTable($m->getTable())) {
                $absentes[] = sprintf('%s → table `%s`', class_basename($classe), $m->getTable());
            }
        }

        $this->assertSame([], $absentes, 'Ces modèles interrogent une table qui n’existe pas.');
    }

    /** `::factory()` DOIT SE RÉSOUDRE. */
    public function test_chaque_fabrique_declaree_se_resout(): void
    {
        $casses = [];

        foreach ($this->modeles() as $classe) {
            if (! in_array(HasFactory::class, class_uses_recursive($classe), true)) {
                continue;
            }

            try {
                $classe::factory();
            } catch (\Throwable $e) {
                $casses[] = class_basename($classe).' → '.Str::limit($e->getMessage(), 90);
            }
        }

        $this->assertSame([], $casses, 'Ces modèles annoncent une fabrique introuvable.');
    }

    /** LES CLÉS D'UNE FABRIQUE SONT DES COLONNES. */
    public function test_les_cles_de_chaque_fabrique_sont_des_colonnes(): void
    {
        $ecarts = [];

        foreach (glob(database_path('factories/*.php')) as $chemin) {
            $classe = 'Database\\Factories\\'.basename($chemin, '.php');

            if (! class_exists($classe)) {
                continue;
            }

            $refl = new ReflectionClass($classe);
            if ($refl->isAbstract() || ! $refl->isSubclassOf(Factory::class)) {
                continue;
            }

            try {
                $f = $classe::new();
                $modele = $f->modelName();
                /** @var Model $instance */
                $instance = new $modele;
                $table = $instance->getTable();
                $definition = $f->definition();
            } catch (\Throwable $e) {
                $ecarts[] = basename($chemin).' → '.Str::limit($e->getMessage(), 80);

                continue;
            }

            if (! Schema::hasTable($table)) {
                $ecarts[] = basename($chemin)." → table `{$table}` absente";

                continue;
            }

            $colonnes = Schema::getColumnListing($table);
            $tolerees = self::VIRTUELLES[$modele] ?? [];

            foreach (array_keys($definition) as $cle) {
                if (in_array($cle, $colonnes, true) || in_array($cle, $tolerees, true)) {
                    continue;
                }
                if ($instance->hasSetMutator($cle) || $instance->hasAttributeSetMutator($cle)) {
                    continue;
                }
                if (method_exists($instance, Str::camel($cle))) {
                    continue;   // une relation posée par la fabrique
                }
                $ecarts[] = sprintf('%s → `%s` (table `%s`)', basename($chemin), $cle, $table);
            }
        }

        $this->assertSame([], $ecarts, 'Ces fabriques posent des clés qui ne sont pas des colonnes.');
    }

    /** UNE ANNOTATION NE DOIT PAS NIER LA NULLABILITÉ DU SCHÉMA. */
    public function test_aucune_annotation_ne_nie_la_nullabilite_du_schema(): void
    {
        $ecarts = [];

        foreach ($this->modeles() as $classe) {
            /** @var Model $m */
            $m = (new ReflectionClass($classe))->newInstanceWithoutConstructor();
            $table = $m->getTable();

            if (! Schema::hasTable($table)) {
                continue;
            }

            $src = (string) file_get_contents((new ReflectionClass($classe))->getFileName());

            if (! preg_match_all('/@property(?:-read|-write)?\s+([^\s]+)\s+\$(\w+)/', $src, $trouves, PREG_SET_ORDER)) {
                continue;
            }

            foreach ($trouves as [, $type, $prop]) {
                if (! Schema::hasColumn($table, $prop)) {
                    continue;   // alias de requête ou attribut virtuel : hors sujet ici
                }

                $nullableAnnote = str_starts_with($type, '?') || stripos($type, 'null') !== false;

                if (! $nullableAnnote && $this->colonneEstNullable($table, $prop)) {
                    $ecarts[] = sprintf('%s::$%s annoté `%s` (colonne nullable)', class_basename($classe), $prop, $type);
                }
            }
        }

        $this->assertSame([], $ecarts, 'Ces annotations promettent une valeur là où le schéma admet null.');
    }

    /** La nullabilité réelle, quel que soit le moteur. */
    private function colonneEstNullable(string $table, string $colonne): bool
    {
        return Schema::getColumns($table)[
            array_search($colonne, array_column(Schema::getColumns($table), 'name'), true)
        ]['nullable'] ?? false;
    }
}
