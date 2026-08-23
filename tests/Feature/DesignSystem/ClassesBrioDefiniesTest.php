<?php

namespace Tests\Feature\DesignSystem;

use Tests\TestCase;

/** UNE CLASSE `brio-` UTILISÉE DANS UNE VUE DOIT EXISTER DANS LE CSS. POURQUOI CE FICHIER EXISTE. */
class ClassesBrioDefiniesTest extends TestCase
{
    /**
     * Classes employées dans les vues qui ne stylent RIEN, et le faisaient déjà avant le renommage.
     *
     * @var list<string>
     */
    private const SANS_STYLE_CONNUES = [
        'brio-choice-card',
        'brio-fade-up',
        'brio-feedback-response',
        'brio-feedback-shell',
        'brio-field-label',
        'brio-filter-panel',
        'brio-form-grid',
        'brio-inline-action',
        'brio-loading-panel',
        'brio-meta-grid',
        'brio-scale-in',
    ];

    /** @return list<string> */
    private function classesUtilisees(): array
    {
        $trouvees = [];

        foreach ($this->fichiers(resource_path('views'), 'blade.php') as $fichier) {
            $source = (string) file_get_contents($fichier);

            // On ne lit QUE les attributs `class` : `brio-fullcalendar` est un identifiant
            // d'élément, pas une classe, et le compter ici demanderait un style qui n'a pas lieu
            // d'être.
            preg_match_all('/class="([^"]*)"/', $source, $attributs);

            foreach ($attributs[1] as $valeur) {
                preg_match_all('/(?<![a-zA-Z0-9_-])(brio-[a-z0-9-]*[a-z0-9])/', $valeur, $classes);
                foreach ($classes[1] as $classe) {
                    $trouvees[$classe] = true;
                }
            }
        }

        $liste = array_keys($trouvees);
        sort($liste);

        return $liste;
    }

    /** @return list<string> */
    private function classesDefinies(): array
    {
        $trouvees = [];

        foreach ($this->fichiers(resource_path('css'), 'css') as $fichier) {
            preg_match_all('/\.(brio-[a-z0-9-]*[a-z0-9])/', (string) file_get_contents($fichier), $classes);
            foreach ($classes[1] as $classe) {
                $trouvees[$classe] = true;
            }
        }

        return array_keys($trouvees);
    }

    /** @return list<string> */
    private function fichiers(string $dossier, string $extension): array
    {
        if (! is_dir($dossier)) {
            return [];
        }

        $fichiers = [];
        $iterateur = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dossier));

        foreach ($iterateur as $fichier) {
            if ($fichier->isFile() && str_ends_with($fichier->getFilename(), $extension)) {
                $fichiers[] = $fichier->getPathname();
            }
        }

        return $fichiers;
    }

    public function test_chaque_classe_brio_employee_dans_une_vue_est_definie_en_css(): void
    {
        $orphelines = array_values(array_diff(
            $this->classesUtilisees(),
            $this->classesDefinies(),
            self::SANS_STYLE_CONNUES,
        ));

        $this->assertSame([], $orphelines, sprintf(
            "Ces classes sont employées dans une vue et ne stylent rien :\n  %s\n\n".
            'Définissez-les dans `resources/css`, ou retirez-les de la vue.',
            implode("\n  ", $orphelines),
        ));
    }

    public function test_les_classes_sans_style_connues_ne_sont_pas_perimees(): void
    {
        // Une liste de tolérance qui survit à la disparition de son motif finit par autoriser
        // n'importe quoi.
        $definies = $this->classesDefinies();

        // Toutes celles qui ont recu un style d'un coup : la liste d'exceptions se toilette en
        // une fois, pas classe par classe.
        $desormaisStylees = array_values(array_intersect(
            self::SANS_STYLE_CONNUES,
            is_array($definies) ? $definies : $definies->all(),
        ));

        $this->assertSame([], $desormaisStylees, 'Ces classes ont desormais un style : retirez-les de SANS_STYLE_CONNUES.');
    }

    public function test_le_prefixe_de_l_ancienne_marque_a_disparu(): void
    {
        foreach ([resource_path('views'), resource_path('css'), resource_path('js')] as $dossier) {
            foreach ($this->fichiers($dossier, 'php') + $this->fichiers($dossier, 'css') + $this->fichiers($dossier, 'js') as $fichier) {
                $source = (string) file_get_contents($fichier);

                $this->assertDoesNotMatchRegularExpression(
                    '/(?<![a-zA-Z0-9_])cu-[a-z]/',
                    $source,
                    'Préfixe `cu-` (CleanUx) resté dans '.$fichier
                );
            }
        }
    }

    public function test_la_mesure_mesure_encore_quelque_chose(): void
    {
        // Une expression régulière qui cesse de capturer rendrait les assertions ci-dessus vraies
        // pour une mauvaise raison — le piège que ce dépôt a déjà rencontré.
        $this->assertGreaterThan(30, count($this->classesUtilisees()), 'Plus aucune classe lue dans les vues.');
        $this->assertGreaterThan(30, count($this->classesDefinies()), 'Plus aucune classe lue dans le CSS.');
    }
}
