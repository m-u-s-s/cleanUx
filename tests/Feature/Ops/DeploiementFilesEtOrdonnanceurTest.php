<?php

namespace Tests\Feature\Ops;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Aucun test ne lisait les fichiers de deploy/ : c'est exactement pour cela qu'un worker lancé sur
 * la connexion « database » a pu cohabiter des mois avec une production configurée en Redis. Les
 * workers drainaient la table `jobs` pendant que l'application poussait dans Redis — zéro job
 * traité, donc aucune escalade du moteur de répartition (EscalateMissionAssignmentJob part sur la
 * file « default » avec un delay).
 *
 * Ce test lit les exemples de déploiement comme le ferait l'ops qui les copie, et échoue dès qu'ils
 * décrivent autre chose que ce que l'application fait réellement.
 */
class DeploiementFilesEtOrdonnanceurTest extends TestCase
{
    // ----------------------------------------------------------------------------------
    // 1. La connexion des workers doit suivre celle de la production
    // ----------------------------------------------------------------------------------

    public function test_aucun_worker_ne_force_une_connexion_differente_de_la_production(): void
    {
        $attendue = $this->connexionDeProduction();
        $lignes = $this->lignesDeWorkers();

        $this->assertNotEmpty($lignes, 'Aucune commande queue:work trouvée dans deploy/ : les workers ne sont plus provisionnés.');

        foreach ($lignes as $ligne) {
            $connexion = $this->connexionDuWorker($ligne['commande']);

            $this->assertTrue(
                $connexion === null || $connexion === $attendue,
                sprintf(
                    "%s lance « queue:work %s » alors que .env.production.example déclare QUEUE_CONNECTION=%s.\n".
                    "L'argument positionnel de queue:work est la CONNEXION : le worker draine alors une autre ".
                    "banque que celle où l'application pousse ses jobs. Laisse l'argument vide (artisan lit ".
                    'config(\'queue.default\')) ou aligne-le sur la production.',
                    $ligne['fichier'],
                    (string) $connexion,
                    $attendue
                )
            );
        }
    }

    // ----------------------------------------------------------------------------------
    // 2. Toute file employée doit être drainée par au moins un worker
    // ----------------------------------------------------------------------------------

    public function test_toutes_les_files_employees_sont_drainees_par_au_moins_un_worker(): void
    {
        $employees = $this->filesEmployees();
        $drainees = $this->filesDrainees();

        $orphelines = array_values(array_diff($employees, $drainees));

        $this->assertSame(
            [],
            $orphelines,
            sprintf(
                "Ces files reçoivent des jobs mais aucun worker de deploy/ ne les draine : %s.\n".
                'Files drainées aujourd\'hui : %s.',
                implode(', ', $orphelines),
                implode(', ', $drainees)
            )
        );
    }

    public function test_la_file_default_qui_porte_l_escalade_du_dispatch_est_drainee(): void
    {
        // EscalateMissionAssignmentJob (app/Services/Dispatch/DispatchEngine.php) est dispatché
        // sans onQueue() : il atterrit sur « default ». Sans worker sur cette file, le TTL de 20 s
        // et les vagues du moteur de répartition ne se déclenchent jamais.
        $this->assertContains('default', $this->filesDrainees(), 'Aucun worker ne draine la file « default ».');
    }

    // ----------------------------------------------------------------------------------
    // 3. L'ordonnanceur doit être provisionné, et son arrêt détectable
    // ----------------------------------------------------------------------------------

    public function test_l_ordonnanceur_est_provisionne_et_declenche_chaque_minute(): void
    {
        $service = null;

        foreach ($this->fichiersDeDeploiement() as $fichier => $contenu) {
            // Une mention en commentaire ne provisionne rien : on exige un ExecStart qui LANCE
            // réellement l'ordonnanceur.
            if (preg_match('/^\s*(ExecStart|command)\s*=.*artisan\s+schedule:run/m', $contenu) === 1) {
                $service = basename($fichier);
                break;
            }
        }

        $this->assertNotNull($service, 'Aucun fichier de deploy/ n\'exécute « schedule:run » : aucune des tâches planifiées de Kernel.php ne tourne (facturation, RGPD, présence, numéros proxy…).');

        $minuteParMinute = false;

        foreach ($this->fichiersDeDeploiement() as $fichier => $contenu) {
            if (! str_ends_with($fichier, '.timer.example')) {
                continue;
            }

            if (! str_contains($contenu, str_replace('.example', '', $service))) {
                continue;
            }

            if (preg_match('/OnCalendar\s*=\s*(minutely|\*-\*-\* \*:\*:00)/', $contenu) === 1
                || preg_match('/OnUnitActiveSec\s*=\s*1min/', $contenu) === 1) {
                $minuteParMinute = true;
            }
        }

        $this->assertTrue($minuteParMinute, sprintf('Aucun timer systemd ne déclenche %s chaque minute.', $service));

        $doc = $this->documentation();
        $this->assertStringContainsString('schedule:run', $doc, 'La doc de production ne montre pas comment provisionner l\'ordonnanceur.');
        $this->assertStringContainsString('brio-scheduler.timer', $doc, 'La doc de production ne mentionne pas le timer systemd de l\'ordonnanceur.');
    }

    public function test_une_sonde_existante_detecte_un_ordonnanceur_arrete(): void
    {
        $commandes = array_keys(Artisan::all());
        $sonde = null;

        foreach ($this->fichiersDeDeploiement() as $fichier => $contenu) {
            if (! str_contains($fichier, 'heartbeat') && ! str_contains($contenu, 'health-check')) {
                continue;
            }

            foreach ($commandes as $commande) {
                // La sonde doit RÉUTILISER une commande artisan existante (le heartbeat ops est
                // déjà planifié toutes les 5 min et déjà contrôlé par ProductionHealthReport),
                // pas en réinventer une seconde.
                if ($commande !== '' && str_contains($contenu, 'artisan '.$commande)) {
                    $sonde = $commande;
                    break 2;
                }
            }
        }

        $this->assertNotNull($sonde, 'Aucune sonde de deploy/ ne vérifie que l\'ordonnanceur a tourné récemment.');

        $doc = $this->documentation();
        $this->assertStringContainsString(
            'OPS_HEARTBEAT_MAX_AGE_SECONDS',
            $doc,
            'La doc ne dit pas au bout de combien de temps un heartbeat absent doit alerter.'
        );
    }

    // ----------------------------------------------------------------------------------
    // 4. La documentation ne doit pas contredire les fichiers de déploiement
    // ----------------------------------------------------------------------------------

    public function test_la_documentation_ne_montre_aucun_worker_sans_liste_de_files(): void
    {
        $attendue = $this->connexionDeProduction();

        foreach (preg_split('/\R/', $this->documentation()) as $numero => $ligne) {
            // Seules les lignes de COMMANDE sont jugées : la prose peut citer « queue:work ».
            if (preg_match('/artisan\s+queue:work/', $ligne) !== 1) {
                continue;
            }

            $connexion = $this->connexionDuWorker($ligne);

            $this->assertTrue(
                $connexion === null || $connexion === $attendue,
                sprintf('QUEUE_CRON_SCHEDULER.md ligne %d documente la connexion « %s » au lieu de « %s ».', $numero + 1, (string) $connexion, $attendue)
            );

            $this->assertStringContainsString(
                '--queue=',
                $ligne,
                sprintf(
                    'QUEUE_CRON_SCHEDULER.md ligne %d documente un worker SANS --queue : il ne draine alors que '.
                    '« default » et abandonne les webhooks Stripe/KYC/SMS/assurance et l\'antivirus.',
                    $numero + 1
                )
            );
        }
    }

    public function test_l_inventaire_documente_correspond_aux_taches_reellement_planifiees(): void
    {
        // L'inventaire nommait « subscriptions:generate » et « gdpr:execute-erasure-requests »,
        // deux commandes qui n'existent pas : l'ops qui contrôlait son déploiement à partir de
        // cette table cherchait des tâches fantômes et ne voyait pas les vraies manquer.
        $kernel = File::get(app_path('Console/Kernel.php'));
        preg_match_all('/\$schedule->command\(\'([^\' ]+)/', $kernel, $m);

        $planifiees = array_values(array_unique($m[1]));
        $this->assertNotEmpty($planifiees, 'Aucune tâche planifiée détectée dans Kernel.php : la lecture du fichier a changé.');

        $doc = $this->documentation();
        $connues = array_keys(Artisan::all());

        foreach ($planifiees as $commande) {
            $this->assertContains($commande, $connues, sprintf('Kernel.php planifie « %s », qui n\'est pas une commande artisan existante.', $commande));
            $this->assertStringContainsString(
                $commande,
                $doc,
                sprintf('La tâche planifiée « %s » ne figure pas dans l\'inventaire de QUEUE_CRON_SCHEDULER.md.', $commande)
            );
        }
    }

    // ----------------------------------------------------------------------------------
    // Outils de lecture
    // ----------------------------------------------------------------------------------

    /**
     * Connexion de file déclarée par le profil de production (source de vérité du test).
     */
    protected function connexionDeProduction(): string
    {
        $contenu = File::get(base_path('.env.production.example'));

        $this->assertSame(
            1,
            preg_match('/^QUEUE_CONNECTION=(.+)$/m', $contenu, $m),
            '.env.production.example ne déclare pas QUEUE_CONNECTION.'
        );

        return trim($m[1]);
    }

    /**
     * Tous les fichiers de deploy/, indexés par chemin relatif.
     *
     * @return array<string, string>
     */
    protected function fichiersDeDeploiement(): array
    {
        $fichiers = [];

        foreach (File::allFiles(base_path('deploy')) as $fichier) {
            $fichiers[str_replace('\\', '/', $fichier->getRelativePathname())] = $fichier->getContents();
        }

        return $fichiers;
    }

    /**
     * Lignes de deploy/ qui lancent réellement un worker.
     *
     * @return list<array{fichier: string, commande: string}>
     */
    protected function lignesDeWorkers(): array
    {
        $lignes = [];

        foreach ($this->fichiersDeDeploiement() as $fichier => $contenu) {
            foreach (preg_split('/\R/', $contenu) as $ligne) {
                if (preg_match('/^\s*(command|ExecStart)\s*=/', $ligne) !== 1) {
                    continue;
                }

                if (! str_contains($ligne, 'queue:work')) {
                    continue;
                }

                $lignes[] = ['fichier' => $fichier, 'commande' => $ligne];
            }
        }

        return $lignes;
    }

    /**
     * Argument POSITIONNEL de queue:work, c'est-à-dire la connexion. null = suit config('queue.default').
     */
    protected function connexionDuWorker(string $commande): ?string
    {
        $tokens = preg_split('/\s+/', trim($commande)) ?: [];
        $index = array_search('queue:work', $tokens, true);

        if ($index === false) {
            return null;
        }

        $suivant = $tokens[$index + 1] ?? null;

        if ($suivant === null || $suivant === '' || str_starts_with($suivant, '-')) {
            return null;
        }

        return $suivant;
    }

    /**
     * Files effectivement drainées par les workers de deploy/.
     *
     * @return list<string>
     */
    protected function filesDrainees(): array
    {
        $files = [];

        foreach ($this->lignesDeWorkers() as $ligne) {
            if (preg_match('/--queue[= ]([^\s]+)/', $ligne['commande'], $m) === 1) {
                foreach (explode(',', $m[1]) as $file) {
                    $files[] = trim($file);
                }

                continue;
            }

            // Sans --queue, un worker ne draine que la file par défaut de la connexion.
            $files[] = 'default';
        }

        return array_values(array_unique(array_filter($files)));
    }

    /**
     * Files réellement employées : celles nommées dans app/, celles résolues par configuration,
     * et les groupes de priorité que config/queue.php demande de recopier « verbatim » dans les
     * configs Supervisor / Forge.
     *
     * @return list<string>
     */
    protected function filesEmployees(): array
    {
        $files = ['default'];

        foreach (File::allFiles(app_path()) as $fichier) {
            if ($fichier->getExtension() !== 'php') {
                continue;
            }

            $contenu = $fichier->getContents();

            if (! str_contains($contenu, 'onQueue(')) {
                continue;
            }

            // onQueue('stripe-webhooks')
            preg_match_all("/onQueue\(\s*'([^']+)'/", $contenu, $litteraux);
            foreach ($litteraux[1] as $file) {
                $files[] = $file;
            }

            // onQueue((string) config('webhooks_v2.queue', 'webhooks'))
            preg_match_all("/onQueue\(\s*(?:\(string\)\s*)?config\(\s*'([^']+)'\s*,\s*'([^']+)'\s*\)/", $contenu, $configures, PREG_SET_ORDER);
            foreach ($configures as $capture) {
                $files[] = (string) config($capture[1], $capture[2]);
            }
        }

        // Groupes de priorité documentés dans .env.example (lus dans le FICHIER, pas dans l'env du
        // poste, pour que le test reste déterministe).
        $env = File::get(base_path('.env.example'));
        foreach (['QUEUE_HIGH', 'QUEUE_DEFAULT', 'QUEUE_LOW'] as $cle) {
            if (preg_match('/^'.$cle.'=(.+)$/m', $env, $m) === 1) {
                foreach (explode(',', trim($m[1])) as $file) {
                    $files[] = trim($file);
                }
            }
        }

        return array_values(array_unique(array_filter($files)));
    }

    protected function documentation(): string
    {
        return File::get(base_path('docs/production/QUEUE_CRON_SCHEDULER.md'));
    }
}
