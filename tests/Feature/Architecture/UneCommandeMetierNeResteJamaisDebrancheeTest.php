<?php

namespace Tests\Feature\Architecture;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

/**
 * UNE COMMANDE QUI DOIT TOURNER SEULE DOIT ETRE DECLENCHEE PAR QUELQUE CHOSE.
 *
 * Trois commandes ecrites, correctes et testees n'etaient citees QUE dans leur propre
 * fichier : ni dans l'ordonnanceur, ni dans un appel, ni dans la CI, ni dans un script de
 * deploiement. Ce n'etait pas du code mort — c'etait du code DEBRANCHE, et la difference
 * tient en une ligne.
 *
 *   disputes:process-sla       le delai d'un litige n'etait jamais escalade.
 *   matching:refresh-metrics   `provider_performance_metrics` comptait ZERO ligne, et le
 *                              moteur de scoring y lit trois de ses criteres.
 *   loyalty:reevaluate-tiers   la retrogradation par inactivite n'avait que cette porte.
 *
 * Rien ne pouvait le dire : la commande existe, elle repond a `artisan list`, ses tests
 * passent. Elle ne s'execute simplement jamais.
 */
class UneCommandeMetierNeResteJamaisDebrancheeTest extends TestCase
{
    /**
     * Ce qui n'a PAS a etre planifie, et pourquoi.
     *
     * Une commande d'outillage s'invoque a la main ou depuis un poste de travail ; l'exiger
     * dans l'ordonnanceur ferait tourner un audit toutes les nuits pour personne.
     *
     * @var array<string, string>
     */
    private const OUTILLAGE = [
        'app:audit-seed-integrity' => 'audit ponctuel, lance a la main',
        'app:security-audit' => 'audit ponctuel, lance a la main',
        'db:check-missing-tables' => 'diagnostic de poste de travail',
        'deploy:check' => 'invoquee par le deploiement, pas par le temps',
        'dispo:generer' => 'generation de jeu d’essai',
        'livewire:routes' => 'inventaire, pour un humain',
        'livewire:verify' => 'inventaire, pour un humain',
        'parity:webview-manifest' => 'genere un fichier, a la demande',
        'translations:scan' => 'outil de traduction',
        'translations:sync' => 'outil de traduction',
        'webpush:vapid' => 'genere une paire de cles, une fois',

        // Audits et rapports : ils LISENT, ils ne changent rien.
        'app:cleanup-report' => 'rapport de nettoyage, pour un humain',
        'app:communication-health-check' => 'audit des communications, a la demande',
        'app:consolidation-final-check' => 'audit ponctuel',
        'app:go-live-readiness-report' => 'rapport de mise en ligne',
        'golive:preflight' => 'controle avant deploiement, invoque par le deploiement',
        'livewire:missing-views' => 'inventaire, pour un humain',
        'livewire:unused-includes' => 'inventaire, pour un humain',
        'schema:audit-drift' => 'audit de derive du schema',

        // Generateurs : ils produisent un fichier, quand on le leur demande.
        'parity:scaffold-registry' => 'genere un registre',
        'pwa:icons' => 'genere les icones, une fois',

        // Reprises : le chemin normal est ailleurs, ces commandes rattrapent l'existant.
        'media:migrate-to-private' => 'migration unique du stockage',
        'organizations:backfill-current' => 'reprise unique',
        'organizations:recompute-ratings' => 'reprise ; le calcul vif passe par RatingAggregationService:95',
        'availability:provision-defaults' => 'reprise ; la pose vive se fait a la creation du compte',

        // Aide au developpement : lit le dernier code SMS envoye, pour tester sans telephone.
        'sms:dernier-code' => 'aide de poste de travail',
    ];

    /**
     * Les commandes METIER : celles dont l'absence change ce que voit un utilisateur.
     * Chacune est nommee avec ce qu'elle porte, pour qu'on sache ce qu'on perd si on la retire.
     *
     * @var array<string, string>
     */
    private const METIER = [
        'disputes:process-sla' => 'escalade le delai d’un litige',
        'matching:refresh-metrics' => 'alimente les criteres de performance du scoring',
        'loyalty:reevaluate-tiers' => 'retrograde un palier apres inactivite',
    ];

    public function test_chaque_commande_metier_est_declenchee_par_l_ordonnanceur(): void
    {
        $planifiees = $this->commandesPlanifiees();
        $absentes = [];

        foreach (self::METIER as $signature => $role) {
            if (! in_array($signature, $planifiees, true)) {
                $absentes[] = $signature.' — '.$role;
            }
        }

        $this->assertSame([], $absentes,
            'Une commande metier n’est declenchee par rien : elle existe et ne s’execute jamais.');
    }

    /**
     * TEMOIN. Sans lui, le test ci-dessus passerait au vert si la lecture de l’ordonnanceur
     * rendait tout — ou rien : il mesurerait alors sa propre panne, dans un sens ou dans l’autre.
     */
    public function test_temoin_la_lecture_de_l_ordonnanceur_est_fidele(): void
    {
        $planifiees = $this->commandesPlanifiees();

        $this->assertGreaterThan(20, count($planifiees),
            'L’ordonnanceur ne rend presque rien : le test ci-dessus ne prouverait plus grand-chose.');

        // Une commande qu’on sait planifiee depuis longtemps.
        $this->assertContains('payouts:process', $planifiees);

        // …et une qu’on sait volontairement absente.
        $this->assertNotContains('webpush:vapid', $planifiees,
            'Une commande d’outillage est planifiee : soit la liste est fausse, soit c’est un oubli.');
    }

    /**
     * Toutes les signatures declarees, moins l'outillage : rien ne doit rester sans emploi.
     */
    public function test_aucune_commande_n_est_ni_planifiee_ni_declaree_outillage(): void
    {
        $planifiees = $this->commandesPlanifiees();
        $orphelines = [];

        foreach ($this->signaturesDeclarees() as $signature => $classe) {
            if (in_array($signature, $planifiees, true) || isset(self::OUTILLAGE[$signature])) {
                continue;
            }

            // Une commande appelee par du code n'a pas besoin de l'ordonnanceur.
            if ($this->citeeAilleurs($signature, $classe)) {
                continue;
            }

            $orphelines[] = $signature.'  ('.$classe.')';
        }

        sort($orphelines);

        $this->assertSame([], $orphelines,
            'Une commande n’est ni planifiee, ni appelee, ni declaree outillage : elle ne tourne nulle part. '
            .'Soit on la branche, soit on la nomme dans OUTILLAGE avec sa raison, soit on la supprime.');
    }

    /** @return list<string> */
    private function commandesPlanifiees(): array
    {
        $schedule = $this->app->make(Schedule::class);
        $signatures = [];

        foreach ($schedule->events() as $event) {
            if (preg_match('/artisan[\'"]?\s+(?:[\'"])?([a-z0-9:_-]+)/i', $event->command ?? '', $m) === 1) {
                $signatures[] = $m[1];
            }
        }

        return array_values(array_unique($signatures));
    }

    /** @return array<string, string> */
    private function signaturesDeclarees(): array
    {
        $signatures = [];
        $base = app_path('Console/Commands');

        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base));

        foreach ($it as $f) {
            if (! $f->isFile() || $f->getExtension() !== 'php') {
                continue;
            }

            $source = (string) file_get_contents($f->getPathname());

            if (preg_match('/\$signature\s*=\s*[\'"]([a-z0-9:_-]+)/i', $source, $m) === 1) {
                $signatures[$m[1]] = $f->getBasename('.php');
            }
        }

        return $signatures;
    }

    /** La signature est-elle invoquee ailleurs qu'ici (code, CI, deploiement) ? */
    private function citeeAilleurs(string $signature, string $classe): bool
    {
        foreach ([app_path(), base_path('.github'), base_path('deploy'), base_path('scripts')] as $racine) {
            if (! is_dir($racine)) {
                continue;
            }

            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($racine));

            foreach ($it as $f) {
                if (! $f->isFile() || str_contains($f->getPathname(), $classe.'.php')) {
                    continue;
                }

                if (str_contains((string) file_get_contents($f->getPathname()), $signature)) {
                    return true;
                }
            }
        }

        return false;
    }
}
