<?php

namespace Tests\Feature\Admin;

use App\Admin\Console\ResourceRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Un champ de formulaire déclaré doit correspondre à une VRAIE colonne. POURQUOI CE FICHIER EXISTE. */
class FormFieldsAreWritableTest extends TestCase
{
    // Le schéma est nécessaire : un descripteur peuple ses listes déroulantes depuis la base au moment où on lui demande son formulaire.
    use RefreshDatabase;

    public function test_chaque_champ_de_formulaire_vise_une_colonne_qui_existe(): void
    {
        $registre = app(ResourceRegistry::class);
        $defauts = [];
        $verifies = 0;
        $couverts = 0;

        foreach ($registre->keys() as $cle) {
            $descripteur = $registre->for($cle);

            if ($descripteur === null || $descripteur->formFields() === []) {
                continue;
            }

            // `query()->getModel()` et non la méthode protégée `model()` : c'est ce que fait le contrôleur, et c'est le seul chemin qui existe sur les DIX descripteurs qui implémentent le contrat à la main.
            $modele = $descripteur->query()->getModel();
            $couverts++;

            $colonnes = $modele->getConnection()
                ->getSchemaBuilder()
                ->getColumnListing($modele->getTable());

            foreach ($descripteur->formFields() as $champ) {
                $nom = $champ->key();
                $verifies++;

                if (in_array($nom, $colonnes, true)) {
                    continue;
                }

                // Un mutateur peut porter une clé sans colonne — le cas est rare, mais réel.
                $mutateur = 'set'.str_replace('_', '', ucwords($nom, '_')).'Attribute';

                if (method_exists($modele, $mutateur)) {
                    continue;
                }

                $defauts[] = sprintf(
                    '%s : le champ « %s » ne correspond à aucune colonne de « %s » ni à un mutateur — la création tomberait en 500.',
                    $cle,
                    $nom,
                    $modele->getTable(),
                );
            }
        }

        // Les deux garde-fous de la MESURE elle-même.
        $this->assertSame([], $defauts, "Des champs visent une colonne qui n’existe pas :\n".implode("\n", $defauts));

        // Les seuils sont posés SOUS le relevé réel du jour, pas devinés au-dessus : ils attrapent l'effondrement de la mesure, pas la variation normale.
        $this->assertGreaterThanOrEqual(15, $couverts, 'La mesure ne voit presque aucun formulaire : elle a cessé de mesurer.');
        $this->assertGreaterThanOrEqual(90, $verifies, 'La mesure ne voit presque aucun champ : elle a cessé de mesurer.');
    }

    public function test_la_mesure_couvre_les_descripteurs_ecrits_a_la_main(): void
    {
        $registre = app(ResourceRegistry::class);

        // Ces six-là n'étendent pas `EloquentResource`.
        // Six descripteurs verifies ensemble : un registre ampute l'est rarement d'une seule
        // entree, et savoir que le premier manque ne dit rien des cinq autres.
        $ecarts = [];

        foreach (['users', 'companies', 'sites', 'promo-codes', 'badges', 'feature-flags'] as $cle) {
            $descripteur = $registre->for($cle);

            if ($descripteur === null) {
                $ecarts[] = "{$cle} : disparu du registre";
            } elseif ($descripteur->formFields() === []) {
                $ecarts[] = "{$cle} : formulaire vide, la mesure ne le regarderait plus";
            }
        }

        $this->assertSame([], $ecarts, 'Ces descripteurs ne portent plus de formulaire mesurable.');
    }
}
