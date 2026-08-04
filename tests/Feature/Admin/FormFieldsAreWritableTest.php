<?php

namespace Tests\Feature\Admin;

use App\Admin\Console\ResourceRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Un champ de formulaire déclaré doit correspondre à une VRAIE colonne.
 *
 * POURQUOI CE FICHIER EXISTE. Le contrôleur crée avec `forceFill()` : le descripteur est
 * l'autorité, pas le `$fillable` du modèle. Une clé inconnue n'est donc pas écartée — elle part
 * dans l'INSERT, et la base refuse. La création tombe en 500 pour un nom de colonne mal recopié,
 * et rien avant l'exécution ne le dit.
 *
 * J'AI DÉJÀ FAIT CETTE FAUTE sur ce moteur : `category_value` pour la colonne `category`, un nom de
 * propriété Livewire copié depuis l'écran web. Elle a été trouvée par un autre test, par chance.
 *
 * PREMIÈRE VERSION FAUSSE, GARDÉE EN MÉMOIRE. Ce fichier vérifiait d'abord `isFillable()`, et
 * passait — pour une raison sans rapport avec la réalité, `forceFill()` ne consultant jamais
 * `$fillable`. Il ne couvrait en plus que les descripteurs Eloquent, laissant les dix qui
 * implémentent le contrat à la main : `users`, `companies`, `promo-codes` en font partie, soit les
 * formulaires les plus exposés. Le modèle se prend maintenant par `query()->getModel()`, comme le
 * contrôleur le fait — la mesure emprunte donc le même chemin que le code qu'elle mesure.
 */
class FormFieldsAreWritableTest extends TestCase
{
    /*
     * Le schéma est nécessaire : un descripteur peuple ses listes déroulantes depuis la base au
     * moment où on lui demande son formulaire. Sans table, la mesure meurt avant de mesurer.
     */
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

            /*
             * `query()->getModel()` et non la méthode protégée `model()` : c'est ce que fait le
             * contrôleur, et c'est le seul chemin qui existe sur les DIX descripteurs qui
             * implémentent le contrat à la main.
             */
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

        /*
         * Les deux garde-fous de la MESURE elle-même. Le premier a déjà servi : une version de ce
         * fichier ne voyait que les descripteurs Eloquent et manquait six formulaires, quarante et
         * un champs, sans que rien ne le signale.
         */
        $this->assertSame([], $defauts, "Des champs visent une colonne qui n’existe pas :\n".implode("\n", $defauts));

        /*
         * Les seuils sont posés SOUS le relevé réel du jour, pas devinés au-dessus : ils attrapent
         * l'effondrement de la mesure, pas la variation normale. Devinés trop haut, ils faisaient
         * échouer le test pour une raison sans rapport avec ce qu'il cherche — c'est ce qui vient
         * d'arriver, et cela masquait le vrai résultat.
         */
        $this->assertGreaterThanOrEqual(15, $couverts, 'La mesure ne voit presque aucun formulaire : elle a cessé de mesurer.');
        $this->assertGreaterThanOrEqual(90, $verifies, 'La mesure ne voit presque aucun champ : elle a cessé de mesurer.');
    }

    public function test_la_mesure_couvre_les_descripteurs_ecrits_a_la_main(): void
    {
        $registre = app(ResourceRegistry::class);

        /*
         * Ces six-là n'étendent pas `EloquentResource`. Un filtre sur cette classe les écartait
         * silencieusement — et ce sont les formulaires les plus exposés de la console.
         */
        foreach (['users', 'companies', 'sites', 'promo-codes', 'badges', 'feature-flags'] as $cle) {
            $descripteur = $registre->for($cle);

            $this->assertNotNull($descripteur, "Le descripteur « {$cle} » a disparu du registre.");
            $this->assertNotSame([], $descripteur->formFields(), "Le formulaire de « {$cle} » est vide : la mesure ne le regarderait plus.");
        }
    }
}
