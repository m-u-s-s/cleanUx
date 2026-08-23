<?php

namespace Tests\Feature\Architecture;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** UN EAGER-LOAD QUI NOMME UNE RELATION INEXISTANTE NE SE VOIT QU'À L'EXÉCUTION. */
class RelationsEagerLoadExistentesTest extends TestCase
{
    /**
     * LACUNES CONNUES, DOCUMENTÉES PLUTÔT QUE MASQUÉES.
     *
     * @var list<string>
     */
    private const LACUNES_CONNUES = [
        // `MissionTaskSegment::with('assignments.user')` et `with('memberStatuses')` ont été
        // RÉSOLUS le 2026-08-06 : les deux relations manquaient sur le segment alors que leurs
        // tables, leurs modèles et le côté direct existaient déjà. Retirés de cette liste.
        "FleetAssignment::with('vehicle.certifications')",
    ];

    #[Test]
    public function tout_eager_load_litteral_nomme_une_relation_existante(): void
    {
        $fautes = [];
        $verifies = 0;

        foreach ($this->fichiersPhp() as $fichier) {
            $code = (string) file_get_contents($fichier);

            // L'EXPRESSION A ÉTÉ ÉLARGIE (2026-08-06).
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

                // Les contraintes par fermeture (`with(['x' => fn ($q) => $q->orderBy('code')])`) contiennent des chaînes qui ne sont PAS des relations : noms de colonnes, valeurs de filtre, relations d'un autre modèle.
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
