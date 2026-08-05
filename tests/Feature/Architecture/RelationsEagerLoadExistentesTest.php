<?php

namespace Tests\Feature\Architecture;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * UN EAGER-LOAD QUI NOMME UNE RELATION INEXISTANTE NE SE VOIT QU'À L'EXÉCUTION.
 *
 * POURQUOI CE FICHIER EXISTE. `ProviderDashboard` chargeait `assignedWorker`, une relation absente
 * de `Mission` (le modèle expose `leadProvider()` et `assignments()`). Rien ne le signalait : ni
 * l'analyse statique, ni la compilation des vues, ni la suite — jusqu'au premier rendu comportant
 * une mission du jour, qui rendait une page blanche à toute société prestataire en activité.
 *
 * C'est la forme la plus coûteuse du défaut dominant de ce dépôt : un nom qui ne désigne rien.
 * Le trouver demandait de monter le composant avec des données représentatives — c'est-à-dire de
 * deviner d'avance où regarder.
 *
 * Cette garde renverse la charge : elle lit le code plutôt que de l'exécuter. Pour chaque
 * `Model::with([...])` littéral du dépôt, elle vérifie que chaque segment nommé correspond à une
 * vraie méthode de relation, en traversant les chaînes pointées (`parent.sender`).
 *
 * LIMITES ASSUMÉES. Elle ne couvre que la forme littérale `Modele::with(...)` / `Modele::query()
 * ->with(...)` — pas les eager-loads construits dynamiquement ni ceux posés sur une variable dont
 * le modèle n'est pas déductible du texte. Une garde partielle qui dit vrai vaut mieux qu'une
 * garde totale qui devine.
 */
class RelationsEagerLoadExistentesTest extends TestCase
{
    #[Test]
    public function tout_eager_load_litteral_nomme_une_relation_existante(): void
    {
        $fautes = [];
        $verifies = 0;

        foreach ($this->fichiersPhp() as $fichier) {
            $code = (string) file_get_contents($fichier);

            // `Modele::with([...])` ou `Modele::query()->with([...])`, argument littéral seulement.
            preg_match_all(
                '/\b([A-Z][A-Za-z0-9_]*)::(?:query\(\)\s*->)?with\(\s*(\[[^\]]*\]|\'[^\']*\')/m',
                $code,
                $occurrences,
                PREG_SET_ORDER
            );

            foreach ($occurrences as [$brut, $nomCourt, $arguments]) {
                $classe = $this->resoudreModele($nomCourt);

                if ($classe === null) {
                    continue;   // pas un modèle Eloquent connu : hors périmètre
                }

                /*
                 * Les contraintes par fermeture (`with(['x' => fn ($q) => $q->orderBy('code')])`)
                 * contiennent des chaînes qui ne sont PAS des relations : noms de colonnes,
                 * valeurs de filtre, relations d'un autre modèle. Ma première version les prenait
                 * pour des relations et signalait quatre fautes inexistantes. On écarte donc ces
                 * groupes en entier plutôt que de deviner lesquelles comptent.
                 */
                if (preg_match('/\bfn\b|\bfunction\b/', $arguments) === 1) {
                    continue;
                }

                preg_match_all('/\'([^\']+)\'/', $arguments, $chaines);

                foreach ($chaines[1] as $chaine) {
                    // `sender:id,name` — la sélection de colonnes ne fait pas partie du chemin.
                    $chemin = explode(':', $chaine)[0];

                    if ($chemin === '' || str_contains($chemin, ' ')) {
                        continue;
                    }

                    $verifies++;
                    $probleme = $this->cheminInvalide($classe, $chemin);

                    if ($probleme !== null) {
                        $fautes[] = sprintf(
                            '%s : %s::with(\'%s\') — %s',
                            str_replace(base_path().DIRECTORY_SEPARATOR, '', $fichier),
                            $nomCourt,
                            $chaine,
                            $probleme
                        );
                    }
                }
            }
        }

        $this->assertGreaterThan(
            50,
            $verifies,
            'La garde ne trouve presque rien à vérifier : son expression de recherche ne mord plus.'
        );

        $this->assertSame(
            [],
            $fautes,
            "Des eager-loads nomment des relations qui n'existent pas :\n".implode("\n", $fautes)
        );
    }

    /** @return list<string> */
    private function fichiersPhp(): array
    {
        $racines = [app_path('Livewire'), app_path('Services'), app_path('Http')];
        $fichiers = [];

        foreach ($racines as $racine) {
            if (! is_dir($racine)) {
                continue;
            }

            $iterateur = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($racine));

            foreach ($iterateur as $entree) {
                if ($entree->isFile() && $entree->getExtension() === 'php') {
                    $fichiers[] = $entree->getPathname();
                }
            }
        }

        return $fichiers;
    }

    /** @return class-string|null */
    private function resoudreModele(string $nomCourt): ?string
    {
        $classe = 'App\\Models\\'.$nomCourt;

        if (! class_exists($classe) || ! is_subclass_of($classe, Model::class)) {
            return null;
        }

        return $classe;
    }

    /** Décrit le premier segment fautif du chemin, ou `null` si tout existe. */
    private function cheminInvalide(string $classe, string $chemin): ?string
    {
        $courant = $classe;

        foreach (explode('.', $chemin) as $segment) {
            if (! method_exists($courant, $segment)) {
                return sprintf('%s n\'a pas de relation « %s »', class_basename($courant), $segment);
            }

            try {
                $relation = (new $courant)->{$segment}();
            } catch (\Throwable) {
                return null;   // méthode non instanciable hors contexte : on ne conclut pas
            }

            if (! $relation instanceof Relation) {
                return sprintf('%s::%s() n\'est pas une relation', class_basename($courant), $segment);
            }

            $courant = $relation->getRelated()::class;
        }

        return null;
    }
}
