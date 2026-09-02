<?php

namespace Tests\Feature\Dispatch;

use Tests\TestCase;

/**
 * QUI A LE DROIT D'ECRIRE L'INTERVENANT D'UNE RESERVATION.
 *
 * Trois ecrans d'administration posaient `employe_id` et `CONFIRME` en direct : ni offre, ni ligne
 * d'assignation, ni garde KYC, ni controle facial. La mission restait invisible de la chaine
 * d'offres, et son historique vide.
 *
 * Ils passent tous par `DispatchEngine`. Ce test empeche le quatrieme : un ecran qui ecrirait de
 * nouveau en direct rougirait ici, pas six mois plus tard dans un audit.
 */
class LAffectationPasseParLaChaineTest extends TestCase
{
    /**
     * LES ECRITURES LEGITIMES, chacune avec sa raison. Ce ne sont pas des exceptions de confort :
     * ce sont les endroits ou poser un intervenant N'EST PAS une affectation de dispatch.
     */
    private const ECRITURES_LEGITIMES = [
        // Le moteur lui-meme : c'est LUI la chaine.
        'app/Services/Dispatch/DispatchEngine.php',
        // La creation d'une reservation porte le choix du client, avant tout dispatch.
        'app/Services/Booking/CreateBookingAction.php',
        'app/Services/OrderEngine/OrderConfirmationService.php',
        // L'import en masse cree des lignes deja pourvues ; il ne dispatche rien.
        'app/Livewire/Admin/ImportCsv.php',
        // L'affectation interne d'une societe, gardee par `MissionAssignmentService`.
        'app/Services/Missions/MissionAssignmentService.php',
        'app/Services/Organizations/OrganizationMemberAdministration.php',
        // Le retrait d'un intervenant qui signale un incident : il LIBERE, il n'affecte pas.
        'app/Livewire/Employe/SignalerIncident.php',
    ];

    public function test_aucun_ecran_neuf_n_ecrit_l_intervenant_en_direct(): void
    {
        $fautifs = [];

        foreach ($this->fichiersPhp() as $chemin => $source) {
            if (in_array($chemin, self::ECRITURES_LEGITIMES, true)) {
                continue;
            }

            foreach ($this->ecrituresDe($source) as $ligne) {
                $fautifs[] = $chemin.':'.$ligne;
            }
        }

        $this->assertSame([], $fautifs,
            "Ces fichiers ecrivent `employe_id` sans passer par `DispatchEngine` :\n"
            .implode("\n", $fautifs)
            ."\n\nUne affectation qui ne traverse pas la chaine ne laisse ni ligne d'assignation "
            ."ni historique, et ne repasse ni le KYC ni le controle facial. Si l'ecriture est "
            .'legitime, l\'inscrire dans ECRITURES_LEGITIMES AVEC SA RAISON.');
    }

    /**
     * TEMOIN — le motif reconnait bien la forme qu'il interdit, et ignore une simple lecture.
     * Sans lui, ce fichier passerait au vert en mesurant une expression reguliere fausse.
     */
    public function test_temoin_le_motif_distingue_l_ecriture_de_la_lecture(): void
    {
        $ecriture = "<?php\n\$rdv->update([\n    'employe_id' => \$employe->id,\n]);";
        $this->assertCount(1, $this->ecrituresDe($ecriture),
            'Le motif ne reconnait plus une ecriture.');

        $lecture = "<?php\n\$q->where('employe_id', \$id);\n\$x = \$rdv->employe_id;";
        $this->assertCount(0, $this->ecrituresDe($lecture),
            'Le motif prend une LECTURE pour une ecriture : un garde-fou qui crie a tort finit ignore.');
    }

    /**
     * TEMOIN — chaque exception protege encore une ecriture reelle.
     *
     * Une exception perimee est pire qu'absente : elle affirme qu'un fichier a une raison
     * d'ecrire alors qu'il n'ecrit plus rien, et masquerait une ecriture neuve qui y naitrait.
     */
    public function test_temoin_aucune_exception_n_est_perimee(): void
    {
        $perimees = [];

        foreach (self::ECRITURES_LEGITIMES as $chemin) {
            if ($this->ecrituresDe((string) file_get_contents(base_path($chemin))) === []) {
                $perimees[] = $chemin;
            }
        }

        $this->assertSame([], $perimees,
            'Ces exceptions ne protegent plus aucune ecriture : les retirer.'.'
'.implode('
', $perimees));
    }

    /** TEMOIN — le balayage lit bien des fichiers ; sinon le test ci-dessus mesure le vide. */
    public function test_temoin_le_balayage_lit_le_depot(): void
    {
        $this->assertGreaterThan(500, count($this->fichiersPhp()));
    }

    /** @return list<int> les numeros de ligne ou `employe_id` est ECRIT */
    private function ecrituresDe(string $source): array
    {
        $lignes = [];

        // LA CLE SEULE NE SUFFIT PAS. `['employe_id' => …]` sert aussi de filtre, de charge
        // utile ou de valeur par defaut. On n'accuse que si un APPEL D'ECRITURE la precede de
        // pres — `update(`, `forceFill(`, `fill(`, `create(`. Sans cela le garde-fou denoncait
        // dix fichiers innocents, et un garde-fou qui crie a tort finit ignore.
        foreach (explode("\n", $source) as $i => $ligne) {
            if (preg_match("/'employe_id'\s*=>/", $ligne) !== 1) {
                continue;
            }

            $amont = implode("\n", array_slice(explode("\n", $source), max(0, $i - 6), min($i, 6) + 1));

            if (preg_match('/->(?:update|forceFill|fill)\(|::(?:create|updateOrCreate|firstOrCreate)\(/', $amont) === 1) {
                $lignes[] = $i + 1;
            }
        }

        return $lignes;
    }

    /** @return array<string, string> */
    private function fichiersPhp(): array
    {
        $fichiers = [];
        $base = base_path();

        foreach (['app'] as $racine) {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(base_path($racine), \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($it as $f) {
                if (! $f->isFile() || ! str_ends_with($f->getFilename(), '.php')) {
                    continue;
                }

                $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($base) + 1));

                // Cache d'outillage, regenere : l'editer n'aurait aucun sens.
                if (str_contains($rel, 'graphify-out/')) {
                    continue;
                }

                $fichiers[$rel] = (string) file_get_contents($f->getPathname());
            }
        }

        return $fichiers;
    }
}
