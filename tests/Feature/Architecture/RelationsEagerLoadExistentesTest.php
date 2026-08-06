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
    /**
     * LACUNES CONNUES, DOCUMENTÉES PLUTÔT QUE MASQUÉES.
     *
     * L'élargissement de l'expression (2026-08-06) a révélé trois eager-loads fautifs dans des
     * sous-systèmes sans rapport avec le lot en cours. Ils sont RÉELS — chacun lève une
     * `RelationNotFoundException` à l'exécution — et vérifiés un par un :
     *
     *   - `MissionTaskSegment` n'expose que `assignedUser` (BelongsTo, singulier). Ni
     *     `assignments` ni `memberStatuses` n'existent. Le panneau
     *     `teamlead/member-status-panel.blade.php` lit pourtant `$selectedSegment->assignments` :
     *     ce n'est donc pas un eager-load mort, c'est la fonctionnalité entière qui est à
     *     reconstruire — la relation manque, pas seulement son chargement.
     *   - `FleetVehicle` n'expose que `currentProvider`, `assignments` et `maintenanceLogs`.
     *     `certifications` n'existe pas ; rien ne la lit, celle-ci est un eager-load mort.
     *
     * On les inscrit ici plutôt que de les corriger à la volée : les réparer suppose de concevoir
     * les relations absentes, ce qui déborde du sujet. La liste les rend visibles et empêche la
     * garde de rester rouge sans raison lisible.
     *
     * @var list<string>
     */
    private const LACUNES_CONNUES = [
        "MissionTaskSegment::with('assignments.user')",
        "MissionTaskSegment::with('memberStatuses')",
        "FleetAssignment::with('vehicle.certifications')",
    ];

    #[Test]
    public function tout_eager_load_litteral_nomme_une_relation_existante(): void
    {
        $fautes = [];
        $verifies = 0;

        foreach ($this->fichiersPhp() as $fichier) {
            $code = (string) file_get_contents($fichier);

            /*
             * L'EXPRESSION A ÉTÉ ÉLARGIE (2026-08-06).
             *
             * Sa première version n'acceptait que `Modele::with(` et `Modele::query()->with(`.
             * Or la forme la plus répandue dans ce dépôt est `Modele::where(...)->with(...)` —
             * celle de `DispatchCenter`, entre autres. La garde couvrait donc bien moins de code
             * qu'elle n'en donnait l'impression : rassurante sans mordre, exactement le reproche
             * qu'on fait ici aux tests verts qui n'exercent que les sorties anticipées.
             *
             * On tolère désormais une chaîne d'appels intermédiaires avant `with(`, à condition
             * qu'elle ne contienne ni `;` ni saut de ligne — pour ne pas rattacher un `with()` à
             * un modèle nommé dans une instruction précédente.
             */
            preg_match_all(
                '/\b([A-Z][A-Za-z0-9_]*)::(?:[A-Za-z0-9_]+\([^;\n]*\)\s*->\s*)*with\(\s*(\[[^\]]*\]|\'[^\']*\')/m',
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

                    $signature = sprintf('%s::with(\'%s\')', $nomCourt, $chaine);

                    if ($probleme !== null && ! in_array($signature, self::LACUNES_CONNUES, true)) {
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
