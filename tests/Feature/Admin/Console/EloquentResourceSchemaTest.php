<?php

namespace Tests\Feature\Admin\Console;

use App\Admin\Console\EloquentResource;
use App\Admin\Console\ResourceRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Chaque colonne déclarée par un descripteur existe-t-elle vraiment ? POURQUOI. */
class EloquentResourceSchemaTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string}> */
    public static function descripteursEloquent(): array
    {
        /** @var array{modules: list<array{key: string, coverage: string}>} $registre */
        $registre = require dirname(__DIR__, 4).'/config/admin_console.php';

        $cas = [];

        foreach ($registre['modules'] as $module) {
            if ($module['coverage'] === 'descriptor') {
                $cas[$module['key']] = [$module['key']];
            }
        }

        // Un fournisseur vide rendrait ce fichier vert sans rien éprouver.
        return $cas === [] ? ['aucun descripteur' => ['__aucun__']] : $cas;
    }

    #[DataProvider('descripteursEloquent')]
    public function test_chaque_colonne_declaree_existe_sur_la_table(string $resource): void
    {
        $descripteur = app(ResourceRegistry::class)->for($resource);
        $this->assertNotNull($descripteur, "Descripteur introuvable pour « {$resource} ».");

        if (! $descripteur instanceof EloquentResource) {
            // Les descripteurs écrits à la main portent leurs propres tests ; ce fichier ne
            // vérifie que ceux qui déclarent leurs colonnes en table.
            $this->addToAssertionCount(1);

            return;
        }

        $modele = $descripteur->query()->getModel();
        $this->assertInstanceOf(Model::class, $modele);

        $table = $modele->getTable();
        $this->assertTrue(Schema::hasTable($table), "La table « {$table} » n’existe pas.");

        $colonnesReelles = Schema::getColumnListing($table);

        $manquantes = [];

        foreach ($descripteur->columns() as $colonne) {
            $cle = $colonne->toArray()['key'];

            // Une clé de relation est traversée par `data_get`, pas lue en colonne.
            if (str_contains($cle, '.')) {
                continue;
            }

            // Un accesseur du modèle n'a pas de colonne, et c'est légitime.
            if ($modele->hasGetMutator($cle) || $modele->hasAttributeGetMutator($cle)) {
                continue;
            }

            if (! in_array($cle, $colonnesReelles, true)) {
                $manquantes[] = $cle;
            }
        }

        $this->assertSame([], $manquantes, sprintf(
            'Colonnes déclarées par « %s » et absentes de la table « %s » : %s. '
            .'Elles afficheraient « — » sur toute la colonne sans que rien ne le signale.',
            $resource,
            $table,
            implode(', ', $manquantes),
        ));
    }

    #[DataProvider('descripteursEloquent')]
    public function test_la_colonne_de_tri_par_defaut_existe(string $resource): void
    {
        $descripteur = app(ResourceRegistry::class)->for($resource);
        $this->assertNotNull($descripteur);

        if (! $descripteur instanceof EloquentResource) {
            $this->addToAssertionCount(1);

            return;
        }

        $table = $descripteur->query()->getModel()->getTable();

        foreach ($descripteur->sorts() as $tri) {
            // Un tri déclaré sur une colonne absente échoue à l'exécution, pas au démarrage : la
            // liste rendrait un 500 dès qu'on demande ce tri.
            $this->assertContains($tri, Schema::getColumnListing($table),
                "Le tri « {$tri} » de « {$resource} » ne correspond à aucune colonne de « {$table} ».");
        }

        $this->assertContains($descripteur->defaultSort(), $descripteur->sorts(),
            "Le tri par défaut de « {$resource} » n’est pas dans la liste des tris autorisés.");
    }
}
