<?php

namespace Tests\Feature\Ops;

use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/** `config:parity-check` — le contrôle qui doit refuser une configuration de prod incomplète. */
class ConfigParityCheckTest extends TestCase
{
    /** Un profil de production complet : tout est conforme, rien ne doit échouer. */
    private function profilDeProductionValide(): void
    {
        // Trois protections — HSTS, CSP, redirection HTTPS — ne s'arment qu'en environnement
        // « production ». Un hôte de prod laissé à `staging` les perd toutes les trois en silence.
        Config::set('app.env', 'production');
        Config::set('app.debug', false);
        Config::set('database.default', 'mysql');
        Config::set('queue.default', 'redis');
        Config::set('cache.default', 'redis');
        Config::set('broadcasting.default', 'reverb');
        Config::set('session.driver', 'database');
        Config::set('services.stripe.key', 'pk_live_valeur_de_test');
        Config::set('services.stripe.secret', 'sk_live_valeur_de_test');
        Config::set('services.stripe.webhook_secret', 'whsec_valeur_de_test');
        Config::set('services.stripe.connect_webhook_secret', 'whsec_connect_valeur_de_test');
        // Sans ce mot de passe, la sauvegarde que le déploiement prend juste avant de migrer est
        // écrite en clair sur le serveur applicatif.
        Config::set('backup.backup.password', 'mot_de_passe_archive_de_test');
    }

    public function test_passes_for_a_production_profile(): void
    {
        $this->profilDeProductionValide();

        $this->artisan('config:parity-check')->assertExitCode(0);
    }

    public function test_fails_when_queue_is_sync(): void
    {
        $this->profilDeProductionValide();
        Config::set('queue.default', 'sync'); // prod-unsafe — SEUL réglage cassé

        $this->artisan('config:parity-check')
            // « • queue.default » n'apparaît que dans la liste des offenseurs : la preuve que
            // l'échec vient bien de la file d'attente, et pas d'un autre réglage.
            ->expectsOutputToContain('• queue.default')
            ->assertExitCode(1);
    }

    public function test_fails_when_cache_is_file(): void
    {
        $this->profilDeProductionValide();
        Config::set('cache.default', 'file'); // prod-unsafe — SEUL réglage cassé

        $this->artisan('config:parity-check')
            ->expectsOutputToContain('• cache.default')
            ->assertExitCode(1);
    }

    public function test_names_the_offending_setting(): void
    {
        $this->profilDeProductionValide();
        Config::set('queue.default', 'sync');

        $this->artisan('config:parity-check')->expectsOutputToContain('queue')->assertExitCode(1);
    }

    // ── Environnement : les protections qui ne s'arment qu'en production ───────────────────

    /** UN HÔTE DE PRODUCTION LAISSÉ À `staging` PERD TROIS PROTECTIONS SANS RIEN DIRE. */
    public function test_echoue_quand_l_environnement_n_est_pas_production(): void
    {
        $this->profilDeProductionValide();
        Config::set('app.env', 'staging');

        $this->artisan('config:parity-check')
            ->expectsOutputToContain('• app.env')
            ->assertExitCode(1);
    }

    public function test_echoue_quand_le_mode_debogage_est_actif(): void
    {
        $this->profilDeProductionValide();
        Config::set('app.debug', true);

        $this->artisan('config:parity-check')
            ->expectsOutputToContain('• app.debug')
            ->assertExitCode(1);
    }

    /** SANS CE MOT DE PASSE, CHAQUE DÉPLOIEMENT DÉPOSE LA BASE EN CLAIR. */
    public function test_echoue_quand_la_sauvegarde_n_est_pas_chiffree(): void
    {
        $this->profilDeProductionValide();
        Config::set('backup.backup.password', null);

        $this->artisan('config:parity-check')
            ->expectsOutputToContain('• backup.backup.password')
            ->assertExitCode(1);
    }

    // ── Secrets de paiement ────────────────────────────────────────────────────────────────

    public function test_echoue_quand_la_cle_publique_stripe_est_absente(): void
    {
        $this->profilDeProductionValide();
        Config::set('services.stripe.key', null);

        $this->artisan('config:parity-check')
            ->expectsOutputToContain('• services.stripe.key')
            ->assertExitCode(1);
    }

    public function test_echoue_quand_le_secret_stripe_est_absent(): void
    {
        $this->profilDeProductionValide();
        Config::set('services.stripe.secret', '');

        $this->artisan('config:parity-check')
            ->expectsOutputToContain('• services.stripe.secret')
            ->assertExitCode(1);
    }

    public function test_echoue_quand_le_secret_de_webhook_stripe_est_absent(): void
    {
        $this->profilDeProductionValide();
        Config::set('services.stripe.webhook_secret', null);

        $this->artisan('config:parity-check')
            ->expectsOutputToContain('• services.stripe.webhook_secret')
            ->assertExitCode(1);
    }

    /** Le trou de H5 : sans ce secret, le contrôleur de webhook Connect ne peut vérifier aucune signature. */
    public function test_echoue_quand_le_secret_de_webhook_connect_est_absent(): void
    {
        $this->profilDeProductionValide();
        Config::set('services.stripe.connect_webhook_secret', null);

        $this->artisan('config:parity-check')
            ->expectsOutputToContain('• services.stripe.connect_webhook_secret')
            ->assertExitCode(1);
    }

    /** `.env.production.example` livre `STRIPE_SECRET=sk_live_CHANGE_ME`. */
    public function test_echoue_quand_un_secret_est_reste_au_gabarit(): void
    {
        $this->profilDeProductionValide();
        Config::set('services.stripe.secret', 'sk_live_CHANGE_ME');

        $this->artisan('config:parity-check')
            ->expectsOutputToContain('• services.stripe.secret')
            ->assertExitCode(1);
    }

    /** La sortie part dans des journaux de CI et de déploiement conservés longtemps : la valeur d'un secret ne doit jamais y figurer, même quand tout va bien. */
    public function test_la_valeur_d_un_secret_n_est_jamais_ecrite_dans_la_sortie(): void
    {
        $this->profilDeProductionValide();
        Config::set('services.stripe.secret', 'sk_live_ce_texte_ne_doit_pas_fuiter');

        $this->artisan('config:parity-check')
            ->doesntExpectOutputToContain('sk_live_ce_texte_ne_doit_pas_fuiter')
            ->assertExitCode(0);
    }

    // ── Câblage dans les flux de déploiement ──────────────────────────────────────────────

    /** @return list<string> */
    /**
     * Ce qui doit tourner AVANT que la base ne soit touchée.
     *
     * @var list<string>
     */
    private const PREALABLES_AVANT_MIGRATION = [
        'php artisan config:cache',
        'php artisan route:cache',
        'php artisan view:cache',
        'php artisan event:cache',
        'php artisan config:parity-check',
        'php artisan ops:check-providers --strict',
        'php artisan storage:link',
        'php artisan backup:run',
    ];

    /**
     * Commande => ce qui se passe quand elle n'est pas câblée.
     *
     * @var array<string, string>
     */
    private const COMMANDES_DE_GARDE = [
        'php artisan config:parity-check' => 'le contrôle de parité ne bloque rien',
        'php artisan ops:check-providers --strict' => 'le déploiement peut partir sur des bouchons',
        'php artisan storage:link' => 'le lien public/storage n’est jamais recréé, médias en 404',
    ];

    private function fluxDeDeploiement(): array
    {
        return [
            '.github/workflows/deploy.yml',
            '.github/workflows/deploy-staging.yml',
        ];
    }

    /** Le contenu du flux PRIVÉ DE SES COMMENTAIRES. */
    private function contenuExecutable(string $chemin): string
    {
        $absolu = base_path($chemin);

        $this->assertFileExists($absolu, "Le flux {$chemin} a disparu : ce test ne mesure plus rien.");

        $lignes = preg_split('/\R/', (string) file_get_contents($absolu)) ?: [];

        $lignes = array_filter($lignes, fn (string $ligne): bool => ! str_starts_with(ltrim($ligne), '#'));

        return implode("\n", $lignes);
    }

    /** Position d'un jalon, avec échec explicite s'il a disparu du script. */
    private function positionDe(string $contenu, string $jalon, string $chemin): int
    {
        $position = strpos($contenu, $jalon);

        $this->assertNotFalse($position, "{$chemin} : « {$jalon} » est absent du script de déploiement.");

        return $position;
    }

    /** B3 — l'inversion qui migrait la base pendant que le job était rapporté en échec. */
    public function test_les_flux_de_deploiement_valident_tout_avant_de_muter_la_base(): void
    {
        // ON RELÈVE TOUS LES DÉSORDRES, PUIS ON LES AFFIRME D'UN COUP.
        $desordres = [];

        foreach ($this->fluxDeDeploiement() as $chemin) {
            $contenu = $this->contenuExecutable($chemin);
            $migration = strpos($contenu, 'php artisan migrate --force');

            if ($migration === false) {
                $desordres[] = "{$chemin} : « migrate --force » est absent";

                continue;
            }

            foreach (self::PREALABLES_AVANT_MIGRATION as $prealable) {
                $position = strpos($contenu, $prealable);

                if ($position === false) {
                    $desordres[] = "{$chemin} : « {$prealable} » est absent";
                } elseif ($position >= $migration) {
                    $desordres[] = "{$chemin} : « {$prealable} » passe APRÈS « migrate --force »";
                }
            }
        }

        $this->assertSame(
            [],
            $desordres,
            'Un échec de ces étapes laisserait une base déjà migrée derrière un job rouge.',
        );
    }

    /** TROIS COMMANDES QUI DOIVENT ÊTRE CÂBLÉES, ET LA LISTE COMPLÈTE DE CE QUI MANQUE. */
    public function test_les_flux_de_deploiement_cablent_les_commandes_de_garde(): void
    {
        $manquantes = [];

        foreach ($this->fluxDeDeploiement() as $chemin) {
            $contenu = $this->contenuExecutable($chemin);

            foreach (self::COMMANDES_DE_GARDE as $commande => $consequence) {
                if (! str_contains($contenu, $commande)) {
                    $manquantes[] = "{$chemin} : « {$commande} » — {$consequence}";
                }
            }
        }

        $this->assertSame([], $manquantes, 'Ces gardes ne sont pas câblées dans le déploiement.');
    }

    /** APP_KEY (low 54) — deux exigences opposées dans le même test, exprès. */
    public function test_les_flux_de_deploiement_gardent_la_cle_sans_jamais_la_regenerer(): void
    {
        foreach ($this->fluxDeDeploiement() as $chemin) {
            $contenu = $this->contenuExecutable($chemin);

            $this->assertStringNotContainsString(
                'key:generate',
                $contenu,
                "{$chemin} : `key:generate` en déploiement récurrent rendrait illisibles les données déjà chiffrées.",
            );

            $garde = $this->positionDe($contenu, 'ABANDON : APP_KEY', $chemin);
            $recuperation = $this->positionDe($contenu, 'git pull', $chemin);

            $this->assertLessThan(
                $recuperation,
                $garde,
                "{$chemin} : le garde APP_KEY doit passer en tête, avant même de récupérer le code.",
            );
        }
    }

    /** L'ORDRE NE SUFFIT PAS : IL FAUT QUE L'ÉCHEC ARRÊTE LE SCRIPT. */
    public function test_aucune_etape_bloquante_ne_peut_echouer_en_silence(): void
    {
        $etapesBloquantes = [
            'config:cache',
            'route:cache',
            'view:cache',
            'event:cache',
            'config:parity-check',
            'storage:link',
            'backup:run',
            'migrate --force',
        ];

        // `|| :` est l'écriture courte de `|| true` ; `continue-on-error` est son équivalent GitHub.
        $neutralisants = ['|| true', '||true', '|| :', 'continue-on-error: true', 'set +e'];

        // DEUX FLUX x TOUTES LEURS LIGNES x HUIT ÉTAPES x CINQ NEUTRALISANTS.
        $desamorcees = [];

        foreach ($this->fluxDeDeploiement() as $chemin) {
            $lignes = preg_split('/\R/', $this->contenuExecutable($chemin)) ?: [];

            if (! preg_grep('/^\s*set -e/', $lignes)) {
                $desamorcees[] = "{$chemin} : `set -e` absent — aucune étape n'arrête plus rien";
            }

            foreach ($lignes as $numero => $ligne) {
                foreach ($etapesBloquantes as $etape) {
                    if (! str_contains($ligne, $etape)) {
                        continue;
                    }

                    foreach ($neutralisants as $neutralisant) {
                        if (str_contains($ligne, $neutralisant)) {
                            $desamorcees[] = sprintf(
                                '%s ligne %d : « %s » neutralise l\'échec de « %s »',
                                $chemin,
                                $numero + 1,
                                $neutralisant,
                                $etape,
                            );
                        }
                    }
                }
            }
        }

        $this->assertSame(
            [],
            $desamorcees,
            'Une étape bloquante qui ne bloque plus laisse passer exactement ce qu’elle devait arrêter.',
        );
    }

    /** LA GARDE DE CHEMIN DOIT TESTER LA CHAÎNE VIDE, PAS SE FIER À `cd`. */
    public function test_les_flux_de_deploiement_refusent_un_chemin_de_deploiement_vide(): void
    {
        foreach ($this->fluxDeDeploiement() as $chemin) {
            $contenu = $this->contenuExecutable($chemin);

            $this->assertMatchesRegularExpression(
                '/(-z\s+"\$|test\s+-z|\[\[?\s+-z)/',
                $contenu,
                "{$chemin} : aucun test de chaîne vide sur le chemin de déploiement. ".
                '`cd ""` réussit et ne garde donc rien.',
            );
        }
    }
}
